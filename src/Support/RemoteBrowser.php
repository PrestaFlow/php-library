<?php

namespace PrestaFlow\Library\Support;

use HeadlessChromium\Browser;
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Communication\Socket\Wrench as WrenchSocket;
use HeadlessChromium\Exception\BrowserConnectionFailed;
use InvalidArgumentException;
use Wrench\Client as WrenchClient;

/**
 * Navigateur distant joint en WebSocket (ws:// ou wss://) : Cloudflare Browser
 * Run, Chrome en conteneur derrière un proxy…
 *
 * Point d'entrée unique : RemoteBrowser::connect(). Il comble trois manques de
 * BrowserFactory::connectToBrowser() :
 *  - les en-têtes de la poignée de main (Authorization du fournisseur), que
 *    chrome-php n'envoie pas ;
 *  - la lecture non bloquante (NonBlockingReadSocket), sans laquelle wss://
 *    ajoute des pauses de 5 s à une commande CDP sur deux ou trois ;
 *  - la taille et le user agent : un navigateur distant ignore les options de
 *    lancement windowSize / userAgent, on les pose donc sur chaque page.
 *
 * Les en-têtes (le jeton) ne sortent jamais dans un message d'erreur :
 * voir redact().
 */
class RemoteBrowser extends Browser
{
    /** Valeur masquée dans les messages. */
    public const MASK = '***';

    private ?string $pageUserAgent = null;

    /**
     * @param array<string,string> $headers en-têtes de la poignée de main WebSocket
     * @param array{0:int,1:int}|null $windowSize taille (viewport) posée sur chaque page
     * @param string|null $userAgent user agent posé sur chaque page
     * @param bool $nonBlockingReads false = socket Wrench d'origine (mesures, diagnostic)
     *
     * @throws InvalidArgumentException adresse qui n'est pas en ws:// ou wss://
     * @throws BrowserConnectionFailed navigateur injoignable ou poignée de main refusée
     */
    public static function connect(
        string $uri,
        #[\SensitiveParameter] array $headers = [],
        int $timeoutMs = 5000,
        ?array $windowSize = null,
        ?string $userAgent = null,
        bool $nonBlockingReads = true,
    ): self {
        $secrets = self::secretsOf($headers);
        $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));

        if (!in_array($scheme, ['ws', 'wss'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Navigateur distant : adresse « %s » refusée, ws:// ou wss:// attendu.',
                self::redact($uri, $secrets)
            ));
        }

        try {
            $options = $nonBlockingReads ? ['socket_class' => NonBlockingReadSocket::class] : [];
            $client = new WrenchClient($uri, 'http://127.0.0.1', $options);
            foreach ($headers as $name => $value) {
                $client->addRequestHeader((string) $name, (string) $value);
            }

            $connection = new Connection(new WrenchSocket($client), null, $timeoutMs);
            $connection->connect();

            if (!$connection->isConnected()) {
                throw new BrowserConnectionFailed('connexion refusée');
            }

            $browser = new self($connection);
        } catch (\Throwable $e) {
            // Pas d'exception chaînée : son message ou sa trace pourraient porter le jeton.
            throw new BrowserConnectionFailed(sprintf(
                'Navigateur distant injoignable (%s) : %s',
                self::redact($uri, $secrets),
                self::redact($e->getMessage() !== '' ? $e->getMessage() : get_class($e), $secrets)
            ));
        }

        if ($windowSize !== null) {
            $browser->setPageViewportSize([(int) $windowSize[0], (int) $windowSize[1]]);
        }
        $browser->pageUserAgent = ($userAgent !== null && $userAgent !== '') ? $userAgent : null;

        return $browser;
    }

    /**
     * Masque dans un texte : les valeurs secrètes données, l'identifiant d'une
     * URL (user:pass@) et les paramètres de requête sensibles (token, apiKey,
     * secret, signature…).
     *
     * @param list<string> $secrets
     */
    public static function redact(string $text, array $secrets = []): string
    {
        foreach ($secrets as $secret) {
            if (strlen($secret) >= 4) {
                $text = str_replace($secret, self::MASK, $text);
            }
        }

        $text = (string) preg_replace('#\b(wss?|https?)://[^/\s@?\#]+@#i', '$1://'.self::MASK.'@', $text);

        return (string) preg_replace(
            '/([?&][^=&\s#]*(?:token|key|secret|auth|pass|signature|sig)[^=&\s#]*=)[^&\s#]+/i',
            '$1'.self::MASK,
            $text
        );
    }

    /**
     * Valeurs à masquer : chaque en-tête, et le jeton nu d'un « Bearer xxx ».
     *
     * @param array<string,string> $headers
     *
     * @return list<string>
     */
    private static function secretsOf(#[\SensitiveParameter] array $headers): array
    {
        $secrets = [];
        foreach ($headers as $value) {
            $value = trim((string) $value);
            $secrets[] = $value;
            if (preg_match('/^\S+\s+(\S.*)$/', $value, $m)) {
                $secrets[] = $m[1];
            }
        }

        // Les plus longues d'abord : « Bearer abc » avant « abc ».
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $secrets;
    }

    public function getPageUserAgent(): ?string
    {
        return $this->pageUserAgent;
    }

    /** @return array{0:int,1:int}|null */
    public function getPageViewportSize(): ?array
    {
        return $this->pageViewportSize;
    }

    /**
     * Toute page vue pour la première fois (createPage(), getPages()) reçoit le
     * user agent ; la taille est posée par Browser::getPage() lui-même.
     */
    public function getPage($targetId)
    {
        $known = array_key_exists($targetId, $this->pages);
        $page = parent::getPage($targetId);

        if (!$known && $page !== null && $this->pageUserAgent !== null) {
            $page->setUserAgent($this->pageUserAgent)->await();
        }

        return $page;
    }

    /**
     * Ferme la session distante (Browser.close), et coupe la WebSocket même si
     * le fournisseur refuse Browser.close : sinon la session reste ouverte
     * (et facturée) jusqu'à son délai d'inactivité.
     */
    public function close(): void
    {
        try {
            parent::close();
        } finally {
            if ($this->connection->isConnected()) {
                try {
                    $this->connection->disconnect();
                } catch (\Throwable) {
                    // déjà coupée
                }
            }
        }
    }
}
