<?php

namespace PrestaFlow\Library\Support;

use Throwable;

/**
 * Requêtes réseau en échec pendant un test : réponses HTTP ≥ 400 (Network.responseReceived)
 * et chargements qui n'aboutissent pas (Network.loadingFailed : DNS, connexion refusée, CORS…).
 *
 * Typiquement l'ajax du panier qui renvoie un 500 alors que la page garde l'ancien état, ou
 * le JS d'un module en 404 après une mise à jour. Comme les erreurs JS, elles sont jointes au
 * test (`failed_requests`) sans le faire échouer ; un test strict les vérifie avec
 * `$page->failedRequests()`.
 *
 * chrome-php active déjà le domaine Network sur chaque page. Les événements ne sont lus qu'en
 * lisant le socket : les appels synchrones des tests suffisent pendant l'activité, drain() vide
 * la file avant de lire la liste.
 *
 * Écartés d'office : requêtes annulées (navigation qui part, ERR_ABORTED), bloquées par le
 * navigateur ou une extension, favicon, URL data:/blob:. La collecte ne doit jamais faire
 * échouer un run : toute erreur la coupe, sans exception.
 */
class FailedRequests
{
    /** Requêtes distinctes gardées par test (les répétitions sont comptées). */
    public const MAX_PER_TEST = 20;

    /** Requêtes en vol mémorisées (méthode, URL), pour nommer un échec sans réponse. */
    private const MAX_PENDING = 500;

    private const MAX_URL = 500;

    private const IGNORED_ERRORS = ['net::ERR_ABORTED', 'net::ERR_BLOCKED_BY_CLIENT', 'net::ERR_BLOCKED_BY_RESPONSE'];

    private static ?self $instance = null;

    private ?object $session = null;

    private ?string $sessionId = null;

    private bool $collecting = false;

    private bool $broken = false;

    /** @var array<string, array{method: string, url: string}> */
    private array $pending = [];

    /** @var array<string, true> requêtes déjà relevées (une réponse 4xx suivie d'un loadingFailed) */
    private array $reported = [];

    /** @var array<string, array{method: string, url: string, status: ?int, error: ?string, type: ?string, count: int}> */
    private array $requests = [];

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /** Début d'un test : liste vidée, écoute (re)branchée sur la page courante. */
    public function beginTest(?object $session): void
    {
        $this->requests = [];
        $this->reported = [];
        $this->collecting = true;
        $this->follow($session);
    }

    /**
     * Fin d'un test : les requêtes relevées, puis la collecte s'arrête jusqu'au test suivant.
     *
     * @return list<array{method: string, url: string, status: ?int, error: ?string, type: ?string, count: int}>
     */
    public function endTest(): array
    {
        $requests = $this->requests();
        $this->collecting = false;
        $this->requests = [];
        $this->reported = [];

        return $requests;
    }

    /**
     * Requêtes en échec du test en cours.
     *
     * @return list<array{method: string, url: string, status: ?int, error: ?string, type: ?string, count: int}>
     */
    public function requests(): array
    {
        if ($this->collecting) {
            SessionEvents::drain($this->session);
        }

        return array_values($this->requests);
    }

    /** Branche l'écoute sur une session (nouvelle page après recreatePage()). */
    public function follow(?object $session): void
    {
        if ($this->broken || $session === null) {
            return;
        }
        try {
            $id = (string) $session->getSessionId();
            if ($id === $this->sessionId) {
                return;
            }
            $this->session = $session;
            $this->sessionId = $id;
            $this->pending = [];
            $guard = function (callable $handler) use ($id): \Closure {
                return function (array $params) use ($handler, $id): void {
                    if ($id === $this->sessionId) {
                        $handler($params);
                    }
                };
            };
            $session->on('method:Network.requestWillBeSent', $guard($this->onRequest(...)));
            $session->on('method:Network.responseReceived', $guard($this->onResponse(...)));
            $session->on('method:Network.loadingFailed', $guard($this->onFailure(...)));
        } catch (Throwable) {
            $this->broken = true;
        }
    }

    /** @param array<string, mixed> $params */
    public function onRequest(array $params): void
    {
        $id = (string) ($params['requestId'] ?? '');
        $request = (array) ($params['request'] ?? []);
        if ($id === '' || ! isset($request['url'])) {
            return;
        }
        if (count($this->pending) >= self::MAX_PENDING) {
            array_shift($this->pending);
        }
        $this->pending[$id] = ['method' => (string) ($request['method'] ?? 'GET'), 'url' => (string) $request['url']];
    }

    /** @param array<string, mixed> $params */
    public function onResponse(array $params): void
    {
        $response = (array) ($params['response'] ?? []);
        $status = (int) ($response['status'] ?? 0);
        $id = (string) ($params['requestId'] ?? '');
        if ($status < 400) {
            unset($this->pending[$id]);

            return;
        }
        $url = (string) ($response['url'] ?? $this->pending[$id]['url'] ?? '');
        $method = $this->pending[$id]['method'] ?? (string) ($response['requestHeaders'][':method'] ?? 'GET');
        unset($this->pending[$id]);
        if ($id !== '') {
            $this->reported[$id] = true;
        }
        $this->add($method, $url, $status, null, $params['type'] ?? null);
    }

    /** @param array<string, mixed> $params */
    public function onFailure(array $params): void
    {
        $id = (string) ($params['requestId'] ?? '');
        $pending = $this->pending[$id] ?? null;
        unset($this->pending[$id]);
        $error = (string) ($params['errorText'] ?? '');
        if (isset($this->reported[$id]) || ! empty($params['canceled']) || isset($params['blockedReason']) || in_array($error, self::IGNORED_ERRORS, true)) {
            return;
        }
        if ($pending === null) {
            return;
        }
        $this->add($pending['method'], $pending['url'], null, $error !== '' ? $error : 'échec du chargement', $params['type'] ?? null);
    }

    private function add(string $method, string $url, ?int $status, ?string $error, mixed $type): void
    {
        if (! $this->collecting || $url === '' || self::ignoredUrl($url)) {
            return;
        }
        $url = mb_substr($url, 0, self::MAX_URL);
        $method = strtoupper($method !== '' ? $method : 'GET');
        $key = $method."\0".$url."\0".($status ?? $error);
        if (isset($this->requests[$key])) {
            $this->requests[$key]['count']++;

            return;
        }
        if (count($this->requests) >= self::MAX_PER_TEST) {
            return;
        }
        $this->requests[$key] = [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'error' => $error,
            'type' => is_string($type) && $type !== '' ? $type : null,
            'count' => 1,
        ];
    }

    private static function ignoredUrl(string $url): bool
    {
        if (preg_match('#^(data|blob|chrome-extension|about):#i', $url)) {
            return true;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);

        return (bool) preg_match('#/favicon\.(ico|png|svg)$#i', $path);
    }
}
