<?php

namespace PrestaFlow\Library\Tests;

use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\OperationTimedOut;
use PrestaFlow\Library\Expects\Expect;
use UnexpectedValueException;

class TestsSuite
{
    public array $suites = [];
    private array $stats = [
        'passes' => 0,
        'failures' => 0,
        'skips' => 0,
    ];

    private $_latestSuite = null;
    private $_runestSuite = null;
    private $_tests = [];

    private $browser;
    public $page;

    public function describe(string $description)
    {
        $this->_latestSuite = get_class($this);
        $this->suites[$this->_latestSuite] = [
            'suite' => '',
            'title' => $description,
            'tests' => $this->_tests,
            'stats' => [
                'passes' => 0,
                'failures' => 0,
                'skips' => 0,
            ]
        ];
        $this->_tests = [];
    }

    public function it(string $description, $steps)
    {
        $this->_tests[] = [
            'title' => $description,
            'steps' => $steps
        ];
    }

    public function skip(string $description, $steps)
    {
        $this->_tests[] = [
            'title' => $description,
            'steps' => $steps,
            'skip' => true,
        ];
    }

    public function isSkippable($test)
    {
        if (isset($test['skip']) && $test['skip']) {
            return true;
        }

        return false;
    }

    public function before()
    {
        $this->_runestSuite = get_class($this);
        $this->suites[$this->_runestSuite]['suite'] = str_replace('\\', '/', $this->_runestSuite);
        $this->start_time = hrtime(true);

        $browserFactory = new BrowserFactory();

        // starts headless Chrome
        $this->browser = $browserFactory->createBrowser([
            'userAgent' => 'PrestaFlow',
            'headless' => false, // disable headless mode
        ]);

        try {
            // creates a new page and navigate to an URL
            $this->page = $this->browser->createPage();
        } catch (Exception $e) {
            $this->after();
        }
    }

    public function after()
    {
        $this->browser->close();

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
        $this->before();

        foreach ($this->suites[$this->_runestSuite]['tests'] as &$test) {
            try {
                $start_time = hrtime(true);
                $this->getInstructions($test);
                if ($this->isSkippable($test) === false) {
                    $test['steps']->call($this);

                    $test['state'] = 'pass';
                    $this->stats['passes']++;
                } else {
                    $test['state'] = 'skip';
                    $this->stats['skips']++;
                }
            } catch (OperationTimedOut $e) {
                $test['state'] = 'fail';
                $test['error'] = $e->getMessage();
                $this->attachScreen($test);
                $this->stats['failures']++;
            } catch (UnexpectedValueException $e) {
                $test['state'] = 'fail';
                $test['error'] = $e->getMessage();
                $this->attachScreen($test);
                $this->stats['failures']++;
            } catch (Exception $e) {
                $test['state'] = 'fail';
                $test['error'] = $e->getMessage();
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
