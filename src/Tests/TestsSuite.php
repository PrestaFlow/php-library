<?php

namespace PrestaFlow\Library\Tests;

use Exception;

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

        echo('Run : '. $this->_runestSuite.'<br />');
        echo('-'.'Describe : ' . $this->suites[$this->_runestSuite]['title'].'<br />');
        foreach ($this->suites[$this->_runestSuite]['tests'] as &$test) {
            echo('--'.$test['title'].'<br />');

            try {
                $test['steps']->call($this);

                $this->stats['passes']++;
            } catch (\UnexpectedValueException $e) {
                $tests['error'] = $e->getMessage();
                echo('----'.$tests['error'].'<br />');
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
}
