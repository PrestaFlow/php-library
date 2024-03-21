<?php

namespace PrestaFlow\Library\Tests;

use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Cookies\Cookie;
use HeadlessChromium\Cookies\CookiesCollection;
use HeadlessChromium\Exception\BrowserConnectionFailed;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Exception\TargetDestroyed;
use PrestaFlow\Library\Expects\Expect;
use Symfony\Component\ErrorHandler\Error\FatalError;
use UnexpectedValueException;

class TestsSuite
{
    public array $suites = [];
    private array $stats = [
        'passes' => 0,
        'failures' => 0,
        'skips' => 0,
    ];

    private $_runs = 0;
    private $_latestSuite = null;
    private $_runestSuite = null;
    private $_tests = [];

    protected function getSuite()
    {
        return get_class($this);
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
            ]
        ];

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

    public function isSkippable($test)
    {
        if (isset($test['skip']) && $test['skip']) {
            return true;
        }

        return false;
    }

    public static function getSocketFilePath()
    {
        if (function_exists('storage_path')) {
            $socketFilePath = storage_path().'/datas/.broswer';
        } else {
            $socketFilePath = '../../datas/.broswer';
        }

        return $socketFilePath;
    }

    public static function getBrowser(bool $headless = true)
    {
        $headless = false;
        $browser = null;

        $socketFile = TestsSuite::getSocketFilePath();

        $socket = null;
        if (file_exists($socketFile)) {
            $socket = \file_get_contents($socketFile);
        }

        try {
            if ($socket === null) {
                throw new BrowserConnectionFailed('');
            }
            $browser = BrowserFactory::connectToBrowser($socket);
        } catch (BrowserConnectionFailed $e) {
            $browserFactory = new BrowserFactory();

            $browserFactory->addOptions(['headless' => (bool) $headless]);

            $browser = $browserFactory->createBrowser([
                'userAgent' => 'PrestaFlow',
                'keepAlive' => true,
                //'headless' => false,
            ]);
            \file_put_contents($socketFile, $browser->getSocketUri(), LOCK_EX);
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

    public function before(bool $headless = true)
    {
        $this->_runestSuite = get_class($this);
        $this->suites[$this->_runestSuite]['suite'] = str_replace('\\', '/', $this->_runestSuite);
        $this->start_time = hrtime(true);

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
        TestsSuite::getBrowser()?->close();

        $this->end_time = hrtime(true);
        $this->suites[$this->_runestSuite]['stats']['time'] = round(($this->end_time - $this->start_time) / 1e+6);
        ;
    }

    public function getInstructions(&$test)
    {
        $instructions = [];
        $reflection = new \ReflectionFunction($test['steps']);

        $lines = file($reflection->getFileName());
        for ($i = ($reflection->getStartLine() - 1) ; $i < ($reflection->getEndLine()) ; $i++) {
            $instructions[$i] = $lines[$i];
        }

        return $test['code'] = $instructions;
    }

    public function run()
    {
        foreach ($this->suites[$this->_runestSuite]['tests'] as &$test) {
            try {
                $start_time = hrtime(true);
                $this->getInstructions($test);
                if ($this->isSkippable($test) === false) {
                    $test['steps']->call($this);

                    $this->attachWarning($test);

                    $test['state'] = 'pass';
                    $this->stats['passes']++;
                } else {
                    $test['state'] = 'skip';
                    $this->stats['skips']++;
                }
            } catch (OperationTimedOut | UnexpectedValueException | TargetDestroyed | FatalError | Exception $e) {
                $test['state'] = 'fail';
                $test['error'] = $e->getMessage();
                $this->attachWarning($test);
                $this->attachScreen($test);
                $this->stats['failures']++;
            } finally {
                $end_time = hrtime(true);
                $test['time'] = round(($end_time - $start_time) / 1e+6);
            }
        }

        $this->suites[$this->_runestSuite]['stats'] = $this->stats;
        $this->stats = [
            'passes' => 0,
            'failures' => 0,
            'skips' => 0,
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
