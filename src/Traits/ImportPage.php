<?php

namespace PrestaFlow\Library\Traits;

trait ImportPage
{
    public function importPage($pageName, $userAgent = 'PrestaFlow', $globals = null)
    {
        $pageClass = '\\PrestaFlow\\Library\\Pages\\v'.$this->getMajorVersion().'\\'.$pageName.'\\Page';

        $pageInstance = new $pageClass($this->getLocale(), $this->getPatchVersion());
        if ($globals === null || !is_array($globals)) {
            $pageInstance->setGlobals($this->globals);
        } else {
            $pageInstance->setGlobals($globals);
        }
        $pageInstance->setUserAgent($userAgent);
        $pageInstance->setLocale($this->getLocale());
        $pageInstance->setPatchVersion($this->getPatchVersion());
        $pageInstance->setMinorVersion($this->getMinorVersion());
        $pageInstance->setMajorVersion($this->getMajorVersion());

        $pageVarName = lcfirst(str_replace('\\', '', ucwords($pageName, '\\'))).'Page';

        $this->pages[$pageVarName] = $pageInstance;
    }
}
