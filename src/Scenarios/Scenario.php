<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Traits\ImportPage;
use PrestaFlow\Library\Traits\Version;

class Scenario
{
    use Version;
    use ImportPage;

    public $globals = [];
    public $params = [];
    public $pages = [];

    public function __construct($testSuite, $params = [])
    {
        $this->params = [...$this->params, ...$params];
        $this->steps($testSuite);
    }

    public function steps($testSuite)
    {
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
}
