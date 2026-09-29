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

    /** Page factice : enregistre les appels à visualCheckpoint() sans navigateur. */
    private function recordingPage(): object
    {
        return new class {
            public array $calls = [];
            public function goToUrl(string $url): void {}
            public function waitForStable(): void {}
            public function waitVisible(string $s): void {}
            public function scrollBelow(string $s): void {}
            public function visualCheckpoint(string $name, ?string $selector = null, float $threshold = 0.98, bool $fullPage = true, string $tag = 'auto', array $masks = []): void
            {
                $this->calls[] = $name;
            }
        };
    }

    private function scopedSuite(object $page, string $scope = ''): VisualTestsSuite
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends VisualTestsSuite {
            public ?object $fakePage = null;
            protected array $checkpoints = [['name' => 'header', 'path' => ''], ['name' => 'footer', 'path' => 'x']];
            public function scopeTo(string $scope): static { $this->visualScope = $scope; return $this; }
            protected function importVisualPage(): void { $this->pages['frontOfficePage'] = $this->fakePage; }
        };
        $suite->fakePage = $page;
        if ($scope !== '') {
            $suite->scopeTo($scope);
        }
        $suite->setGlobals([
            'PS_VERSION' => '8.1.0', 'LOCALE' => 'fr', 'PREFIX_LOCALE' => false,
            'FO' => ['URL' => 'https://shop.test', 'EMAIL' => '', 'PASSWD' => ''],
            'BO' => ['URL' => '', 'EMAIL' => '', 'PASSWD' => ''],
        ]);

        return $suite;
    }

    public function test_scope_slug_is_kebab_case_of_short_class_name(): void
    {
        $this->assertSame('nouvelle-scene', VisualTestsSuite::slugify('NouvelleScene'));
        $this->assertSame('prod-visual2', VisualTestsSuite::slugify('ProdVisual2'));
        $this->assertSame('html-page', VisualTestsSuite::slugify('HTMLPage'));
        $this->assertSame('home', VisualTestsSuite::slugify('Home'));
    }

    public function test_default_visual_scope_derives_from_class_name(): void
    {
        $suite = new \PrestaFlow\Tests\Unit\Visual\Fixtures\ProdVisual2(loadGlobals: false, getBrowser: false);
        $this->assertSame('prod-visual2', $suite->visualScope());
    }

    public function test_anonymous_suite_scope_is_filename_safe(): void
    {
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $this->suite()->visualScope());
    }

    public function test_visual_scope_property_overrides_slug(): void
    {
        $this->assertSame('home-fr', $this->scopedSuite($this->recordingPage(), 'home-fr')->visualScope());
    }

    public function test_invalid_visual_scope_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->scopedSuite($this->recordingPage(), 'Bad/Scope')->visualScope();
    }

    public function test_init_passes_suite_scoped_name_but_keeps_unscoped_titles(): void
    {
        $page = $this->recordingPage();
        $suite = $this->scopedSuite($page, 'nouvelle-scene');
        $suite->init();
        $tests = array_values($suite->tests);

        $this->assertSame('capture visuelle : header', $tests[0]['title']);
        $this->assertSame('capture visuelle : footer', $tests[1]['title']);
        foreach ($tests as $t) {
            ($t['steps'])();
        }
        $this->assertSame(['nouvelle-scene.header', 'nouvelle-scene.footer'], $page->calls);
    }

    public function test_use_definition_overrides_literal_properties_for_init(): void
    {
        $page = $this->recordingPage();
        $suite = $this->scopedSuite($page, 'scene');
        $returned = $suite->useDefinition(['mobile'], ['en'], [['name' => 'hero', 'path' => 'home']]);

        $this->assertSame($suite, $returned);
        $this->assertSame(['mobile'], $suite->devices());
        $this->assertSame(['en'], $suite->locales());
        $this->assertSame('hero', $suite->checkpoints()[0]['name']);

        $suite->setGlobals(array_merge($suite->getGlobals(), ['DEVICE' => 'mobile', 'LOCALE' => 'en']));
        $suite->init();
        $tests = array_values($suite->tests);
        $this->assertCount(1, $tests);
        $this->assertSame('capture visuelle : hero', $tests[0]['title']);
        ($tests[0]['steps'])();
        $this->assertSame(['scene.hero'], $page->calls);
    }

    public function test_use_definition_rejects_device_not_in_new_definition(): void
    {
        $suite = $this->scopedSuite($this->recordingPage(), 'scene');
        $suite->useDefinition(['mobile'], [], [['name' => 'hero', 'path' => '']]);

        $suite->setGlobals(array_merge($suite->getGlobals(), ['DEVICE' => 'desktop']));

        $this->expectException(\InvalidArgumentException::class);
        $suite->init(); // desktop n'est plus déclaré après useDefinition()
    }
}
