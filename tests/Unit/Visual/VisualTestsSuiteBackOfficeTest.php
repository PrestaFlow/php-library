<?php

namespace PrestaFlow\Tests\Unit\Visual;

use HeadlessChromium\Exception\CommunicationException\ResponseHasError;
use HeadlessChromium\Exception\OperationTimedOut;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\BackOfficeTimeoutException;
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
            public string $location = '';
            /** Horloge factice (ms) lue par la suite (nowMs()), avancée par les doubles. */
            public int $now = 0;
            /** Durée simulée de chaque lecture JS (≤ 5 s, comme une lecture réelle bornée). */
            public int $evalCostMs = 0;
            public function evaluate(string $js): object
            {
                $this->now += $this->evalCostMs;
                $value = match (true) {
                    str_contains($js, '#email') => $this->form,
                    str_contains($js, '.alert-danger') => $this->error,
                    str_contains($js, 'location.pathname') => $this->location,
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
            /** Horloge factice : l'onglet partagé ($this->chrome->now). */
            public ?object $clock = null;
            protected function nowMs(): int { return $this->clock?->now ?? 0; }
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
        $suite->clock = $this->chrome;
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
            /** Plafond de navigation posé par la suite (CommonPage::$navigationTimeout). */
            public ?int $navigationTimeout = null;
            /** Plafond vu à chaque navigation. */
            public array $navTimeouts = [];
            public ?\Throwable $navThrows = null;
            public ?\Throwable $menuThrows = null;
            /** Durée simulée de chaque navigation (avance l'horloge factice). */
            public int $navCostMs = 0;
            public function __construct(public \ArrayObject $log, public object $chrome) {}
            public function getPage(): object { return $this->chrome; }
            public function goToPage($page = null, $params = null): void
            {
                $this->log[] = 'page '.$page;
                $this->navTimeouts[] = $this->navigationTimeout;
                // Fidèle à chrome-php : au-delà du plafond, la navigation expire.
                if ($this->navigationTimeout !== null && $this->navCostMs > $this->navigationTimeout) {
                    $this->chrome->now += $this->navigationTimeout;
                    throw new OperationTimedOut('Operation timed out after '.$this->navigationTimeout.'ms');
                }
                $this->chrome->now += $this->navCostMs;
                if ($this->navThrows !== null) {
                    throw $this->navThrows;
                }
            }
            public function goToMenu(string $selectors): string
            {
                $this->log[] = 'menu '.$selectors;
                $this->navTimeouts[] = $this->navigationTimeout;
                // Fidèle à chrome-php : au-delà du plafond, la navigation expire.
                if ($this->navigationTimeout !== null && $this->navCostMs > $this->navigationTimeout) {
                    $this->chrome->now += $this->navigationTimeout;
                    throw new OperationTimedOut('Operation timed out after '.$this->navigationTimeout.'ms');
                }
                $this->chrome->now += $this->navCostMs;
                if ($this->menuThrows !== null) {
                    throw $this->menuThrows;
                }

                return 'http://shop.test/admin-dev/x?token=t';
            }
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
            /** Plafonds posés par la suite (CommonPage::$navigationTimeout, Login\Page::$loginOutcomeDeadline). */
            public ?int $navigationTimeout = null;
            public int $loginOutcomeTimeout = 60000;
            public ?int $loginOutcomeDeadline = null;
            public ?bool $loginOutcomeSeen = null;
            /** Issue vue par login() dans le plafond (false = délai). */
            public bool $outcome = true;
            public bool $logoutThrows = false;
            public ?\Throwable $navThrows = null;
            public array $navTimeouts = [];
            public array $outcomeTimeouts = [];
            /** Échéance de l'issue vue à chaque envoi du formulaire. */
            public array $outcomeDeadlines = [];
            public array $logoutTimeouts = [];
            /**
             * Durées simulées : navigation ; rechargement après l'envoi (10 s au plus,
             * comme CommonPage::waitForPageReload()) ; apparition de l'issue ;
             * constat de la session (isLoggedIn(), 5 s au plus).
             */
            public int $navCostMs = 0;
            public int $reloadCostMs = 0;
            public int $outcomeCostMs = 0;
            public int $checkCostMs = 0;
            public function __construct(public \ArrayObject $log, public bool $ok, public object $chrome) {}
            public function getPage(): object { return $this->chrome; }
            public function getSelector($selector, $replacements = []): string
            {
                return ['emailInput' => '#email', 'alertDangerDiv' => '.alert-danger'][$selector];
            }
            public function goToPage($page = null, $params = null): void
            {
                $this->log[] = 'login:page '.$page;
                $this->navTimeouts[] = $this->navigationTimeout;
                // Fidèle à chrome-php : au-delà du plafond, la navigation expire.
                if ($this->navigationTimeout !== null && $this->navCostMs > $this->navigationTimeout) {
                    $this->chrome->now += $this->navigationTimeout;
                    throw new OperationTimedOut('Operation timed out after '.$this->navigationTimeout.'ms');
                }
                $this->chrome->now += $this->navCostMs;
                if ($this->navThrows !== null) {
                    throw $this->navThrows;
                }
            }
            public function login($email = null, $password = null, $waitForNavigation = true): void
            {
                $this->log[] = 'login:submit';
                $this->outcomeDeadlines[] = $this->loginOutcomeDeadline;
                // Fidèle à Login\Page::login() : rechargement (10 s au plus), puis issue
                // plafonnée par le temps restant avant l'échéance (1 s au moins), ou par
                // $loginOutcomeTimeout sans échéance ; au-delà du plafond, l'issue n'est pas vue.
                $this->chrome->now += min($this->reloadCostMs, 10000);
                $ceiling = $this->loginOutcomeDeadline === null
                    ? $this->loginOutcomeTimeout
                    : max(1000, $this->loginOutcomeDeadline - $this->chrome->now);
                $this->outcomeTimeouts[] = $ceiling;
                $this->loginOutcomeSeen = $this->outcome && $this->outcomeCostMs <= $ceiling;
                $this->chrome->now += min($this->outcomeCostMs, $ceiling);
            }
            public function isLoggedIn(): bool
            {
                $this->log[] = 'login:check';
                $this->chrome->now += min($this->checkCostMs, 5000);

                return $this->ok;
            }
            public function logout(): void
            {
                $this->log[] = 'login:logout';
                $this->logoutTimeouts[] = $this->navigationTimeout;
                if ($this->logoutThrows) {
                    throw new \RuntimeException('Cannot log out: no logout link found at "#header_logout".');
                }
            }
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

    public function test_refused_login_reports_the_form_error_on_one_line(): void
    {
        // 9.2 : bouton de fermeture puis .alert-text, sur plusieurs lignes ; seule
        // la première ligne (« close ») apparaissait dans le rapport.
        $this->chrome->error = "\n  The employee does not exist,\n   or the password provided is incorrect.\n";
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log, ok: false), checkpoints: [['name' => 'dashboard']]);
        $s->init();

        $this->assertSame(
            ['capture visuelle : dashboard' => 'Connexion au back-office impossible : identifiants refusés ou page inattendue : The employee does not exist, or the password provided is incorrect.'],
            $this->runSteps($s)
        );
    }

    public function test_unexpected_page_reports_where_the_browser_is(): void
    {
        // Pas d'alerte : la connexion n'a pas été refusée, l'onglet est ailleurs.
        // Le chemin et le contrôleur suffisent au diagnostic, pas le jeton.
        $this->chrome->location = '/admin-dev/?controller=AdminDashboard';
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log, ok: false), checkpoints: [['name' => 'dashboard']]);
        $s->init();

        $this->assertSame(
            ['capture visuelle : dashboard' => 'Connexion au back-office impossible : identifiants refusés ou page inattendue (page : /admin-dev/?controller=AdminDashboard)'],
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

    public function test_open_checkpoint_logs_in_then_opens_the_dashboard_then_the_menu(): void
    {
        $this->chrome->location = '/admin-dev/index.php?controller=AdminProducts';
        $log = new \ArrayObject();
        $page = $this->page($log);
        $login = $this->login($log);
        $s = $this->suite(page: $page, login: $login);

        $where = $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts'], 25000, 15000);

        $this->assertSame([
            'login:page index', 'login:submit', 'login:check',
            'page index', 'menu #subtab-AdminProducts',
        ], $log->getArrayCopy());
        // Chemin et contrôleur, jamais le jeton.
        $this->assertSame('/admin-dev/index.php?controller=AdminProducts', $where);
        // Plafonds pendant l'ouverture, valeurs du run rétablies ensuite. Échéance de
        // l'issue : début + 25 s - 5 s de constat (isLoggedIn()) ; rechargement
        // instantané : toute l'attente revient à l'issue.
        $this->assertSame([20000], $login->outcomeDeadlines);
        $this->assertSame([20000], $login->outcomeTimeouts);
        $this->assertSame([15000], $login->navTimeouts);
        $this->assertSame([15000, 15000], $page->navTimeouts);
        $this->assertNull($page->navigationTimeout);
        $this->assertNull($login->navigationTimeout);
        $this->assertNull($login->loginOutcomeDeadline);
        $this->assertSame(60000, $login->loginOutcomeTimeout);
    }

    public function test_open_checkpoint_without_menu_stays_on_the_dashboard(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));

        $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => null]);

        $this->assertSame(['login:page index', 'login:submit', 'login:check', 'page index'], $log->getArrayCopy());
    }

    public function test_open_checkpoint_without_auth_opens_the_login_page_without_logging_in(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));

        $s->openBackOfficeCheckpoint(['name' => 'picker', 'auth' => false]);

        $this->assertSame(['page index'], $log->getArrayCopy());
    }

    public function test_open_checkpoint_without_auth_fails_when_a_session_is_open(): void
    {
        $this->chrome->form = false;
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session back-office déjà ouverte : la page de connexion ne peut pas être capturée');
        $s->openBackOfficeCheckpoint(['name' => 'picker', 'auth' => false]);
    }

    public function test_open_checkpoint_reports_refused_credentials_like_the_run(): void
    {
        $this->chrome->error = '  The employee does not exist.  ';
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log, ok: false));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts']);
            $this->fail('RuntimeException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->fail('Pas un délai : '.$e->getMessage());
        } catch (\RuntimeException $e) {
            $this->assertSame('Connexion au back-office impossible : identifiants refusés ou page inattendue : The employee does not exist.', $e->getMessage());
        }
        $this->assertNotContains('page index', $log->getArrayCopy());
    }

    public function test_open_checkpoint_reports_a_missing_menu_entry_like_the_run(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $page->menuThrows = new \RuntimeException('Entrée du menu introuvable : #subtab-Nope');
        $s = $this->suite(page: $page, login: $this->login($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-Nope']);
            $this->fail('RuntimeException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->fail('Pas un délai : '.$e->getMessage());
        } catch (\RuntimeException $e) {
            $this->assertSame('Entrée du menu introuvable : #subtab-Nope', $e->getMessage());
        }
        $this->assertNull($page->navigationTimeout);
    }

    public function test_open_checkpoint_turns_an_unseen_login_outcome_into_a_timeout(): void
    {
        $log = new \ArrayObject();
        $login = $this->login($log, ok: false);
        $login->outcome = false; // ni lien de déconnexion ni alerte dans le plafond
        $s = $this->suite(page: $this->page($log), login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e);
            $this->assertStringContainsString('à la connexion dans le délai imparti (25 s)', $e->getMessage());
        }
        $this->assertSame(60000, $login->loginOutcomeTimeout);
        $this->assertNull($login->loginOutcomeDeadline);
        $this->assertNull($login->navigationTimeout);
    }

    public function test_open_checkpoint_turns_a_navigation_timeout_into_a_timeout(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $page->navThrows = new OperationTimedOut('Operation timed out after 15s.');
        $s = $this->suite(page: $page, login: $this->login($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertStringContainsString('15 s', $e->getMessage());
            $this->assertInstanceOf(OperationTimedOut::class, $e->getPrevious());
        }
        $this->assertNull($page->navigationTimeout);
    }

    public function test_open_checkpoint_turns_a_login_page_timeout_into_a_timeout(): void
    {
        // Le délai survient dans ensureBackOfficeLogin(), qui le relève avec sa cause.
        $log = new \ArrayObject();
        $login = $this->login($log);
        $login->navThrows = new OperationTimedOut('Operation timed out after 15s.');
        $s = $this->suite(page: $this->page($log), login: $login);

        $this->expectException(BackOfficeTimeoutException::class);
        $this->expectExceptionMessage('15 s');
        $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);
    }

    public function test_open_checkpoint_is_refused_outside_the_back_office(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite('fo', page: $this->page($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker']);
            $this->fail('LogicException attendue');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('« bo »', $e->getMessage());
        }
        $this->assertSame([], $log->getArrayCopy());
    }

    public function test_open_checkpoint_refuses_a_login_page_with_a_menu(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'auth' => false, 'menu' => '#subtab-AdminOrders']);
            $this->fail('InvalidArgumentException attendue');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('menu interdit', $e->getMessage());
        }
        $this->assertSame([], $log->getArrayCopy());
    }

    public function test_close_session_logs_out_once_under_its_ceiling(): void
    {
        $log = new \ArrayObject();
        $login = $this->login($log);
        $s = $this->suite(page: $this->page($log), login: $login);
        $s->openBackOfficeCheckpoint(['name' => 'picker']);

        $s->closeBackOfficeSession(5000);
        $s->closeBackOfficeSession(5000);

        $this->assertSame(1, count(array_keys($log->getArrayCopy(), 'login:logout')));
        $this->assertSame([5000], $login->logoutTimeouts);
        $this->assertNull($login->navigationTimeout);
    }

    public function test_close_session_without_session_does_nothing(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));
        $s->closeBackOfficeSession();

        $s->openBackOfficeCheckpoint(['name' => 'picker', 'auth' => false]);
        $s->closeBackOfficeSession();

        $this->assertNotContains('login:logout', $log->getArrayCopy());
    }

    public function test_close_session_never_throws(): void
    {
        $log = new \ArrayObject();
        $login = $this->login($log);
        $login->logoutThrows = true;
        $s = $this->suite(page: $this->page($log), login: $login);
        $s->openBackOfficeCheckpoint(['name' => 'picker']);

        $s->closeBackOfficeSession();

        $this->assertContains('login:logout', $log->getArrayCopy());
        $this->assertNull($login->navigationTimeout);
    }

    public function test_open_checkpoint_keeps_a_minimal_login_outcome_wait_under_a_low_ceiling(): void
    {
        $log = new \ArrayObject();
        $login = $this->login($log);
        $s = $this->suite(page: $this->page($log), login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker'], 5000, 15000);

        // Échéance de l'issue = début + 5 s - 5 s de constat : 1 s d'attente au minimum.
        $this->assertSame([0], $login->outcomeDeadlines);
        $this->assertSame([1000], $login->outcomeTimeouts);
        $this->assertSame(60000, $login->loginOutcomeTimeout);
    }

    public function test_open_checkpoint_caps_each_step_to_what_is_left_of_the_global_budget(): void
    {
        // Échéance = 25 + 15 = 40 s après le début.
        $log = new \ArrayObject();
        $page = $this->page($log);
        $page->navCostMs = 3000;
        $login = $this->login($log);
        $login->navCostMs = 12000;    // page de connexion (plafond 15 s) : t = 12 s
        $login->reloadCostMs = 4000;  // t = 16 s
        $login->outcomeCostMs = 4000; // t = 20 s
        $login->checkCostMs = 5000;   // t = 25 s
        $s = $this->suite(page: $page, login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts'], 25000, 15000);

        $this->assertSame([15000], $login->navTimeouts);
        // Échéance de l'issue 20 s ; rechargement fini à 16 s : 4 s pour l'issue.
        $this->assertSame([4000], $login->outcomeTimeouts);
        // Tableau de bord : reste 15 s ; menu (t = 28 s) : reste 12 s.
        $this->assertSame([15000, 12000], $page->navTimeouts);
    }

    public function test_open_checkpoint_caps_the_login_outcome_to_what_is_left(): void
    {
        $log = new \ArrayObject();
        // Navigation de 15 s puis lecture du formulaire (2 s) : envoi à t = 17 s, à
        // 3 s de l'échéance de l'issue (25 - 5 = 20 s).
        $this->chrome->evalCostMs = 2000;
        $login = $this->login($log);
        $login->navCostMs = 15000;
        $s = $this->suite(page: $this->page($log), login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);

        $this->assertSame([20000], $login->outcomeDeadlines);
        $this->assertSame([3000], $login->outcomeTimeouts);
    }

    public function test_open_checkpoint_leaves_the_rest_of_the_budget_to_the_outcome_after_a_short_reload(): void
    {
        // Rechargement d'1 s : l'issue reçoit tout le reste avant son échéance (16 s),
        // et un premier tableau de bord de 14 s est vu (l'ancien calcul donnait 10 s).
        $log = new \ArrayObject();
        $login = $this->login($log);
        $login->navCostMs = 3000;
        $login->reloadCostMs = 1000;
        $login->outcomeCostMs = 14000;
        $s = $this->suite(page: $this->page($log), login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);

        $this->assertSame([16000], $login->outcomeTimeouts);
        $this->assertTrue($login->loginOutcomeSeen);
        $this->assertSame(['login:page index', 'login:submit', 'login:check', 'page index'], $log->getArrayCopy());
    }

    public function test_open_checkpoint_does_not_submit_the_login_with_less_than_the_check_and_one_second_left(): void
    {
        $log = new \ArrayObject();
        // Échéance 5 + 5 = 10 s ; page de connexion en 4,5 s : reste 5,5 s < 5 s de
        // constat + 1 s.
        $login = $this->login($log);
        $login->navCostMs = 4500;
        $s = $this->suite(page: $this->page($log), login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 5000, 5000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertSame("Le back-office n'a pas répondu dans le délai imparti (10 s au total).", $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $this->assertNotContains('login:submit', $log->getArrayCopy());
        $this->assertNull($login->loginOutcomeDeadline);
        $this->assertSame(60000, $login->loginOutcomeTimeout);
    }

    public function test_open_checkpoint_submits_the_login_once_the_check_and_one_second_are_left(): void
    {
        $log = new \ArrayObject();
        // Échéance 10 s ; page de connexion en 4 s : reste 6 s = constat + 1 s, envoi.
        $login = $this->login($log);
        $login->navCostMs = 4000;
        $s = $this->suite(page: $this->page($log), login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker'], 5000, 5000);

        $this->assertSame(['login:page index', 'login:submit', 'login:check', 'page index'], $log->getArrayCopy());
        $this->assertSame([1000], $login->outcomeTimeouts);
    }

    public function test_open_checkpoint_stops_before_a_step_once_the_deadline_is_reached(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $login = $this->login($log);
        // Pire cas : envoi à 6 s de l'échéance (10 s), rechargement au plafond de 10 s,
        // issue 1 s, constat 5 s : t = 20 s = échéance + 10 s (PAGE_RELOAD_MS).
        $login->navCostMs = 4000;
        $login->reloadCostMs = 10000;
        $login->outcomeCostMs = 1000;
        $login->checkCostMs = 5000;
        $s = $this->suite(page: $page, login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts'], 5000, 5000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertSame("Le back-office n'a pas répondu dans le délai imparti (10 s au total).", $e->getMessage());
        }
        $this->assertSame(20000, $this->chrome->now);
        $this->assertNotContains('page index', $log->getArrayCopy());
        $this->assertSame([], $page->navTimeouts);
        $this->assertNull($page->navigationTimeout);
    }

    public function test_open_checkpoint_restores_the_run_ceilings_even_on_error(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $page->navigationTimeout = 30000;
        $page->menuThrows = new \RuntimeException('Entrée du menu introuvable : #subtab-Nope');
        $login = $this->login($log);
        $login->navigationTimeout = 30000;
        $login->loginOutcomeTimeout = 45000;
        $login->loginOutcomeDeadline = 123;
        $s = $this->suite(page: $page, login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-Nope'], 25000, 15000);
            $this->fail('RuntimeException attendue');
        } catch (\RuntimeException) {
        }

        $this->assertSame([15000, 15000], $page->navTimeouts);
        $this->assertSame(30000, $page->navigationTimeout);
        $this->assertSame(30000, $login->navigationTimeout);
        $this->assertSame(45000, $login->loginOutcomeTimeout);
        // Échéance posée pour l'envoi (début + 25 - 5 s), celle d'avant rétablie ensuite.
        $this->assertSame([20000], $login->outcomeDeadlines);
        $this->assertSame(123, $login->loginOutcomeDeadline);
    }

    public function test_open_checkpoint_masks_the_token_of_a_failed_page_load(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $cause = new ResponseHasError('Cannot load page for url: "http://shop.test/admin-dev/index.php?controller=AdminProducts&token=abc123def". Reason: net::ERR_CONNECTION_RESET');
        $page->menuThrows = $cause;
        $s = $this->suite(page: $page, login: $this->login($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts']);
            $this->fail('RuntimeException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->fail('Pas un délai : '.$e->getMessage());
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('abc123def', $e->getMessage());
            $this->assertStringContainsString('controller=AdminProducts&token=…', $e->getMessage());
            $this->assertSame($cause, $e->getPrevious());
        }
    }

    public function test_open_checkpoint_masks_url_credentials_and_tokens_of_a_login_error(): void
    {
        $log = new \ArrayObject();
        $login = $this->login($log);
        $login->navThrows = new ResponseHasError('Cannot load page for url: "https://admin:s3cret@shop.test/admin-dev/index.php?_token=xyz789". Reason: net::ERR_ABORTED');
        $s = $this->suite(page: $this->page($log), login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker']);
            $this->fail('RuntimeException attendue');
        } catch (\RuntimeException $e) {
            $this->assertStringStartsWith('Connexion au back-office impossible : ', $e->getMessage());
            $this->assertStringNotContainsString('s3cret', $e->getMessage());
            $this->assertStringNotContainsString('xyz789', $e->getMessage());
            $this->assertStringContainsString('https://…@shop.test/admin-dev/index.php?_token=…', $e->getMessage());
            $this->assertNotNull($e->getPrevious());
        }
    }

    public function test_open_checkpoint_treats_a_timed_out_page_load_as_a_timeout(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $page->navThrows = new ResponseHasError('Cannot load page for url: "http://shop.test/admin-dev/?token=abc". Reason: net::ERR_TIMED_OUT');
        $s = $this->suite(page: $page, login: $this->login($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertStringContainsString('délai imparti', $e->getMessage());
            $this->assertInstanceOf(ResponseHasError::class, $e->getPrevious());
        }
    }

    public function test_open_checkpoint_accepts_a_login_seen_late_by_is_logged_in(): void
    {
        // Issue non vue dans le plafond, mais le lien de déconnexion est là ensuite.
        $log = new \ArrayObject();
        $login = $this->login($log, ok: true);
        $login->outcome = false;
        $s = $this->suite(page: $this->page($log), login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker']);

        $this->assertSame(['login:page index', 'login:submit', 'login:check', 'page index'], $log->getArrayCopy());
    }

    public function test_the_login_ceiling_hook_does_not_outlive_its_opening(): void
    {
        // Ouverture refusée par la closure (reste insuffisant à l'envoi), puis run
        // sur la même instance : le plafond du run (60 s) s'applique de nouveau.
        $login = $this->login($log = new \ArrayObject());
        $login->navCostMs = 4500;
        $page = $this->page($log);
        $s = $this->suite(page: $page, login: $login);
        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 5000, 5000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException) {
        }
        $log->exchangeArray([]);

        $s->init();
        $this->runSteps($s);

        $fresh = new \ArrayObject();
        $other = $this->suite(page: $this->page($fresh), login: $this->login($fresh));
        $other->init();
        $this->runSteps($other);

        $this->assertSame(60000, end($login->outcomeTimeouts));
        $this->assertNull(end($login->outcomeDeadlines));
        $this->assertSame($fresh->getArrayCopy(), $log->getArrayCopy());
    }

    public static function urlsToRedact(): array
    {
        return [
            'jeton' => ['http://shop.test/admin-dev/index.php?controller=AdminProducts&token=abc123', 'http://shop.test/admin-dev/index.php?controller=AdminProducts&token=…'],
            'jeton _token en tête' => ['http://shop.test/admin-dev/?_token=abc123', 'http://shop.test/admin-dev/?_token=…'],
            'jeton encodé %26' => ['http://shop.test/r?back=x%26token%3Dabc123', 'http://shop.test/r?back=x%26token%3D…'],
            'jeton encodé %3F' => ['http://shop.test/r?back=y%3F_token%3Dxyz789', 'http://shop.test/r?back=y%3F_token%3D…'],
            'jeton en entité HTML' => ['http://shop.test/a?controller=X&amp;token=html123', 'http://shop.test/a?controller=X&amp;token=…'],
            "guillemet simple conservé" => ["'http://shop.test/a?token=abc123'", "'http://shop.test/a?token=…'"],
            'identifiants' => ['https://admin:s3cret@shop.test/admin-dev/', 'https://…@shop.test/admin-dev/'],
            'arobase de requête, hôte gardé' => ['https://shop.test?email=a@b.com', 'https://shop.test?email=a@b.com'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('urlsToRedact')]
    public function test_open_checkpoint_redacts_relayed_urls(string $url, string $expected): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $page->menuThrows = new ResponseHasError('Cannot load page for url: "'.$url.'". Reason: net::ERR_ABORTED');
        $s = $this->suite(page: $page, login: $this->login($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts']);
            $this->fail('RuntimeException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->fail('Pas un délai : '.$e->getMessage());
        } catch (\RuntimeException $e) {
            $this->assertSame('Cannot load page for url: "'.$expected.'". Reason: net::ERR_ABORTED', $e->getMessage());
        }
    }
}
