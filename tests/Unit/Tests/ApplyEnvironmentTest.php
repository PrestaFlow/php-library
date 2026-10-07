<?php

namespace PrestaFlow\Tests\Unit\Tests;

use HeadlessChromium\Communication\Message;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Environnement du run (Basic Auth, PRESTAFLOW_EXTRA_HEADERS, PRESTAFLOW_COOKIES)
 * appliqué à un navigateur et une page donnés. Aucun Chrome : doubles qui
 * reprennent les signatures de chrome-php (Connection::setConnectionHttpHeaders,
 * Session::sendMessageSync, Page::setExtraHTTPHeaders, Page::setCookies()->await())
 * et notent chaque appel dans un journal.
 */
final class ApplyEnvironmentTest extends TestCase
{
    private const KEYS = ['PRESTAFLOW_BASIC_USER', 'PRESTAFLOW_BASIC_PASS', 'PRESTAFLOW_EXTRA_HEADERS', 'PRESTAFLOW_COOKIES'];

    private array $envBackup = [];
    private array $headersBackup = [];
    private ?string $socketFile = null;

    protected function setUp(): void
    {
        foreach (self::KEYS as $key) {
            $this->envBackup[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
            // Chaîne vide = variable absente pour les trois étapes, même si le shell la définit.
            $_ENV[$key] = '';
        }
        $this->headersBackup = TestsSuite::$extraHttpHeaders;
        TestsSuite::$extraHttpHeaders = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        TestsSuite::$extraHttpHeaders = $this->headersBackup;
        if ($this->socketFile !== null) {
            InjectedBrowserSuite::forgetBrowser();
            @unlink($this->socketFile);
            TestsSuite::scopeBrowserFilesTo(null);
        }
    }

    /** @return array{0: object, 1: object, 2: \ArrayObject} navigateur, page, journal */
    private function doubles(): array
    {
        $journal = new \ArrayObject();

        $connection = new class ($journal) {
            public function __construct(private \ArrayObject $journal) {}
            public function isConnected(): bool { return true; }
            public function setConnectionHttpHeaders(array $headers): void
            {
                $this->journal[] = 'connection '.json_encode($headers);
            }
        };
        $session = new class ($journal) {
            public function __construct(private \ArrayObject $journal) {}
            public function sendMessageSync(Message $message, ?int $timeout = null): object
            {
                $this->journal[] = 'session '.$message->getMethod();

                return new \stdClass();
            }
        };
        $page = new class ($journal, $session) {
            public function __construct(private \ArrayObject $journal, private object $session) {}
            public function getSession(): object { return $this->session; }
            public function setExtraHTTPHeaders(array $headers = []): void
            {
                $this->journal[] = 'page '.json_encode($headers);
            }
            public function setCookies($cookies): object
            {
                foreach ($cookies as $cookie) {
                    $this->journal[] = sprintf('cookie %s=%s domain=%s path=%s', $cookie->getName(), $cookie->getValue(), (string) $cookie->offsetGet('domain'), (string) $cookie->offsetGet('path'));
                }

                return new class {
                    public function await(?int $time = null): self { return $this; }
                };
            }
        };
        $browser = new class ($connection, $page) {
            public function __construct(private object $connection, private object $page) {}
            public function getConnection(): object { return $this->connection; }
            public function getPages(): array { return [$this->page]; }
            public function createPage(): object { return $this->page; }
            public function close(): void {}
        };

        return [$browser, $page, $journal];
    }

    private function setFullEnvironment(): void
    {
        $_ENV['PRESTAFLOW_BASIC_USER'] = 'admin';
        $_ENV['PRESTAFLOW_BASIC_PASS'] = 's3cret';
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{"X-CI-Bypass":"k"}';
        $_ENV['PRESTAFLOW_COOKIES'] = '[{"name":"consent","value":"1","domain":"shop.test"},{"value":"sans nom"}]';
    }

    public function test_basic_auth_then_extra_headers_then_cookies(): void
    {
        $this->setFullEnvironment();
        [$browser, $page, $journal] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page);

        $basic = ['Authorization' => 'Basic YWRtaW46czNjcmV0'];
        $all = $basic + ['X-CI-Bypass' => 'k'];
        $this->assertSame([
            'connection '.json_encode($basic),
            'session Network.enable',
            'page '.json_encode($basic),
            'connection '.json_encode($all),
            'session Network.enable',
            'page '.json_encode($all),
            'cookie consent=1 domain=shop.test path=/',
        ], $journal->getArrayCopy());
        $this->assertSame($all, TestsSuite::$extraHttpHeaders);
    }

    public function test_extra_headers_may_override_basic_auth(): void
    {
        $_ENV['PRESTAFLOW_BASIC_USER'] = 'admin';
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{"Authorization":"Bearer t"}';
        [$browser, $page] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page);

        $this->assertSame(['Authorization' => 'Bearer t'], TestsSuite::$extraHttpHeaders);
    }

    public function test_nothing_is_applied_without_environment(): void
    {
        [$browser, $page, $journal] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page);

        $this->assertSame([], $journal->getArrayCopy());
        $this->assertSame([], TestsSuite::$extraHttpHeaders);
    }

    public function test_clear_environment_forgets_the_headers(): void
    {
        $this->setFullEnvironment();
        [$browser, $page] = $this->doubles();
        TestsSuite::applyEnvironment($browser, $page);

        TestsSuite::clearEnvironment();

        $this->assertSame([], TestsSuite::$extraHttpHeaders);
    }

    public function test_before_applies_the_same_environment_as_apply_environment(): void
    {
        $this->setFullEnvironment();
        [$browser, $page, $expected] = $this->doubles();
        TestsSuite::applyEnvironment($browser, $page);
        TestsSuite::$extraHttpHeaders = [];

        // before() trouve le navigateur partagé par son fichier socket et son cache :
        // on y range le double, sans lancer Chrome.
        [$shared, , $journal] = $this->doubles();
        TestsSuite::scopeBrowserFilesTo('apply-env-'.getmypid());
        $this->socketFile = TestsSuite::getFilePath('.browser');
        file_put_contents($this->socketFile, 'ws://127.0.0.1:1/devtools/browser/double');
        InjectedBrowserSuite::useBrowser($shared, 'ws://127.0.0.1:1/devtools/browser/double');

        (new InjectedBrowserSuite(loadGlobals: false, getBrowser: false))->before(headless: true);

        // before() vide d'abord les cookies de la suite précédente, puis applique l'environnement.
        $this->assertSame(
            array_merge(['session Network.clearBrowserCookies'], $expected->getArrayCopy()),
            $journal->getArrayCopy()
        );
    }
}

/** Suite qui range un navigateur double dans le cache de TestsSuite::getBrowser(). */
final class InjectedBrowserSuite extends TestsSuite
{
    public static function useBrowser(object $browser, string $socket): void
    {
        static::$browserInstance = $browser;
        static::$browserInstanceSocket = $socket;
    }

    public static function forgetBrowser(): void
    {
        static::$browserInstance = null;
        static::$browserInstanceSocket = null;
    }
}
