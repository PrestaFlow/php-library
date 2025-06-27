<?php

namespace PrestaFlow\Library\Resolvers;

use PrestaFlow\Library\Traits\Locale;
use PrestaFlow\Library\Traits\Version;

trait Translations
{
    use Locale;
    use Version;

    public $translationsCatalog = null;

    public function initTranslations(string $locale, string $patchVersion)
    {
        $this->setLocale($locale);
        $this->exctractVersions($patchVersion);
    }

    public function translate($message)
    {
        //get_class($this));
        if (is_null($this->translationsCatalog)) {
            $this->translationsCatalog = $this->getCatalog();
        }

        if (isset($this->translationsCatalog[$message])) {
            return $this->translationsCatalog[$message];
        }

        return $message;
    }

    public function getCatalog()
    {
        if (!$this->localeIsInit()) {
            $this->initLocale($this->getGlobal('LOCALE'));
        }

        $basePath = __DIR__.'/../Translations/';
        $fileName = $this->getLocale().'.json';
        $defaultCatalog = [];
        $patchCatalog = [];
        $minorCatalog = [];
        $majorCatalog = [];
        $mergedCatalog = [];

        $pathToCatalog = $basePath.$fileName;
        if (file_exists($pathToCatalog)) {
            $defaultCatalog = json_decode(file_get_contents($pathToCatalog), true);
        }

        if ($this->getMajorVersion() !== null) {
            $pathToCatalog = $basePath.$this->getMajorVersion().'/'.$fileName;
            if (file_exists($pathToCatalog)) {
                $majorCatalog = json_decode(file_get_contents($pathToCatalog), true);
            }
        }

        if ($this->getMinorVersion() !== null) {
            $pathToCatalog = $basePath.$this->getMajorVersion().'/'.$this->getMinorVersion().'/'.$fileName;
            if (file_exists($pathToCatalog)) {
                $minorCatalog = json_decode(file_get_contents($pathToCatalog), true);
            }
        }

        if ($this->getPatchVersion() !== null) {
            $pathToCatalog = $basePath.$this->getMajorVersion().'/'.$this->getMinorVersion().'/'.$this->getPatchVersion().'/'.$fileName;
            if (file_exists($pathToCatalog)) {
                $patchCatalog = json_decode(file_get_contents($pathToCatalog), true);
            }
        }

        $mergedCatalog = [
            ...$defaultCatalog,
            ...$majorCatalog,
            ...$minorCatalog,
            ...$patchCatalog,
        ];

        return $mergedCatalog;
    }
}
