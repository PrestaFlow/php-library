<?php

namespace PrestaFlow\Library\Tests;

use Closure;
use Dotenv\Dotenv;
use Error;
use Exception;
use HeadlessChromium\BrowserFactory;
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

    public $customs = [
        'selectors' => [],
        'messages' => [],
        'urls' => [],
    ];

    protected $dataset = [];
    protected $datasets = [];

    protected $scenarioName = '';
    protected $scenarioParams = [];

    protected static $lines = [];

    protected $draft = false;
    protected $groups = 'all';

    public function __construct(bool $loadGlobals = true)
    {
        if ($loadGlobals) {
            $this->loadGlobals();
        }

        $this->before();
    }

    public function getParam($paramName)
    {
        return $this->scenarioParams[$this->scenarioName][$paramName] ?? $this->dataset[$paramName] ?? null;
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
        $this->tests[] = [
            'title' => $description,
            'steps' => $steps,
            'datasets' => $this->datasets,
        ];

        return $this;
    }

    public function skip(string $description, Closure $steps)
    {
        $this->tests[] = [
            'title' => $description,
            'steps' => $steps,
            'skip' => true,
        ];

        return $this;
    }

    public function todo(string $description, Closure $steps)
    {
        $this->tests[] = [
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

    public function isSkippableCauseFailed($test)
    {
        if ($this->failed && $this->skipWhenFailed) {
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
                'windowSize' => [1920, 1000],
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
        $this->suite = get_class($this);
        $this->start_time = hrtime(true);

        if (!$this->isVersionSupported()) {
            throw new Error('This version of PrestaShop is not supported by PrestaFlow.');
        }

        if ($headless === null) {
            $headless = $this->isHeadlessMode();
        }

        TestsSuite::getBrowser(headless: $headless, force: true);

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

    public function setGlobals(array $globals = [])
    {
        $this->globals = array_merge($this->globals, $globals);

        $this->exctractVersions($this->globals['PS_VERSION'] ?? '8.1.0');
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
        $dotenv = Dotenv::createImmutable(__DIR__.'/../../');
        $dotenv->safeLoad();
        // When importing the library in a project, the .env file is not in the same directory
        $dotenv = Dotenv::createImmutable(__DIR__.'/../../../../../');
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

        $this->globals = [
            'PS_VERSION' => $_ENV['PRESTAFLOW_PS_VERSION'] ?? '8.1.0',
            'LOCALE' => $_ENV['PRESTAFLOW_LOCALE'] ?? 'en',
            'PREFIX_LOCALE' => (bool) $_ENV['PRESTAFLOW_PREFIX_LOCALE'] ?? false,
            'BO' => [
                'URL' => $_ENV['PRESTAFLOW_BO_URL'] ?? $frontOfficeUrl . 'admin-dev/',
                'EMAIL' => $_ENV['PRESTAFLOW_BO_EMAIL'] ?? 'demo@prestashop.com',
                'PASSWD' => $_ENV['PRESTAFLOW_BO_PASSWD'] ?? 'Correct Horse Battery Staple',
            ],
            'FO' => [
                'URL' => $frontOfficeUrl,
                'EMAIL' => $_ENV['PRESTAFLOW_FO_EMAIL'] ?? 'pub@prestashop.com',
                'PASSWD' => $_ENV['PRESTAFLOW_FO_PASSWD'] ?? '123456789',
            ],
            'HEADLESS' => (bool) $_ENV['PRESTAFLOW_HEADLESS'] ?? true,
            'DEBUG' => (bool) $_ENV['PRESTAFLOW_DEBUG'] ?? false,
            'VERBOSE' => (bool) $_ENV['PRESTAFLOW_VERBOSE'] ?? true,
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
        return $this->getGlobals()['HEADLESS'] ?? true;
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

    public function run($cli = false, OutputInterface $output = null, string $mode = 'full', string $section = '', mixed $sectionOutput = null)
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
                    $test['expect'] = Expect::getExpectMessage();
                    Expect::getNbAssertions();
                    $endTime = hrtime(true);
                    $test['time'] = round(($endTime - $startTime) / 1e+6);

                    match ($test['state']) {
                        'skip' => $this->skipped(test: $test, section: $sectionId, newLine: true),
                        'skipped' => $this->skippedCauseItsFail(test: $test, section: $sectionId, newLine: true),
                        'todo' => $this->toBeDone(test: $test, section: $sectionId, newLine: true),
                        'pass' => $this->pass(test: $test, section: $sectionId, newLine: true),
                        'fail' => $this->fail(test: $test, section: $sectionId, newLine: true),
                        default => $this->info(test: $test, section: $sectionId, newLine: true)
                    };
                }
            }

            $endTime = hrtime(true);
            $this->stats['time'] = round(($endTime - $this->startTime) / 1e+6);

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

    public function attachWarning(&$test)
    {
        $test['warning'] = Expect::$latestWarning;
        $this->warnings[] = $test['warning'];
    }

    public function attachScreen(&$test)
    {
        $test['screen'] = Expect::$latestError;
        $this->screens[] = $test['screen'];
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
