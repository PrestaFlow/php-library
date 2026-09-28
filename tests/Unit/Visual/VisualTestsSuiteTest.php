<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Tests\VisualTestsSuite;

final class VisualTestsSuiteTest extends TestCase
{
    private function suite(array $globals = []): VisualTestsSuite
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends VisualTestsSuite {
            protected array $devices = ['desktop', 'mobile'];
            protected array $locales = ['fr', 'en'];
            protected array $checkpoints = [
                ['name' => 'login', 'path' => 'connexion', 'paths' => ['en' => 'login']],
                ['name' => 'footer', 'path' => '', 'zone' => 'element', 'selector' => '#footer', 'excludeDevices' => ['mobile']],
                ['name' => 'promos', 'path' => 'promotions', 'paths' => ['en' => false]],
            ];
            // pas de page réelle en test unitaire
            protected function importVisualPage(): void {}
        };
        $suite->setGlobals(array_merge([
            'PS_VERSION' => '8.1.0', 'LOCALE' => 'fr', 'PREFIX_LOCALE' => true,
            'FO' => ['URL' => 'https://shop.test', 'EMAIL' => '', 'PASSWD' => ''],
            'BO' => ['URL' => '', 'EMAIL' => '', 'PASSWD' => ''],
        ], $globals));

        return $suite;
    }

    private array $envBackup = [];
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->envBackup = $_ENV;
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        TestsSuite::useBrowserOptions(null, null, null);
        $_ENV = $this->envBackup;
        $_SERVER = $this->serverBackup;
    }

    /** Suite construite avec loadGlobals: true (chemin CLI), sans navigateur. */
    private function cliSuite(): VisualTestsSuite
    {
        return new class (loadGlobals: true, getBrowser: false) extends VisualTestsSuite {
            protected array $devices = ['desktop'];
            protected array $locales = ['fr', 'en'];
            protected array $checkpoints = [];
            protected function importVisualPage(): void {}
        };
    }

    /** Expose la logique de décision du preset (pas de navigateur réel). */
    private function presetProbe(): string
    {
        $probe = new class (loadGlobals: false, getBrowser: false) extends VisualTestsSuite {
            protected function importVisualPage(): void {}
            public static function apply(string $device): bool { return static::applyDevicePreset($device); }
        };

        return $probe::class;
    }

    public function test_resolve_path(): void
    {
        $s = $this->suite();
        $this->assertSame('connexion', $s->resolvePath(['path' => 'connexion', 'paths' => ['en' => 'login']], 'fr'));
        $this->assertSame('login', $s->resolvePath(['path' => 'connexion', 'paths' => ['en' => 'login']], 'en'));
        $this->assertNull($s->resolvePath(['path' => 'x', 'paths' => ['en' => false]], 'en'));
        $this->assertSame('x', $s->resolvePath(['path' => 'x', 'paths' => ['en' => '']], 'en'));
    }

    public function test_resolve_url(): void
    {
        $this->assertSame('https://shop.test/fr/connexion', $this->suite()->resolveUrl('connexion', 'fr'));
        $this->assertSame('https://shop.test/connexion', $this->suite(['PREFIX_LOCALE' => false])->resolveUrl('/connexion', 'fr'));
        $this->assertSame('https://shop.test/fr/', $this->suite()->resolveUrl('', 'fr'));
    }

    public function test_current_device_from_globals_then_default(): void
    {
        $this->assertSame('desktop', $this->suite()->currentDevice());
        $this->assertSame('mobile', $this->suite(['DEVICE' => 'mobile'])->currentDevice());
    }

    public function test_init_registers_tests_and_skips(): void
    {
        $s = $this->suite(['DEVICE' => 'mobile', 'LOCALE' => 'en']);
        $s->init();
        $tests = array_values($s->tests);

        $this->assertCount(3, $tests);
        $this->assertSame('capture visuelle : login', $tests[0]['title']);
        $this->assertArrayNotHasKey('skip', $tests[0]);
        $this->assertTrue($tests[1]['skip'] ?? false);   // footer exclu sur mobile
        $this->assertTrue($tests[2]['skip'] ?? false);   // promos sautée en EN
    }

    public function test_cli_locale_defaults_to_first_declared_locale(): void
    {
        // Défini mais vide, pas absent : loadGlobals() charge aussi les .env* du
        // dépôt de la lib (et non du seul répertoire courant), et un
        // PRESTAFLOW_LOCALE=en dans un .env.local local reviendrait par là.
        // Dotenv immutable ne réécrit pas une variable déjà définie.
        $_ENV['PRESTAFLOW_LOCALE'] = '';
        unset($_SERVER['PRESTAFLOW_LOCALE']);
        putenv('PRESTAFLOW_LOCALE');

        $this->assertSame('fr', $this->cliSuite()->currentLocale());
    }

    public function test_cli_locale_env_wins_over_declared_locales(): void
    {
        $_ENV['PRESTAFLOW_LOCALE'] = 'en';

        $this->assertSame('en', $this->cliSuite()->currentLocale());
    }

    public function test_init_rejects_undeclared_device(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tablet');
        $this->suite(['DEVICE' => 'tablet'])->init();
    }

    public function test_init_rejects_undeclared_locale(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('de');
        $this->suite(['LOCALE' => 'de'])->init();
    }

    public function test_default_title_is_short_class_name(): void
    {
        $s = $this->suite();
        $s->init();
        $this->assertStringNotContainsString('\\', $s->title);
        $this->assertSame(substr(strrchr('\\'.$s::class, '\\'), 1), $s->title);
    }

    public function test_normalize_clamps_threshold(): void
    {
        $this->assertSame(0.5, VisualTestsSuite::normalize(['name' => 'x', 'threshold' => 0.1])['threshold']);
        $this->assertSame(1.0, VisualTestsSuite::normalize(['name' => 'x', 'threshold' => 3])['threshold']);
        $this->assertSame(0.9, VisualTestsSuite::normalize(['name' => 'x', 'threshold' => '0.9'])['threshold']);
    }

    public function test_device_preset_applied_and_resets_browser_when_options_differ(): void
    {
        $probe = $this->presetProbe();
        $socket = TestsSuite::getFilePath('.browser');
        file_put_contents($socket, 'ws://127.0.0.1:1/devtools/browser/nope');

        $this->assertTrue($probe::apply('mobile'));
        $this->assertFileDoesNotExist($socket);
        $this->assertSame([390, 844], TestsSuite::browserOptions()['windowSize']);

        // même device : options déjà bonnes → pas de reset, socket réutilisable
        file_put_contents($socket, 'ws://127.0.0.1:1/devtools/browser/nope');
        $this->assertFalse($probe::apply('mobile'));
        $this->assertFileExists($socket);
        @unlink($socket);

        // device différent, preset posé par nous → on le remplace
        $this->assertTrue($probe::apply('tablet'));
        $this->assertSame([768, 1024], TestsSuite::browserOptions()['windowSize']);
    }

    public function test_device_preset_never_overrides_app_options(): void
    {
        $probe = $this->presetProbe();
        $probe::apply('mobile');

        // l'app pose ses propres options (matrice) → intouchables
        TestsSuite::useBrowserOptions(1280, 720, 'AppUA');
        $this->assertFalse($probe::apply('desktop'));
        $this->assertSame(['windowSize' => [1280, 720], 'userAgent' => 'AppUA'], TestsSuite::browserOptions());
    }

    public function test_normalize_defaults(): void
    {
        $cp = VisualTestsSuite::normalize(['name' => 'x', 'path' => '']);
        $this->assertSame('viewport', $cp['zone']);
        $this->assertSame(0.98, $cp['threshold']);
        $this->assertSame([], $cp['masks']);
        $this->assertSame([], $cp['excludeDevices']);
    }
}
