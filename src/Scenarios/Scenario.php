<?php

namespace PrestaFlow\Library\Scenarios;

class Scenario
{
    public $globals = [];
    public $params = [];
    public $pages = [];

    public function __construct($testSuite, $params = [])
    {
        $this->params = $params;
        $this->steps($testSuite);
    }

    public function steps($testSuite)
    {
        return $this;
    }

    public function importPage($pageName, $userAgent = 'PrestaFlow', $globals = null)
    {
        $version = 'v8';

        $pageClass = '\\PrestaFlow\\Library\\Pages\\'.$version.'\\'.$pageName.'\\Page';

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
