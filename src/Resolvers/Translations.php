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
        $this->translationsCatalog = null;
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

        /*
        $customDomain = null;
        $currentClass = get_class($this);
        if (!str_starts_with($currentClass, 'PrestaFlow\\Library\\')) {
            $domains = explode('\\', $currentClass);
            foreach ($domains as $domain) {
                if ($domain == 'Pages') {
                    break;
                }
                $customDomain .= $domain.'\\';
            }
        }
        var_dump($customDomain);
        */

        $customPath = __DIR__.'/../../../../../Tests/Translations/';
        $basePath = __DIR__.'/../Translations/';
        $fileName = $this->getLocale().'.json';
        $defaultCatalog = [];
        $customCatalog = [];
        $patchCatalog = [];
        $minorCatalog = [];
        $majorCatalog = [];
        $mergedCatalog = [];

        $pathToCatalog = $basePath.$fileName;
        if (file_exists($pathToCatalog)) {
            $defaultCatalog = json_decode(file_get_contents($pathToCatalog), true);
        }

        $pathToCatalog = $customPath.$fileName;
        if (file_exists($pathToCatalog)) {
            $customCatalog = json_decode(file_get_contents($pathToCatalog), true);
            if (!is_array($customCatalog)) {
                $customCatalog = [];
            }
        }

        if ($this->getMajorVersion() !== null) {
            $pathToCatalog = $basePath.$this->getMajorVersion().'/'.$fileName;
            if (file_exists($pathToCatalog)) {
                $majorCatalog = json_decode(file_get_contents($pathToCatalog), true);
                if (!is_array($majorCatalog)) {
                    $majorCatalog = [];
                }
            }
        }

        if ($this->getMinorVersion() !== null) {
            $pathToCatalog = $basePath.$this->getMajorVersion().'/'.$this->getMinorVersion().'/'.$fileName;
            if (file_exists($pathToCatalog)) {
                $minorCatalog = json_decode(file_get_contents($pathToCatalog), true);
                if (!is_array($minorCatalog)) {
                    $minorCatalog = [];
                }
            }
        }

        if ($this->getPatchVersion() !== null) {
            $pathToCatalog = $basePath.$this->getMajorVersion().'/'.$this->getMinorVersion().'/'.$this->getPatchVersion().'/'.$fileName;
            if (file_exists($pathToCatalog)) {
                $patchCatalog = json_decode(file_get_contents($pathToCatalog), true);
                if (!is_array($patchCatalog)) {
                    $patchCatalog = [];
                }
            }
        }

        if (is_array($this->customs['messages'])) {
            $customCatalog = [
                ...$customCatalog,
                ...$this->customs['messages'],
            ];
        }

        $mergedCatalog = [
            ...$defaultCatalog,
            ...$majorCatalog,
            ...$minorCatalog,
            ...$patchCatalog,
            ...$customCatalog,
        ];

        return $mergedCatalog;
    }
}
