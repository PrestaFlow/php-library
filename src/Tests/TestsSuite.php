<?php

namespace PrestaFlow\Library\Tests;

use Exception;
use HeadlessChromium\BrowserFactory;
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
        $browserFactory = new BrowserFactory();

        // starts headless Chrome
        $this->browser = $browserFactory->createBrowser([
            'headless' => true, // disable headless mode
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
    }

    public function run()
    {
        $this->_runestSuite = get_class($this);

        $this->before();

        foreach ($this->suites[$this->_runestSuite]['tests'] as &$test) {
            try {
                if ($this->isSkippable($test) === false) {
                    $test['steps']->call($this);

                    $test['state'] = 'pass';
                    $this->stats['passes']++;
                } else {
                    $test['state'] = 'skip';
                    $this->stats['skips']++;
                }
            } catch (UnexpectedValueException $e) {
                $test['state'] = 'fail';
                $test['error'] = $e->getMessage();
                $this->screen($this->page, 'error');
                $this->stats['failures']++;
            } catch (Exception $e) {
                $test['state'] = 'fail';
                $test['error'] = $e->getMessage();
                $this->screen($this->page, 'error');
                $this->stats['failures']++;
            } finally {
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

    public function screen($page, $type = '')
    {
        $pages = $this->browser->getPages();
        //$this->browser->getPage()->screenshot()->saveToFile('../../screens/errors/bar.png');
    }

    public function results($type = 'json')
    {
        if ($type == 'json') {
            return json_encode($this->suites[$this->_runestSuite]);
        }

        return $this->suites[$this->_runestSuite];
    }
}
