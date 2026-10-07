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
        $browser = new class ($journal, $connection, $page) {
            public function __construct(private \ArrayObject $journal, private object $connection, private object $page) {}
            public function getConnection(): object
            {
                $this->journal[] = 'browser getConnection';

                return $this->connection;
            }
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
            'browser getConnection',
            'connection '.json_encode($basic),
            'session Network.enable',
            'page '.json_encode($basic),
            'browser getConnection',
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

    public function test_applying_twice_does_not_accumulate_headers(): void
    {
        $this->setFullEnvironment();
        [$browser, $page, $journal] = $this->doubles();
        TestsSuite::applyEnvironment($browser, $page);
        $firstHeaders = TestsSuite::$extraHttpHeaders;
        $firstConnection = $this->lastConnection($journal);

        TestsSuite::applyEnvironment($browser, $page);

        $this->assertSame($firstHeaders, TestsSuite::$extraHttpHeaders);
        $this->assertSame($firstConnection, $this->lastConnection($journal));
    }

    private function lastConnection(\ArrayObject $journal): ?string
    {
        $connections = array_values(array_filter($journal->getArrayCopy(), static fn (string $line) => str_starts_with($line, 'connection ')));

        return $connections === [] ? null : $connections[count($connections) - 1];
    }

    /**
     * Hors CLI (php-fpm : file `sync` de l'app), la constante STDERR n'existe pas.
     * Un PRESTAFLOW_EXTRA_HEADERS invalide ne doit pas lever « Undefined constant ».
     * Le SAPI CGI n'a pas STDERR non plus : on y exécute l'étape dans un sous-processus.
     */
    public function test_invalid_extra_headers_do_not_fail_outside_cli(): void
    {
        $cgi = dirname(PHP_BINARY).'/php-cgi';
        if (!is_executable($cgi)) {
            $this->markTestSkipped('php-cgi absent à côté de '.PHP_BINARY);
        }

        $autoload = dirname(__DIR__, 3).'/vendor/autoload.php';
        $base = tempnam(sys_get_temp_dir(), 'pf-env-cgi-');
        $script = $base.'.php';
        file_put_contents($script, '<?php
require '.var_export($autoload, true).';
$_ENV["PRESTAFLOW_BASIC_USER"] = "";
$_ENV["PRESTAFLOW_COOKIES"] = "";
$_ENV["PRESTAFLOW_EXTRA_HEADERS"] = "{pas du json";
echo defined("STDERR") ? "stderr-defini|" : "sans-stderr|";
\\PrestaFlow\\Library\\Tests\\TestsSuite::applyEnvironment(new stdClass(), new stdClass());
echo "ok";
');

        try {
            $output = (string) shell_exec(escapeshellarg($cgi).' -q -d display_errors=1 -d error_log=/dev/null '.escapeshellarg($script).' 2>&1');
        } finally {
            @unlink($script);
            @unlink($base);
        }

        $this->assertSame('sans-stderr|ok', trim($output));
    }

    /**
     * Avant navigation la page est sur about:blank : chrome-php y impose
     * domain = host de l'URL courante, soit null, et CDP refuse le cookie.
     */
    public function test_a_cookie_without_domain_takes_the_host_of_its_url(): void
    {
        $_ENV['PRESTAFLOW_COOKIES'] = '[{"name":"consent","value":"1","url":"https://preprod.shop.test:8443/fr/"}]';
        [$browser, $page, $journal] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page);

        $this->assertSame(['cookie consent=1 domain=preprod.shop.test path=/'], $journal->getArrayCopy());
    }

    public function test_a_cookie_without_domain_nor_url_takes_the_host_of_the_default_url(): void
    {
        $_ENV['PRESTAFLOW_COOKIES'] = '[{"name":"consent","value":"1"},{"name":"vu","value":"2","url":"https://autre.test/"}]';
        [$browser, $page, $journal] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page, 'https://shop.test/fr/');

        $this->assertSame([
            'cookie consent=1 domain=shop.test path=/',
            'cookie vu=2 domain=autre.test path=/',
        ], $journal->getArrayCopy());
    }

    public function test_an_explicit_cookie_domain_is_kept(): void
    {
        $_ENV['PRESTAFLOW_COOKIES'] = '[{"name":"consent","value":"1","domain":".shop.test","url":"https://preprod.shop.test/"}]';
        [$browser, $page, $journal] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page, 'https://autre.test/');

        $this->assertSame(['cookie consent=1 domain=.shop.test path=/'], $journal->getArrayCopy());
    }

    public function test_without_default_url_a_cookie_without_domain_nor_url_is_left_to_chrome_php(): void
    {
        $_ENV['PRESTAFLOW_COOKIES'] = '[{"name":"consent","value":"1"}]';
        [$browser, $page, $journal] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page);

        $this->assertSame(['cookie consent=1 domain= path=/'], $journal->getArrayCopy());
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
        // getBrowser() valide son cache par getConnection()->isConnected() à chaque appel :
        // ces lectures du navigateur partagé ne sont pas comparées, seuls les effets le sont.
        $effects = static fn (\ArrayObject $j) => array_values(array_filter($j->getArrayCopy(), static fn (string $line) => $line !== 'browser getConnection'));
        $this->assertSame(
            array_merge(['session Network.clearBrowserCookies'], $effects($expected)),
            $effects($journal)
        );
    }

    public function test_before_gives_no_default_domain_to_a_cookie_without_domain_nor_url(): void
    {
        $_ENV['PRESTAFLOW_COOKIES'] = '[{"name":"consent","value":"1"}]';
        [$shared, , $journal] = $this->doubles();
        TestsSuite::scopeBrowserFilesTo('apply-env-'.getmypid());
        $this->socketFile = TestsSuite::getFilePath('.browser');
        file_put_contents($this->socketFile, 'ws://127.0.0.1:1/devtools/browser/double');
        InjectedBrowserSuite::useBrowser($shared, 'ws://127.0.0.1:1/devtools/browser/double');

        (new InjectedBrowserSuite(loadGlobals: false, getBrowser: false))->before(headless: true);

        // Comportement du run inchangé : chrome-php reste seul à déduire le domaine.
        $this->assertContains('cookie consent=1 domain= path=/', $journal->getArrayCopy());
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
