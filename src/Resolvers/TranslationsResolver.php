<?php

namespace PrestaFlow\Library\Resolvers;

use PrestaFlow\Library\Utils\Locale;
use PrestaFlow\Library\Utils\Versions;

class TranslationsResolver
{
    use Locale;
    use Versions;

    public function __construct(string $patchVersion, string $locale)
    {
        $this->setVersions($patchVersion);
        $this->setLocale($locale);
    }

    public function getCatalog()
    {
        $basePath = __DIR__.'/../Translations/';
        $fileName = $this->locale.'.json';
        $defaultCatalog = [];
        $patchCatalog = [];
        $minorCatalog = [];
        $majorCatalog = [];
        $mergedCatalog = [];

        $pathToCatalog = $basePath.$fileName;
        if (file_exists($pathToCatalog)) {
            $defaultCatalog = json_decode(file_get_contents($pathToCatalog), true);
        }

        $pathToCatalog = $basePath.$this->majorVersion.'/'.$fileName;
        if (file_exists($pathToCatalog)) {
            $majorCatalog = json_decode(file_get_contents($pathToCatalog), true);
        }

        $pathToCatalog = $basePath.$this->majorVersion.'/'.$this->minorVersion.'/'.$fileName;
        if (file_exists($pathToCatalog)) {
            $minorCatalog = json_decode(file_get_contents($pathToCatalog), true);
        }

        $pathToCatalog = $basePath.$this->majorVersion.'/'.$this->minorVersion.'/'.$this->patchVersion.'/'.$fileName;
        if (file_exists($pathToCatalog)) {
            $patchCatalog = json_decode(file_get_contents($pathToCatalog), true);
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
