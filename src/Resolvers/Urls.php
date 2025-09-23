<?php

namespace PrestaFlow\Library\Resolvers;

use PrestaFlow\Library\Traits\Locale;

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

        return null;
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

        $specificUrls = [];
        if (is_array($this->customs['urls'])) {
            $specificUrls = $this->customs['urls'];
        }

        $mergedCatalog = [
            ...$defaultCatalog,
            ...$customCatalog,
            ...$specificUrls,
        ];

        return $mergedCatalog;
    }
}
