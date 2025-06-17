<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Traits\Version;

class Scenario
{
    use Version;

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
}
