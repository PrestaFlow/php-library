<?php

namespace PrestaFlow\Library\Tests;

use Dotenv\Dotenv;
use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Cookies\Cookie;
use HeadlessChromium\Cookies\CookiesCollection;
use HeadlessChromium\Exception\BrowserConnectionFailed;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Exception\TargetDestroyed;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Traits\Version;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Throwable;
use UnexpectedValueException;

class TestsSuite
{
    use Version;

    public array $suites = [];
    private array $stats = [
        'passes' => 0,
        'failures' => 0,
        'skips' => 0,
        'todos' => 0,
        'assertions' => 0,
    ];

    private $_runestSuite = null;

    protected $start_time;
    protected $end_time;

    protected $init = false;

    public $globals = [];
    public $pages = [];

    protected $scenarioName = '';
    protected $scenarioParams = [];

    protected static $lines = [];

    public function __construct()
    {
        $this->loadGlobals();

        $this->before();
    }

    protected function getSuite()
    {
        return get_class($this);
    }

    public function getParam($paramName)
    {
        return $this->scenarioParams[$this->scenarioName][$paramName] ?? null;
    }

    public function describe(string $description)
    {
        $this->suites[$this->getSuite()] = [
            'suite' => '',
            'title' => $description,
            'tests' => [],
            'stats' => [
                'passes' => 0,
                'failures' => 0,
                'skips' => 0,
                'todos' => 0,
                'assertions' => 0,
            ]
        ];

        return $this;
    }

    public function getDescribe()
    {
        return $this->suites[$this->getSuite()]['title'];
    }

    public function scenario($class, array $params = [])
    {
        $scenario = new $class($this, $params);
        $scenario->globals = $this->globals;

        $this->scenarioParams[get_class($scenario)] = $scenario->params;

        return $this;
    }

    public function it(string $description, $steps)
    {
        $this->suites[$this->getSuite()]['tests'][] = [
            'title' => $description,
            'steps' => $steps
        ];

        return $this;
    }

    public function skip(string $description, $steps)
    {
        $this->suites[$this->getSuite()]['tests'][] = [
            'title' => $description,
            'steps' => $steps,
            'skip' => true,
        ];

        return $this;
    }

    public function todo(string $description, $steps)
    {
        $this->suites[$this->getSuite()]['tests'][] = [
            'title' => $description,
            'steps' => $steps,
            'todo' => true,
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

    public function isTodoable($test)
    {
        if (isset($test['todo']) && $test['todo']) {
            return true;
        }

        return false;
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

        $socketFile = TestsSuite::getSocketFilePath();

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
            $browserFactory = new BrowserFactory();

            //$browserFactory->addOptions(['headless' => (bool) $headless]);

            $browser = $browserFactory->createBrowser([
                'userAgent' => 'PrestaFlow',
                'keepAlive' => true,
                'headless' => (bool) $headless,
            ]);
            \file_put_contents($socketFile, $browser->getSocketUri());
        }

        return $browser;
    }

    public static function getPage()
    {
        $pages = TestsSuite::getBrowser()?->getPages();
        if (count($pages) == 0) {
            TestsSuite::getBrowser()?->createPage();
        }
        return TestsSuite::getBrowser()?->getPages()[0];
    }

    public function before($headless = null)
    {
        $this->_runestSuite = get_class($this);
        $this->suites[$this->_runestSuite]['suite'] = str_replace('\\', '/', $this->_runestSuite);
        $this->start_time = hrtime(true);

        if ($headless === null) {
            $headless = true;
            if ($this->globals['HEADLESS'] === 'false' || !$this->globals['HEADLESS']) {
                $headless = false;
            }
        }

        TestsSuite::getBrowser($headless);

        try {
            $cookies = TestsSuite::getPage()?->getCookies();
            if ($cookies instanceof CookiesCollection && count($cookies)) {
                foreach ($cookies as $cookie) {
                    if (str_starts_with($cookie->getName(), 'PrestaShop-')) {
                        TestsSuite::getPage()->setCookies([
                            Cookie::create($cookie->getName(), '', ['expires'])
                        ])->await();
                    }
                }
            }
        } catch (Exception $e) {
            //
        }
    }

    public function after()
    {
        TestsSuite::getBrowser(force: false)?->close();

        $this->end_time = hrtime(true);
        $this->suites[$this->_runestSuite]['stats']['time'] = round(($this->end_time - $this->start_time) / 1e+6);
        ;
    }

    public function getInstructions(&$test)
    {
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
    }

    public function loadGlobals()
    {
        $dotenv = Dotenv::createImmutable(__DIR__.'/../../');
        $dotenv->safeLoad();
        // When importing the library in a project, the .env file is not in the same directory
        $dotenv = Dotenv::createImmutable(__DIR__.'/../../../../../');
        $dotenv->safeLoad();

        if (isset($_ENV['PRESTAFLOW_DEBUG'])) {
            $_ENV['PRESTAFLOW_DEBUG'] = filter_var($_ENV['PRESTAFLOW_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($_ENV['PRESTAFLOW_HEADLESS'])) {
            $_ENV['PRESTAFLOW_HEADLESS'] = filter_var($_ENV['PRESTAFLOW_HEADLESS'], FILTER_VALIDATE_BOOLEAN);
        }

        $this->globals = [
            'PS_VERSION' => $_ENV['PRESTAFLOW_PS_VERSION'] ?? '8.1.0',
            'LOCALE' => $_ENV['PRESTAFLOW_LOCALE'] ?? 'en',
            'BO' => [
                'URL' => $_ENV['PRESTAFLOW_BO_URL'] ?? ($_ENV['PRESTAFLOW_FO_URL'] ?? 'https://localhost') . '/admin-dev/',
                'EMAIL' => $_ENV['PRESTAFLOW_BO_EMAIL'] ?? 'demo@prestashop.com',
                'PASSWD' => $_ENV['PRESTAFLOW_BO_PASSWD'] ?? 'Correct Horse Battery Staple',
            ],
            'FO' => [
                'URL' => $_ENV['PRESTAFLOW_FO_URL'] ?? 'https://localhost/',
                'EMAIL' => $_ENV['PRESTAFLOW_FO_EMAIL'] ?? 'pub@prestashop.com',
                'PASSWD' => $_ENV['PRESTAFLOW_FO_PASSWD'] ?? '123456789',
            ],
            'HEADLESS' => (bool) $_ENV['PRESTAFLOW_HEADLESS'] ?? true,
            'DEBUG' => (bool) $_ENV['PRESTAFLOW_DEBUG'] ?? false,
        ];
    }

    public function getGlobals() : array
    {
        return $this->globals;
    }

    public function importPage($pageName, $userAgent = 'PrestaFlow', $globals = null)
    {
        $pageClass = '\\PrestaFlow\\Library\\Pages\\'.$this->getVersion().'\\'.$pageName.'\\Page';

        $pageInstance = new $pageClass();
        if ($globals === null || !is_array($globals)) {
            $pageInstance->setGlobals($this->globals);
        } else {
            $pageInstance->setGlobals($globals);
        }
        $pageInstance->setUserAgent($userAgent);

        $pageVarName = lcfirst(str_replace('\\', '', ucwords($pageName, '\\'))).'Page';

        $this->pages[$pageVarName] = $pageInstance;
    }

    public function run($cli = false)
    {
        if (!$this->init) {
            $this->init();
            $this->init = true;
        }

        if (is_array($this->suites[$this->_runestSuite]['tests'])) {
            foreach ($this->suites[$this->_runestSuite]['tests'] as &$test) {
                try {
                    $start_time = hrtime(true);
                    if (!$cli) {
                        $this->getInstructions($test);
                    }
                    if ($this->isSkippable($test) === true) {
                        $test['state'] = 'skip';
                        $this->stats['skips']++;
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
                } finally {
                    $test['expect'] = Expect::getExpectMessage();
                    Expect::getNbAssertions();
                    $end_time = hrtime(true);
                    $test['time'] = round(($end_time - $start_time) / 1e+6);
                }
            }
        }

        $this->suites[$this->_runestSuite]['stats'] = $this->stats;
        $this->stats = [
            'passes' => 0,
            'failures' => 0,
            'skips' => 0,
            'todos' => 0,
            'assertions' => 0,
        ];

        $this->after();
    }

    public function attachWarning(&$test)
    {
        $test['warning'] = Expect::$latestWarning;
    }

    public function attachScreen(&$test)
    {
        $test['screen'] = Expect::$latestError;
    }

    public function results($json = true)
    {
        if (true === $json) {
            return json_encode($this->suites[$this->_runestSuite]);
        }

        return $this->suites[$this->_runestSuite];
    }
}
