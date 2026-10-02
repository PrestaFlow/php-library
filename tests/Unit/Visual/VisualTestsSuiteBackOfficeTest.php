<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\VisualTestsSuite;

final class VisualTestsSuiteBackOfficeTest extends TestCase
{
    private array $envBackup = [];

    /** Onglet Chrome factice partagé par les pages : formulaire de connexion affiché ?, texte d'erreur. */
    private object $chrome;

    protected function setUp(): void
    {
        $this->chrome = new class {
            public bool $form = true;
            public string $error = '';
            public function evaluate(string $js): object
            {
                $value = match (true) {
                    str_contains($js, '#email') => $this->form,
                    str_contains($js, '.alert-danger') => $this->error,
                    default => null,
                };

                return new class ($value) {
                    public function __construct(private mixed $value) {}
                    public function getReturnValue(): mixed { return $this->value; }
                };
            }
        };
        $this->envBackup = $_ENV;
        unset($_ENV['PRESTAFLOW_VISUAL_ONLY']);
        putenv('PRESTAFLOW_VISUAL_ONLY');
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBackup;
    }

    private function suite(string $area = 'bo', ?bool $freeze = null, ?object $page = null, ?object $login = null, ?array $checkpoints = null): VisualTestsSuite
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends VisualTestsSuite {
            public ?object $fakePage = null;
            public ?object $fakeLogin = null;
            protected array $devices = ['desktop'];
            protected array $locales = ['en'];
            protected string $visualScope = 'backoffice';
            protected array $checkpoints = [
                ['name' => 'dashboard', 'menu' => '#subtab-AdminDashboard, #tab-AdminDashboard'],
                ['name' => 'login', 'auth' => false],
                ['name' => 'orders', 'menu' => '#subtab-AdminOrders', 'hide' => ['.popup']],
                ['name' => 'login-again', 'auth' => false],
            ];
            // pages factices, rangées sous les clés que produit importPage()
            protected function importVisualPage(): void
            {
                if ($this->fakePage !== null) {
                    $this->pages[$this->area === 'bo' ? 'backOfficePage' : 'frontOfficePage'] = $this->fakePage;
                }
                if ($this->fakeLogin !== null) {
                    $this->pages['backOfficeLoginPage'] = $this->fakeLogin;
                }
            }
            public function setArea(string $area, ?bool $freeze): void { $this->area = $area; $this->freezeTransitions = $freeze; }
        };
        $suite->setArea($area, $freeze);
        $suite->fakePage = $page;
        $suite->fakeLogin = $login;
        if ($checkpoints !== null) {
            $suite->useDefinition(['desktop'], ['en'], $checkpoints);
        }
        $suite->setGlobals(['PS_VERSION' => '9.2.0', 'LOCALE' => 'en', 'DEVICE' => 'desktop',
            'FO' => ['URL' => 'http://shop.test/', 'EMAIL' => '', 'PASSWD' => ''],
            'BO' => ['URL' => 'http://shop.test/admin-dev/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x']]);

        return $suite;
    }

    /** Page factice : journalise navigation et captures dans un journal partagé. */
    private function page(\ArrayObject $log): object
    {
        return new class ($log, $this->chrome) {
            public array $calls = [];
            public ?string $failOn = null;
            public function __construct(public \ArrayObject $log, public object $chrome) {}
            public function getPage(): object { return $this->chrome; }
            public function goToPage($page = null, $params = null): void { $this->log[] = 'page '.$page; }
            public function goToMenu(string $selectors): string { $this->log[] = 'menu '.$selectors; return 'http://shop.test/admin-dev/x?token=t'; }
            public function goToUrl(string $url): void { $this->log[] = 'goto '.$url; }
            public function waitForStable(): bool { $this->log[] = 'stable'; return true; }
            public function waitVisible(string $s): void {}
            public function scrollBelow(string $s): void { $this->log[] = 'scrollBelow '.$s; }
            public function scrollToTop(): void { $this->log[] = 'top'; }
            public function visualCheckpoint(string $name, ?string $selector = null, ?float $threshold = null, bool $fullPage = true, string $tag = 'auto', array $masks = [], ?int $maxDiffPixels = null, array $hide = [], bool $freezeTransitions = false): void
            {
                $this->log[] = 'capture '.$name;
                $this->calls[$name] = [$hide, $freezeTransitions];
                if ($this->failOn === $name) {
                    throw new \RuntimeException('capture ratée');
                }
            }
        };
    }

    private function login(\ArrayObject $log, bool $ok = true): object
    {
        // Ne navigue pas l'onglet partagé : la présence du formulaire est fixée par
        // le test ($this->chrome->form), comme l'afficherait la racine du BO pour la
        // session courante. Suffisant : la suite ne la lit qu'après une navigation.
        return new class ($log, $ok, $this->chrome) {
            public function __construct(public \ArrayObject $log, public bool $ok, public object $chrome) {}
            public function getPage(): object { return $this->chrome; }
            public function getSelector($selector, $replacements = []): string
            {
                return ['emailInput' => '#email', 'alertDangerDiv' => '.alert-danger'][$selector];
            }
            public function goToPage($page = null, $params = null): void { $this->log[] = 'login:page '.$page; }
            public function login($email = null, $password = null, $waitForNavigation = true): void { $this->log[] = 'login:submit'; }
            public function isLoggedIn(): bool { $this->log[] = 'login:check'; return $this->ok; }
        };
    }

    /** Exécute les étapes comme run() (closure liée à la sous-classe), en collectant les erreurs. */
    private function runSteps(VisualTestsSuite $suite): array
    {
        $errors = [];
        foreach (array_values($suite->tests) as $t) {
            try {
                $t['steps']->call($suite);
            } catch (\RuntimeException $e) {
                $errors[$t['title']] = $e->getMessage();
            }
        }

        return $errors;
    }

    public function test_normalize_adds_backoffice_defaults(): void
    {
        $cp = VisualTestsSuite::normalize(['name' => 'x']);

        $this->assertNull($cp['menu']);
        $this->assertTrue($cp['auth']);
        $this->assertSame([], $cp['hide']);
    }

    public function test_normalize_casts_auth_and_cleans_hide(): void
    {
        $cp = VisualTestsSuite::normalize(['name' => 'x', 'auth' => 0, 'hide' => ' .popup ']);

        $this->assertFalse($cp['auth']);
        $this->assertSame(['.popup'], $cp['hide']);
        $this->assertSame(['.a', '.b'], VisualTestsSuite::normalize(['name' => 'x', 'hide' => ['.a', '', ' ', '.b']])['hide']);
        $this->assertSame(['.a'], VisualTestsSuite::normalize(['name' => 'x', 'hide' => [['.nested'], '.a', 3]])['hide']);
    }

    public function test_area_accessor(): void
    {
        $this->assertSame('bo', $this->suite('bo')->area());
        $this->assertSame('fo', $this->suite('fo')->area());
    }

    public function test_logged_out_checkpoints_run_first_keeping_declared_order(): void
    {
        $names = array_column($this->suite()->orderedCheckpoints(), 'name');

        $this->assertSame(['login', 'login-again', 'dashboard', 'orders'], $names);
    }

    public function test_front_office_keeps_declared_order(): void
    {
        $names = array_column($this->suite('fo')->orderedCheckpoints(), 'name');

        $this->assertSame(['dashboard', 'login', 'orders', 'login-again'], $names);
    }

    public function test_transitions_are_frozen_by_default_in_the_back_office_only(): void
    {
        $this->assertTrue($this->suite('bo')->freezesTransitions());
        $this->assertFalse($this->suite('fo')->freezesTransitions());
        $this->assertFalse($this->suite('bo', false)->freezesTransitions());
        $this->assertTrue($this->suite('fo', true)->freezesTransitions());
    }

    public function test_init_registers_backoffice_checkpoints_in_run_order(): void
    {
        $s = $this->suite();
        $s->init();
        $tests = array_values($s->tests);

        $this->assertSame([
            'capture visuelle : login', 'capture visuelle : login-again',
            'capture visuelle : dashboard', 'capture visuelle : orders',
        ], array_column($tests, 'title'));
        foreach ($tests as $t) {
            $this->assertArrayNotHasKey('skip', $t);
        }
    }

    public function test_visual_only_filter_keeps_run_order(): void
    {
        $_ENV['PRESTAFLOW_VISUAL_ONLY'] = 'orders,login-again';
        $s = $this->suite();
        $s->init();

        $this->assertSame(
            ['capture visuelle : login-again', 'capture visuelle : orders'],
            array_column(array_values($s->tests), 'title')
        );
    }

    public function test_backoffice_ignores_paths_and_only_skips_excluded_devices(): void
    {
        $s = $this->suite(checkpoints: [
            ['name' => 'a', 'paths' => ['en' => false]],
            ['name' => 'b', 'excludeDevices' => ['desktop']],
        ]);
        $s->init();
        $tests = array_values($s->tests);

        $this->assertArrayNotHasKey('skip', $tests[0]);
        $this->assertTrue($tests[1]['skip'] ?? false);
    }

    public function test_unknown_area_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->suite('admin')->init();
    }

    public function test_steps_log_in_once_then_navigate_by_menu(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $s = $this->suite(page: $page, login: $this->login($log), checkpoints: [
            ['name' => 'dashboard'],
            ['name' => 'login', 'auth' => false],
            ['name' => 'orders', 'menu' => '#subtab-AdminOrders', 'hide' => ['.popup']],
            ['name' => 'orders-bis', 'menu' => '#subtab-AdminOrders'],
        ]);
        $s->init();

        $this->assertSame([], $this->runSteps($s));
        $this->assertSame([
            // non connecté : racine du BO = page de connexion
            'page index', 'stable', 'capture backoffice.login',
            // connexion unique, puis racine (tableau de bord)
            'login:page index', 'login:submit', 'login:check',
            'page index', 'stable', 'capture backoffice.dashboard',
            'page index', 'menu #subtab-AdminOrders', 'stable', 'capture backoffice.orders',
            // même (auth, menu) : pas de nouvelle navigation
            'stable', 'top', 'capture backoffice.orders-bis',
        ], $log->getArrayCopy());
        $this->assertSame([['.popup'], true], $page->calls['backoffice.orders']);
    }

    public function test_failed_login_fails_every_logged_in_checkpoint_without_retrying(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log, ok: false), checkpoints: [
            ['name' => 'dashboard'],
            ['name' => 'login', 'auth' => false],
            ['name' => 'orders', 'menu' => '#subtab-AdminOrders'],
        ]);
        $s->init();
        $errors = $this->runSteps($s);

        $this->assertSame(['capture visuelle : dashboard', 'capture visuelle : orders'], array_keys($errors));
        foreach ($errors as $message) {
            $this->assertStringStartsWith('Connexion au back-office impossible : ', $message);
        }
        $this->assertSame(1, count(array_keys($log->getArrayCopy(), 'login:submit')));
        $this->assertContains('capture backoffice.login', $log->getArrayCopy());
        $this->assertNotContains('capture backoffice.dashboard', $log->getArrayCopy());
    }

    public function test_front_office_passes_hide_and_freeze_to_the_capture(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $s = $this->suite('fo', page: $page, checkpoints: [
            ['name' => 'home', 'path' => ''],
            ['name' => 'popup', 'path' => '', 'hide' => ['.modal']],
        ]);
        $s->init();
        $this->runSteps($s);

        $this->assertSame(['goto http://shop.test/', 'stable', 'capture backoffice.home', 'stable', 'top', 'capture backoffice.popup'], $log->getArrayCopy());
        $this->assertSame([[], false], $page->calls['backoffice.home']);
        $this->assertSame([['.modal'], false], $page->calls['backoffice.popup']);
    }

    public function test_logged_out_checkpoint_with_a_menu_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('login-menu');
        $this->suite(checkpoints: [
            ['name' => 'dashboard'],
            ['name' => 'login-menu', 'auth' => false, 'menu' => '#subtab-AdminOrders'],
        ])->init();
    }

    public function test_surviving_session_skips_login_and_proceeds(): void
    {
        $this->chrome->form = false; // session BO déjà ouverte
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log), checkpoints: [
            ['name' => 'dashboard'],
            ['name' => 'orders', 'menu' => '#subtab-AdminOrders'],
        ]);
        $s->init();

        $this->assertSame([], $this->runSteps($s));
        $this->assertSame([
            'login:page index',
            'page index', 'stable', 'capture backoffice.dashboard',
            'page index', 'menu #subtab-AdminOrders', 'stable', 'capture backoffice.orders',
        ], $log->getArrayCopy());
    }

    public function test_logged_out_checkpoint_fails_when_a_session_is_open(): void
    {
        $this->chrome->form = false;
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log), checkpoints: [
            ['name' => 'login', 'auth' => false],
        ]);
        $s->init();

        $this->assertSame(
            ['capture visuelle : login' => 'Session back-office déjà ouverte : la page de connexion ne peut pas être capturée'],
            $this->runSteps($s)
        );
        $this->assertNotContains('capture backoffice.login', $log->getArrayCopy());
    }

    public function test_refused_login_reports_the_form_error(): void
    {
        $this->chrome->error = '  The employee does not exist.  ';
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log, ok: false), checkpoints: [['name' => 'dashboard']]);
        $s->init();

        $this->assertSame(
            ['capture visuelle : dashboard' => 'Connexion au back-office impossible : identifiants refusés ou page inattendue : The employee does not exist.'],
            $this->runSteps($s)
        );
    }

    public function test_failed_checkpoint_forces_navigation_on_next_one_with_same_target(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $page->failOn = 'backoffice.orders';
        $s = $this->suite(page: $page, login: $this->login($log), checkpoints: [
            ['name' => 'orders', 'menu' => '#subtab-AdminOrders'],
            ['name' => 'orders-bis', 'menu' => '#subtab-AdminOrders'],
        ]);
        $s->init();
        $this->runSteps($s);

        $this->assertSame([
            'login:page index', 'login:submit', 'login:check',
            'page index', 'menu #subtab-AdminOrders', 'stable', 'capture backoffice.orders',
            'page index', 'menu #subtab-AdminOrders', 'stable', 'capture backoffice.orders-bis',
        ], $log->getArrayCopy());
    }
}
