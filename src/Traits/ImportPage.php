<?php

namespace PrestaFlow\Library\Traits;

trait ImportPage
{
    public function importPage($pageName, $userAgent = 'PrestaFlow', $globals = null, $domain = '\\PrestaFlow\\Library')
    {
        $pageClass = $domain.'\\Pages\\v'.$this->getMajorVersion().'\\'.$pageName.'\\Page';

        if ($globals === null || !is_array($globals)) {
            $globals = $this->globals;
        }

        if (isset($this->params['locale']) && is_string($this->params['locale'])) {
            $globals['LOCALE'] = $this->params['locale'];
        }
        if (isset($this->params['useIsoCode'])) {
            $globals['PREFIX_LOCALE'] = (bool) $this->params['useIsoCode'];
        }

        $locale = $globals['LOCALE'] ?? $this->getLocale();
        $patchVersion = $globals['PATCH_VERSION'] ?? $this->getPatchVersion();

        $pageInstance = new $pageClass(locale: $locale, patchVersion: $patchVersion, globals: $globals);
        $pageInstance->setGlobals($globals);
        $pageInstance->setUserAgent($userAgent);
        $pageInstance->setLocale(locale: $locale);
        $pageInstance->setPatchVersion($patchVersion);
        $pageInstance->setMinorVersion($this->getMinorVersion());
        $pageInstance->setMajorVersion($this->getMajorVersion());

        $pageInstance->initTranslations(
            locale: $locale,
            patchVersion: $pageInstance->getPatchVersion(),
        );

        $pageInstance->initUrls(
            locale: $locale
        );

        $pageVarName = lcfirst(str_replace('\\', '', ucwords($pageName, '\\'))).'Page';

        $this->pages[$pageVarName] = $pageInstance;
    }
}
