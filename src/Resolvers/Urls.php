<?php

namespace PrestaFlow\Library\Resolvers;

use PrestaFlow\Library\Traits\Locale;
use PrestaFlow\Library\Traits\Version;

trait Urls
{
    use Locale;

    public $urls = null;

    public function initUrls(string $locale)
    {
        $this->setLocale($locale);
    }

    public function url($page)
    {
        if (is_null($this->urls)) {
            $this->urls = $this->getUrls();
        }

        if (isset($this->urls[$page])) {
            return $this->urls[$page];
        }

        return $page;
    }

    public function getUrls()
    {
        if (!$this->localeIsInit()) {
            $this->initLocale($this->getGlobal('LOCALE'));
        }

        $customPath = __DIR__.'/../../../../../Tests/Urls/';
        $basePath = __DIR__.'/../Urls/';
        $fileName = $this->getLocale().'.json';
        $defaultCatalog = [];
        $customCatalog = [];
        $mergedCatalog = [];

        $pathToCatalog = $basePath.$fileName;
        if (file_exists($pathToCatalog)) {
            $defaultCatalog = json_decode(file_get_contents($pathToCatalog), true);
        }

        $pathToCatalog = $customPath.$fileName;
        if (file_exists($pathToCatalog)) {
            $customCatalog = json_decode(file_get_contents($pathToCatalog), true);
        }

        $mergedCatalog = [
            ...$defaultCatalog,
            ...$customCatalog,
        ];

        return $mergedCatalog;
    }
}
