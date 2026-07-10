<?php

namespace PrestaFlow\Library\Tests;

use Closure;
use Dotenv\Dotenv;
use Error;
use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Cookies\Cookie;
use HeadlessChromium\Cookies\CookiesCollection;
use HeadlessChromium\Exception\BrowserConnectionFailed;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Exception\TargetDestroyed;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Traits\ImportPage;
use PrestaFlow\Library\Traits\Locale;
use PrestaFlow\Library\Traits\Version;
use PrestaFlow\Library\Utils\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Throwable;
use UnexpectedValueException;

class TestsSuite
{
    use Locale;
    use Version;
    use ImportPage;
    use Output;

    public string $title = '';
    public array $tests = [];
    protected array $stats = [
        'passes' => 0,
        'failures' => 0,
        'skips' => 0,
        'skippeds' => 0,
        'todos' => 0,
        'assertions' => 0,
        'time' => 0,
    ];

    public $warnings = [];
    public $screens = [];

    protected $suite = null;

    protected $start_time;
    protected $end_time;

    protected $init = false;
    protected $failed = false;
    protected $skipWhenFailed = true;

    public $globals = [];
    public $pages = [];
    public $params = [];

    public $customs = [
        'selectors' => [],
        'messages' => [],
        'urls' => [],
    ];

    protected $dataset = [];
    protected $datasets = [];

    protected array $store = [];

    protected $scenarioName = '';
    protected $scenarioParams = [];

    protected static $lines = [];

    protected static array $pendingDebugMessages = [];

    /** Résultats de régression visuelle du run courant (alimentés par CommonPage::visualCheckpoint). */
    public static array $visualResults = [];

    public static function recordVisualResult(array $result): void
    {
        self::$visualResults[] = $result;
    }

    /**
     * En-têtes HTTP à (ré)appliquer sur CHAQUE page, y compris celles recréées par
     * goToPage (qui ferme puis recrée la page). Alimenté par presetBasicAuth().
     */
    public static array $extraHttpHeaders = [];

    protected $draft = false;
    protected $groups = 'all';

    public function __construct(bool $loadGlobals = true, bool $getBrowser = true)
    {
        if ($loadGlobals) {
            $this->loadGlobals();
        }

        $this->before(getBrowser: $getBrowser);
    }

    public function getParam($paramName)
    {
        return $this->scenarioParams[$this->scenarioName][$paramName] ?? $this->dataset[$paramName] ?? null;
    }

    public function store(string $key, mixed $value): static
    {
        $this->store[$key] = $value;

        return $this;
    }

    public function retrieve(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function describe(string $description)
    {
        $this->title = $description;

        return $this;
    }

    public function getStats() : array
    {
        return $this->stats;
    }

    public function getSuite() : string
    {
        return $this->suite;
    }

    public function getDescribe() : string
    {
        return $this->title;
    }

    public function with(array $datasets = [])
    {
        $this->datasets = $datasets;

        return $this;
    }

    public function getDatasets() : array
    {
        return $this->datasets;
    }

    public function scenario($class, array $params = [])
    {
        $scenario = new $class($this, $params);

        $this->scenarioParams[get_class($scenario)] = $scenario->params;

        return $this;
    }

    public function it(string $description, Closure $steps)
    {
        $reflection = new \ReflectionFunction($steps);
        $this->tests[] = [
            'title' => $description,
            'steps' => $steps,
            'datasets' => $this->datasets,
            'file' => $reflection->getFileName(),
            'line' => $reflection->getStartLine(),
        ];

        return $this;
    }

    public function skip(string $description, Closure $steps)
    {
        $reflection = new \ReflectionFunction($steps);
        $this->tests[] = [
            'title' => $description,
            'steps' => $steps,
            'skip' => true,
            'file' => $reflection->getFileName(),
            'line' => $reflection->getStartLine(),
        ];

        return $this;
    }

    public function todo(string $description, Closure $steps)
    {
        $reflection = new \ReflectionFunction($steps);
        $this->tests[] = [
            'title' => $description,
            'steps' => $steps,
            'todo' => true,
            'file' => $reflection->getFileName(),
            'line' => $reflection->getStartLine(),
        ];

        return $this;
    }

    public function isSkippable($test)
    {
        if (isset($test['skip']) && $test['skip']) {
            return true;
        }

        return false;
    }

    public function isSkipWhenFailed() : bool
    {
        return $this->skipWhenFailed;
    }

    public function isSkippableCauseFailed($test)
    {
        if ($this->failed && $this->isSkipWhenFailed()) {
            return true;
        }

        return false;
    }

    public function skipWhenFailed(bool $skipWhenFailed = true)
    {
        $this->skipWhenFailed = $skipWhenFailed;
        return $this;
    }

    public function isTodoable($test)
    {
        if (isset($test['todo']) && $test['todo']) {
            return true;
        }

        return false;
    }

    public function isDraft() : bool
    {
        return (bool) $this->draft;
    }

    public function getGroups() : string|array
    {
        return $this->groups;
    }

    public static function getFilePath($filename = '.broswer')
    {
        if (function_exists('storage_path')) {
            $filePath = storage_path().'/datas/'.$filename;
        } else {
            $filePath = __DIR__.'/../../datas/'.$filename;
        }

        return $filePath;
    }


    public static function _getBrowser(bool $headless = true, bool $force = true, array $globals = [])
    {
        $browser = null;

        $windowWidth = (int)$globals['BROWSER']['WINDOW_SIZE_WIDTH'] ?? 1920;
        $windowHeight = (int) $globals['BROWSER']['WINDOW_SIZE_HEIGHT'] ?? 1000;

        if ($windowWidth <= 0) {
            $windowWidth = 1920;
        }
        if ($windowHeight <= 0) {
            $windowHeight = 1000;
        }

        $browserOptions = [
            'userAgent' => $globals['BROWSER']['USER_AGENT'] ?? 'PrestaFlow',
            'keepAlive' => true,
            'windowSize' => [$windowWidth, $windowHeight],
            'headless' => (bool) $headless,
        ];

        $browserOptionsFile = TestsSuite::getFilePath('.broswer-options');
        $socketFile = TestsSuite::getFilePath('.broswer');

        $socket = null;
        if (file_exists($browserOptionsFile)) {
            $browserOptionsDatas = \file_get_contents($browserOptionsFile);
            $savedBrowserOptions = \json_decode($browserOptionsDatas, true);

            if (is_array($savedBrowserOptions)
                && count($browserOptions) == count($savedBrowserOptions)
                && array_diff($browserOptions, $savedBrowserOptions) === array_diff($savedBrowserOptions, $browserOptions)) {
                if (file_exists($socketFile)) {
                    $socket = \file_get_contents($socketFile);

                    if (!strlen($socket)) {
                        $socket = null;
                    }
                }
            } else {
                // options have changed, remove socket file to force new browser creation
                if (file_exists($socketFile)) {
                    unlink($socketFile);
                }
            }
        }

        try {
            if ($socket === null) {
                if (!$force) {
                    return null;
                }
                throw new BrowserConnectionFailed('');
            }
            $browser = BrowserFactory::connectToBrowser($socket);
        } catch (BrowserConnectionFailed | OperationTimedOut $e) {
            if (!$force) {
                return null;
            }

            $browserFactory = new BrowserFactory();
            $browser = $browserFactory->createBrowser($browserOptions);

            \file_put_contents($browserOptionsFile, \json_encode($browserOptions));
            \file_put_contents($socketFile, $browser->getSocketUri());
        }

        return $browser;
    }

    public static function getSocketFilePath()
    {
        if (function_exists('storage_path')) {
            $socketFilePath = storage_path().'/datas/.broswer';
        } else {
            $socketFilePath = __DIR__.'/../../datas/.broswer';
        }

        return $socketFilePath;
    }

    public static function getBrowser(bool $headless = true, bool $force = true)
    {
        $browser = null;

        $socketFile = TestsSuite::getFilePath('.broswer');

        $socket = null;
        if (file_exists($socketFile)) {
            $socket = \file_get_contents($socketFile);

            if (!strlen($socket)) {
                $socket = null;
            }
        }

        try {
            if ($socket === null) {
                if (!$force) {
                    return null;
                }
                throw new BrowserConnectionFailed('');
            }
            $browser = BrowserFactory::connectToBrowser($socket);
        } catch (BrowserConnectionFailed | OperationTimedOut $e) {
            if (!$force) {
                return null;
            }

            $options = [
                'userAgent' => $_ENV['PRESTAFLOW_USER_AGENT'] ?? 'PrestaFlow',
                'keepAlive' => true,
                'windowSize' => [1920, 1000],
                'headless' => (bool) $headless,
                'ignoreCertificateErrors' => true,
            ];

            // Le lancement d'un navigateur « froid » échoue parfois au handshake
            // CDP (« Message could not be sent. Reason: the connection is closed »).
            // On réessaie quelques fois : sinon toute la suite ne tourne pas (et,
            // sans rapport JUnit produit, le job peut passer au vert à tort).
            $browser = null;
            $lastError = null;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    $browser = (new BrowserFactory())->createBrowser($options);
                    \file_put_contents($socketFile, $browser->getSocketUri());
                    $lastError = null;
                    break;
                } catch (\Throwable $e2) {
                    $lastError = $e2;
                    usleep(700000);
                }
            }

            if ($lastError !== null) {
                throw $lastError;
            }
        }

        return $browser;
    }

    public static function getPage()
    {
        // L'énumération des targets (getPages) peut floter au démarrage à froid,
        // pendant qu'une page se ferme/crée : « Call to a member function
        // getTargetInfo() on null ». On réessaie quelques fois avant d'échouer.
        for ($try = 1; ; $try++) {
            try {
                $pages = TestsSuite::getBrowser()?->getPages();
                if (count($pages) == 0) {
                    TestsSuite::getBrowser()?->createPage();
                }
                return TestsSuite::getBrowser()?->getPages()[0];
            } catch (\Throwable $e) {
                if ($try >= 3) {
                    throw $e;
                }
                usleep(500000);
            }
        }
    }

    public function before($headless = null, bool $getBrowser = true)
    {
        $this->suite = get_class($this);
        $this->start_time = hrtime(true);

        if (!$this->isVersionSupported()) {
            throw new Error('This version of PrestaShop is not supported by PrestaFlow.');
        }

        if ($headless === null) {
            $headless = $this->isHeadlessMode();
        }

        if ($getBrowser === false) {
            return;
        }

        TestsSuite::getBrowser(headless: $headless, force: true);

        // Authentification HTTP Basic (env) posée en header sur TOUTES les requêtes
        // (navigation top-level, sous-ressources ET XHR), avant toute navigation.
        // Plus fiable que des identifiants dans l'URL (https://user:pass@host/…),
        // que Chrome n'applique pas aux requêtes XHR ni toujours aux redirections.
        $this->presetBasicAuth();

        // Pré-réglage de cookies fournis via l'environnement (PRESTAFLOW_COOKIES,
        // JSON), avant toute navigation. Pratique pour neutraliser un bandeau de
        // consentement (RGPD) sur un environnement protégé/preprod.
        $this->presetEnvCookies();

        try {
            $page = TestsSuite::getPage();
            if ($page !== null) {
                // Clear all browser cookies so each suite starts from a clean,
                // unauthenticated state (the previous per-cookie clearing set a
                // malformed expiry and never actually removed the admin session).
                $page->getSession()->sendMessageSync(
                    new \HeadlessChromium\Communication\Message('Network.clearBrowserCookies')
                );
            }
        } catch (\Throwable $e) {
        }

        $this->start_time = hrtime(true);
    }

    /**
     * Authentification HTTP Basic via l'environnement, posée en en-tête sur toutes
     * les requêtes (utile pour un environnement protégé : preprod/staging).
     *
     * PRESTAFLOW_BASIC_USER / PRESTAFLOW_BASIC_PASS. Best-effort.
     *
     * On pose l'en-tête au niveau de la CONNEXION du navigateur : BrowserFactory
     * réapplique ces en-têtes à chaque nouvelle page. C'est indispensable car
     * FrontOfficePage::goToPage() ferme la page courante et en crée une neuve —
     * un en-tête posé uniquement sur la page initiale serait perdu. On l'applique
     * aussi à la page courante pour couvrir la toute première navigation.
     */
    protected function presetBasicAuth(): void
    {
        $user = $_ENV['PRESTAFLOW_BASIC_USER'] ?? null;
        $pass = $_ENV['PRESTAFLOW_BASIC_PASS'] ?? null;
        if ($user === null || $user === '') {
            return;
        }

        $authHeader = 'Basic '.\base64_encode($user.':'.($pass ?? ''));

        // Mémorisé pour réapplication après chaque (re)création de page (goToPage).
        TestsSuite::$extraHttpHeaders['Authorization'] = $authHeader;

        $browser = TestsSuite::getBrowser();
        if ($browser) {
            try {
                // Hérité par chaque page créée ensuite (dont le createPage de goToPage).
                $browser->getConnection()->setConnectionHttpHeaders(['Authorization' => $authHeader]);
            } catch (Throwable $e) {
                // best-effort
            }
        }

        // Applique sur la page courante (première navigation).
        TestsSuite::applyExtraHttpHeaders();
    }

    /**
     * (Ré)applique self::$extraHttpHeaders sur la page courante. À appeler après
     * toute (re)création de page — notamment dans goToPage — car un en-tête posé
     * sur une page fermée est perdu. Best-effort.
     */
    public static function applyExtraHttpHeaders(): void
    {
        if (empty(TestsSuite::$extraHttpHeaders)) {
            return;
        }

        $page = TestsSuite::getPage();
        if (!$page) {
            return;
        }

        try {
            // Network doit être activé pour que Network.setExtraHTTPHeaders prenne effet.
            $page->getSession()->sendMessageSync(new Message('Network.enable'));
            $page->setExtraHTTPHeaders(TestsSuite::$extraHttpHeaders);
        } catch (Throwable $e) {
            // best-effort
        }
    }

    /**
     * Pré-règle des cookies fournis via l'environnement, avant toute navigation.
     *
     * PRESTAFLOW_COOKIES = tableau JSON d'objets {name, value, domain?, path?, secure?}.
     * Exemple (neutraliser un bandeau RGPD Knowband) :
     *   PRESTAFLOW_COOKIES=[{"name":"___kbgdcc","value":"eyIx...","domain":"preprod.example.com"}]
     *
     * Best-effort : n'interrompt jamais l'exécution si l'API cookies échoue.
     */
    protected function presetEnvCookies(): void
    {
        $raw = $_ENV['PRESTAFLOW_COOKIES'] ?? null;
        if (!$raw) {
            return;
        }

        $cookies = \json_decode($raw, true);
        if (!is_array($cookies)) {
            return;
        }

        $page = TestsSuite::getPage();
        if (!$page) {
            return;
        }

        foreach ($cookies as $c) {
            if (empty($c['name'])) {
                continue;
            }

            $params = [];
            foreach (['domain', 'path', 'secure', 'httpOnly', 'sameSite', 'expires', 'url'] as $key) {
                if (array_key_exists($key, $c)) {
                    $params[$key] = $c[$key];
                }
            }
            if (!isset($params['path'])) {
                $params['path'] = '/';
            }

            try {
                $page->setCookies([
                    Cookie::create($c['name'], (string) ($c['value'] ?? ''), $params),
                ])->await();
            } catch (Throwable $e) {
                // best-effort
            }
        }
    }

    public function after()
    {
        // The keepAlive browser is intentionally left open so the next suite in
        // the same run can reconnect to it. It is closed once at the end of the
        // run by the command (ExecuteSuite).
        $this->end_time = hrtime(true);
        $this->stats['time'] = round(($this->end_time - $this->start_time) / 1e+6);
    }

    public function getInstructions(&$test)
    {
        if ($this->cli) {
            return;
        }

        $instructions = [];
        $reflection = new \ReflectionFunction($test['steps']);

        if (isset(self::$lines[$reflection->getFileName()])) {
            $lines = self::$lines[$reflection->getFileName()];
        } else {
            $lines = file($reflection->getFileName());
            self::$lines[$reflection->getFileName()] = $lines;
        }
        for ($i = ($reflection->getStartLine() - 1) ; $i < ($reflection->getEndLine()) ; $i++) {
            $instructions[$i] = $lines[$i];
        }

        return $test['code'] = $instructions;
    }

    public function init()
    {
        $this->stats = [
            'passes' => 0,
            'failures' => 0,
            'skips' => 0,
            'skippeds' => 0,
            'todos' => 0,
            'assertions' => 0,
            'time' => 0,
        ];

        return $this;
    }

    public function setLocale(string $locale)
    {
        self::$locale = $locale;
        Expect::setLocale($locale);
    }

    public function setGlobals(array $globals = [])
    {
        $this->globals = array_merge($this->globals, $globals);

        $this->resolveVersion();
        $this->setLocale($this->globals['LOCALE'] ?? 'en');

        return $this;
    }

    public function setSelectors(array $selectors = [])
    {
        $this->customs['selectors'] = $selectors;

        return $this;
    }

    public function setMessages(array $messages = [])
    {
        $this->customs['messages'] = $messages;

        return $this;
    }

    public function setUrls(array $urls = [])
    {
        $this->customs['urls'] = $urls;

        return $this;
    }

    public function loadGlobals()
    {
        // Répertoire de travail courant : le CLI `prestaflow` est lancé depuis la
        // racine du projet qui consomme la lib, où se trouvent .env / .env.local.
        // C'est le chemin le plus fiable, y compris quand la lib est installée en
        // symlink (path repo Composer) — auquel cas __DIR__ pointe hors du projet.
        // `createImmutable` : la première valeur trouvée gagne → on charge d'abord.
        $dotenv = Dotenv::createImmutable(getcwd(), ['.env.local', '.env']);
        $dotenv->safeLoad();

        $dotenv = Dotenv::createImmutable(__DIR__.'/../../', ['.env.local', '.env']);
        $dotenv->safeLoad();
        // When importing the library in a project, the .env file is not in the same directory
        $dotenv = Dotenv::createImmutable(__DIR__.'/../../../../../', ['.env.local', '.env']);
        $dotenv->safeLoad();

        if (isset($_ENV['PRESTAFLOW_DEBUG'])) {
            $_ENV['PRESTAFLOW_DEBUG'] = filter_var($_ENV['PRESTAFLOW_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $_ENV['PRESTAFLOW_DEBUG'] = false;
        }

        if (isset($_ENV['PRESTAFLOW_HEADLESS'])) {
            $_ENV['PRESTAFLOW_HEADLESS'] = filter_var($_ENV['PRESTAFLOW_HEADLESS'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $_ENV['PRESTAFLOW_HEADLESS'] = true;
        }

        if (isset($_ENV['PRESTAFLOW_PREFIX_LOCALE'])) {
            $_ENV['PRESTAFLOW_PREFIX_LOCALE'] = filter_var($_ENV['PRESTAFLOW_PREFIX_LOCALE'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $_ENV['PRESTAFLOW_PREFIX_LOCALE'] = false;
        }

        if (isset($_ENV['PRESTAFLOW_VERBOSE'])) {
            $_ENV['PRESTAFLOW_VERBOSE'] = filter_var($_ENV['PRESTAFLOW_VERBOSE'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $_ENV['PRESTAFLOW_VERBOSE'] = true;
        }

        $frontOfficeUrl = $_ENV['PRESTAFLOW_FO_URL'] ?? 'https://localhost/';
        if (!str_ends_with($frontOfficeUrl, '/')) {
            $frontOfficeUrl .= '/';
        }

        $backOfficeUrl = $_ENV['PRESTAFLOW_BO_URL'] ?? $frontOfficeUrl . 'admin-dev/';
        if (!str_starts_with($backOfficeUrl, 'https://') && !str_starts_with($backOfficeUrl, 'http://')) {
            $backOfficeUrl = $frontOfficeUrl . $backOfficeUrl;
        }
        if (!str_ends_with($backOfficeUrl, '/')) {
            $backOfficeUrl .= '/';
        }

        $this->globals = [
            'PS_VERSION' => $_ENV['PRESTAFLOW_PS_VERSION'] ?? '8.1.0',
            'LOCALE' => $_ENV['PRESTAFLOW_LOCALE'] ?? 'en',
            'PREFIX_LOCALE' => (bool) $_ENV['PRESTAFLOW_PREFIX_LOCALE'] ?? false,
            'BO' => [
                'URL' => $backOfficeUrl,
                'EMAIL' => $_ENV['PRESTAFLOW_BO_EMAIL'] ?? 'demo@prestashop.com',
                'PASSWD' => $_ENV['PRESTAFLOW_BO_PASSWD'] ?? 'Correct Horse Battery Staple',
            ],
            'FO' => [
                'URL' => $frontOfficeUrl,
                'EMAIL' => $_ENV['PRESTAFLOW_FO_EMAIL'] ?? 'pub@prestashop.com',
                'PASSWD' => $_ENV['PRESTAFLOW_FO_PASSWD'] ?? '123456789',
            ],
            'DEBUG' => (bool) $_ENV['PRESTAFLOW_DEBUG'] ?? false,
            'VERBOSE' => (bool) $_ENV['PRESTAFLOW_VERBOSE'] ?? true,
            'BROWSER' => [
                'HEADLESS' => (bool) $_ENV['PRESTAFLOW_HEADLESS'] ?? true,
                'WINDOW_SIZE_HEIGHT' => $_ENV['PRESTAFLOW_WINDOW_SIZE_HEIGHT'] ?? 1920,
                'WINDOW_SIZE_WIDTH' => $_ENV['PRESTAFLOW_WINDOW_SIZE_WIDTH'] ?? 1000,
                'USER_AGENT' => $_ENV['PRESTAFLOW_USER_AGENT'] ?? 'PrestaFlow',
            ],
        ];

        $this->exctractVersions($_ENV['PRESTAFLOW_PS_VERSION'] ?? '8.1.0');
        $this->setLocale($_ENV['PRESTAFLOW_LOCALE'] ?? 'en');
    }

    public function isVerboseMode(): bool
    {
        return $this->getGlobals()['VERBOSE'] ?? true;
    }

    public function isHeadlessMode(): bool
    {
        return $this->getGlobals()['BROWSER']['HEADLESS'] ?? true;
    }

    public function isDebugMode(): bool
    {
        return $this->getGlobals()['DEBUG'] ?? false;
    }

    public function getGlobals() : array
    {
        if (!is_array($this->globals)) {
            throw new Exception('Globals are not set. Please call loadGlobals() first.');
        }
        return $this->globals;
    }

    public function run($cli = false, ?OutputInterface $output = null, string $mode = 'full', string $section = '', mixed $sectionOutput = null)
    {
        $this->cli = $cli;
        $this->output = $output;
        $this->outputMode = $mode;

        if (!empty($section) && $sectionOutput !== null) {
            $this->outputSections[$section] = $sectionOutput;
        }

        if (!$this->init) {
            $this->init();
            $this->init = true;
        }

        $className = str_replace('\\', '/', $this->suite);

        $sectionId = ($this->cli ? 'cli-' : '') . sha1(str_replace('\\', '-', $this->suite));
        if (!array_key_exists($sectionId, $this->outputSections)) {
            if ($this->cli && self::OUTPUT_JSON !== $this->getOutputMode()) {
                $this->outputSections[$sectionId] = $output->section();
            } else {
                $this->outputSections[$sectionId] = [];
            }
        }

        if (isset($this->tests) && is_array($this->tests)) {
            $this->info($this->title, newLine: true, section: $sectionId);
            $this->cli(title: 'Suite:', bold: false, titleColor: 'gray', secondaryColor: 'white', message: $className, section: $sectionId);

            // Get DataSets
            $datasets = $this->getDatasets();
            if (count($datasets) === 0) {
                // Trick to get at least one execution of tests
                $datasets[] = [];
            }

            $tests = [];
            foreach ($datasets as $key => $dataset) {
                foreach ($this->tests as &$test) {
                    $test['datasets'] = $dataset;
                    $test['dataset'] = $key + 1;
                    $tests[] = $test;
                }
            }
            $this->tests = $tests;

            foreach ($this->tests as &$test) {
                try {
                    $startTime = hrtime(true);

                    $this->dataset = $test['datasets'];

                    $this->getInstructions($test);

                    if ($this->isSkippable($test) === true) {
                        $test['state'] = 'skip';
                        $this->stats['skips']++;
                    } else if ($this->isSkippableCauseFailed($test) === true) {
                        $test['state'] = 'skipped';
                        $this->stats['skippeds']++;
                    } else if ($this->isTodoable($test) === true) {
                        $test['state'] = 'todo';
                        $this->stats['todos']++;
                    } else {
                        $this->scenarioName = null;

                        $reflection = new \ReflectionFunction($test['steps']);
                        $this->scenarioName = $reflection->getClosureCalledClass()->name;

                        $test['steps']->call($this);
                        $this->stats['assertions'] += Expect::getNbAssertions();

                        $this->attachWarning($test);

                        $test['state'] = 'pass';
                        $this->stats['passes']++;
                    }
                } catch (OperationTimedOut | UnexpectedValueException | TargetDestroyed | FatalError | Throwable | Exception $e) {
                    $test['state'] = 'fail';
                    Expect::$expectMessage['fail'] = [$e->getMessage()];
                    $this->attachWarning($test);
                    $this->attachScreen($test);
                    $this->stats['assertions'] += Expect::getNbAssertions();
                    $this->stats['failures']++;
                    $this->failed = true;
                } finally {
                    // Reset structurel : aucune attache visuelle ne fuite d'un test
                    // à l'autre, quel que soit l'état (pass compris).
                    Expect::$latestAttachments = [];
                    $test['expect'] = Expect::getExpectMessage();
                    $this->attachDebugMessages($test);
                    Expect::getNbAssertions();
                    $endTime = hrtime(true);
                    $test['time'] = round(($endTime - $startTime) / 1e+6);

                    match ($test['state']) {
                        'skip' => $this->skipped(test: $test, section: $sectionId, newLine: true),
                        'skipped' => $this->skippedCauseItsFail(test: $test, section: $sectionId, newLine: true),
                        'todo' => $this->toBeDone(test: $test, section: $sectionId, newLine: true),
                        'pass' => $this->pass(test: $test, section: $sectionId, newLine: true),
                        'fail' => $this->fail(test: $test, section: $sectionId, newLine: true),
                        default => $this->info(message: $test, section: $sectionId, newLine: true)
                    };
                }
            }

            $endTime = hrtime(true);
            $this->stats['time'] = round(($endTime - $this->start_time) / 1e+6);

            $tests = [];
            if ($this->stats['failures']) {
                $tests[] = sprintf('<fg=red;options=bold>%d failures</>', $this->stats['failures']);
            }
            if ($this->stats['passes']) {
                $tests[] = sprintf('<fg=green;options=bold>%d passed</>', $this->stats['passes']);
            }
            if ($this->stats['skips']) {
                $tests[] = sprintf('<fg=bright-yellow;options=bold>%d skips</>', $this->stats['skips']);
            }
            if ($this->stats['skippeds']) {
                $tests[] = sprintf('<fg=bright-yellow;options=bold>%d skippeds</>', $this->stats['skippeds']);
            }
            if ($this->stats['todos']) {
                $tests[] = sprintf('<fg=blue;options=bold>%d todos</>', $this->stats['todos']);
            }

            if ($this->cli && self::OUTPUT_JSON !== $this->getOutputMode()) {
                $this->outputSections[$sectionId]->writeln('');
                $this->outputSections[$sectionId]->writeln([
                        sprintf(
                            '  <fg=gray>Tests:</>    <fg=default>%s</><fg=gray> (%s assertions)</>',
                            implode('<fg=gray>,</> ', $tests),
                        (int) $this->stats['assertions']
                    ),
                ]);
                $this->outputSections[$sectionId]->writeln(sprintf('  <fg=gray>Duration:</> <fg=white>%ss</>', $this->formatSeconds($this->stats['time'])));
            } else {
                $this->outputSections[$sectionId]['stats'] = $this->stats;
                $this->outputSections[$sectionId]['duration'] = $this->formatSeconds($this->stats['time']).'s';
            }
        }

        $this->after();

        if (!empty($section)) {
            return $this->outputSections[$section];
        }
    }

    public function console(string|array $message): static
    {
        return $this->log($message);
    }

    public function log(string|array $message): static
    {
        self::$pendingDebugMessages[] = is_array($message)
            ? json_encode($message, JSON_PRETTY_PRINT)
            : $message;

        return $this;
    }

    public function attachWarning(&$test)
    {
        $test['warning'] = Expect::$latestWarning;
        $this->warnings[] = $test['warning'];
    }

    public function attachScreen(&$test)
    {
        $test['screen'] = Expect::$latestError;
        $this->screens[] = $test['screen'];

        $test['attachments'] = Expect::$latestAttachments ?? [];

        if (!empty(Expect::$latestScreenshotError)) {
            $this->log('Screenshot capture failed: ' . Expect::$latestScreenshotError);
            Expect::$latestScreenshotError = null;
        }
    }

    public function attachDebugMessages(&$test)
    {
        $test['debug'] = self::$pendingDebugMessages;
        self::$pendingDebugMessages = [];
    }

    public function results($json = true)
    {
        $results = [
            'suite' => $this->suite,
            'title' => $this->title,
            'stats' => $this->stats,
            'tests' => $this->tests,
            'warnings' => $this->warnings,
            'screens' => $this->screens,
        ];

        if (true === $json) {
            return json_encode($results);
        }

        return $results;
    }
}
