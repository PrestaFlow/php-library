<?php

namespace PrestaFlow\Library\Traits;

trait ImportPage
{
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
