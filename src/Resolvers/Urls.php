<?php

namespace PrestaFlow\Library\Resolvers;

use PrestaFlow\Library\Traits\Locale;
use PrestaFlow\Library\Utils\TestsPath;

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

        $basePath = __DIR__ . '/../Urls/';
        $fileName = $this->getLocale() . '.json';

        $defaultCatalog = TestsPath::readJson($basePath . $fileName);
        // Un fichier du projet invalide vaut [] : l'ancien code testait $defaultCatalog au lieu de
        // $customCatalog, et le null restait dans la fusion (TypeError au déballage).
        $customCatalog = TestsPath::readJson(TestsPath::dir('Urls', $this->globals ?? []) . $fileName);

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
