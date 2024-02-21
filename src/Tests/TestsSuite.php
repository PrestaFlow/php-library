<?php

namespace PrestaFlow\Library\Tests;

class TestsSuite
{
    public $page;
    public array $suites = [];

    private $_latestSuite = null;

    public function describe(string $value)
    {
        $this->_latestSuite = get_class($this);
        $this->suites[$this->_latestSuite] = [
            'title' => $description,
            'tests' => [],
        ];
    }

    public function it(string $description, $instructions)
    {
        $this->suites[$this->_latestSuite]['tests'][] = [
            $description,
            $instruction
        ]
    }

    public function run()
    {

    }
}
