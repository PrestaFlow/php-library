<?php

namespace PrestaFlow\Library\Support;

use Throwable;

/**
 * Erreurs JavaScript de la page pendant un test : exceptions non rattrapées
 * (Runtime.exceptionThrown) et appels console.error (Runtime.consoleAPICalled).
 *
 * Typiquement un module de paiement ou de livraison qui casse le JS du checkout sans
 * que rien ne change à l'écran. Les erreurs sont jointes au test (`js_errors`) sans le
 * faire échouer ; un test qui doit être strict les vérifie avec `$page->jsErrors()`.
 *
 * chrome-php active déjà le domaine Runtime sur chaque page. Les événements ne sont lus
 * qu'en lisant le socket : les appels synchrones des tests suffisent pendant l'activité,
 * drain() vide la file avant de lire la liste.
 *
 * La collecte ne doit jamais faire échouer un run : toute erreur la coupe, sans exception.
 * Les iframes d'un autre domaine (champs carte Stripe, PayPal…) sont des cibles à part :
 * leurs erreurs ne sont pas vues ici.
 */
class ConsoleErrors
{
    /** Erreurs distinctes gardées par test (les répétitions sont comptées). */
    public const MAX_PER_TEST = 20;

    private const MAX_MESSAGE = 500;

    private static ?self $instance = null;

    private ?object $session = null;

    private ?string $sessionId = null;

    private bool $collecting = false;

    private bool $broken = false;

    /** @var array<string, array{message: string, url: ?string, line: ?int, count: int}> */
    private array $errors = [];

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
        $this->errors = [];
        $this->collecting = true;
        $this->follow($session);
    }

    /**
     * Fin d'un test : les erreurs relevées, puis la collecte s'arrête jusqu'au test suivant.
     *
     * @return list<array{message: string, url: ?string, line: ?int, count: int}>
     */
    public function endTest(): array
    {
        $errors = $this->errors();
        $this->collecting = false;
        $this->errors = [];

        return $errors;
    }

    /**
     * Erreurs du test en cours.
     *
     * @return list<array{message: string, url: ?string, line: ?int, count: int}>
     */
    public function errors(): array
    {
        if ($this->collecting) {
            $this->drain();
        }

        return array_values($this->errors);
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
            $session->on('method:Runtime.exceptionThrown', function (array $params) use ($id): void {
                if ($id === $this->sessionId) {
                    $this->onException($params);
                }
            });
            $session->on('method:Runtime.consoleAPICalled', function (array $params) use ($id): void {
                if ($id === $this->sessionId) {
                    $this->onConsole($params);
                }
            });
        } catch (Throwable) {
            $this->broken = true;
        }
    }

    /** @param array<string, mixed> $params */
    public function onException(array $params): void
    {
        $details = (array) ($params['exceptionDetails'] ?? []);
        $exception = (array) ($details['exception'] ?? []);
        // description porte « TypeError: x is undefined » et la pile ; text seul vaut souvent « Uncaught ».
        $message = (string) ($exception['description'] ?? $exception['value'] ?? '');
        $message = $message !== '' ? strtok($message, "\n") : (string) ($details['text'] ?? '');
        $this->add($message, $details['url'] ?? self::topFrame($details)['url'] ?? null, $details['lineNumber'] ?? null);
    }

    /** @param array<string, mixed> $params */
    public function onConsole(array $params): void
    {
        if (($params['type'] ?? null) !== 'error') {
            return;
        }
        $parts = [];
        foreach ((array) ($params['args'] ?? []) as $arg) {
            $arg = (array) $arg;
            $parts[] = array_key_exists('value', $arg) && is_scalar($arg['value'])
                ? (string) $arg['value']
                : (string) ($arg['description'] ?? $arg['type'] ?? '');
        }
        $frame = self::topFrame($params);
        $this->add(trim(implode(' ', $parts)), $frame['url'] ?? null, $frame['lineNumber'] ?? null);
    }

    private function add(string $message, mixed $url, mixed $line): void
    {
        if (! $this->collecting || trim($message) === '') {
            return;
        }
        $message = mb_substr(trim($message), 0, self::MAX_MESSAGE);
        $url = is_string($url) && $url !== '' ? $url : null;
        // Chrome compte les lignes à partir de 0.
        $line = is_numeric($line) ? (int) $line + 1 : null;
        $key = $message."\0".$url."\0".$line;
        if (isset($this->errors[$key])) {
            $this->errors[$key]['count']++;

            return;
        }
        if (count($this->errors) >= self::MAX_PER_TEST) {
            return;
        }
        $this->errors[$key] = ['message' => $message, 'url' => $url, 'line' => $line, 'count' => 1];
    }

    /**
     * @param  array<string, mixed>  $holder
     * @return array<string, mixed>
     */
    private static function topFrame(array $holder): array
    {
        $frames = (array) (((array) ($holder['stackTrace'] ?? []))['callFrames'] ?? []);

        return (array) ($frames[0] ?? []);
    }

    /** Lit les événements en attente sur le socket (au plus 100 ms). */
    private function drain(): void
    {
        if (! $this->broken) {
            SessionEvents::drain($this->session);
        }
    }
}
