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
        return [
            ...$this->getDefaultUrls(),
            ...$this->getOverrideUrls(),
        ];
    }

    /**
     * The library's own table (src/Urls/<locale>.json): friendly URLs of a
     * stock PrestaShop in that language, a guess about the user's shop.
     */
    public function getDefaultUrls(): array
    {
        if (!$this->localeIsInit()) {
            $this->initLocale($this->getGlobal('LOCALE'));
        }

        return TestsPath::readJson(__DIR__ . '/../Urls/' . $this->getLocale() . '.json');
    }

    /**
     * What the user declared for their shop: the project's Tests/Urls/<locale>.json,
     * then the suite's customs['urls']. Always wins over anything discovered.
     */
    public function getOverrideUrls(): array
    {
        if (!$this->localeIsInit()) {
            $this->initLocale($this->getGlobal('LOCALE'));
        }

        // Un fichier du projet invalide vaut [] : l'ancien code testait $defaultCatalog au lieu de
        // $customCatalog, et le null restait dans la fusion (TypeError au déballage).
        $customCatalog = TestsPath::readJson(TestsPath::dir('Urls', $this->globals ?? []) . $this->getLocale() . '.json');

        $specificUrls = [];
        if (is_array($this->customs['urls'] ?? null)) {
            $specificUrls = $this->customs['urls'];
        }

        return [
            ...$customCatalog,
            ...$specificUrls,
        ];
    }
}
