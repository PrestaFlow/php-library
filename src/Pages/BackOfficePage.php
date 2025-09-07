<?php

namespace PrestaFlow\Library\Pages;

use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Traits\Locale;

class BackOfficePage extends CommonPage
{
    use Locale;

    public function __construct(string $locale, string $patchVersion, array $globals)
    {
        $this->globals = $globals;
        $this->initLocale(locale: $locale);

        $selectors = [
        ];

        $this->selectors = $this->getSelectors(selectors: $selectors);

        $this->messages = $this->getMessages();

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals);
    }

    public function goToPage($page = null)
    {
        if ($page === null) {
            $page = 'login';
        }

        $url = $this->getPageURL($page);
        $this->getPage()->navigate($url)->waitForNavigation();
    }

    public function getPageURL($page, $index = null): string
    {
        $url = $this->getGlobals()['BO']['URL'];
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }

        if (is_string($page)) {
            $pageUrl = $this->url($page);
            if ($pageUrl !== '' && $pageUrl !== null) {
                $url .= $pageUrl;
            } else {
                $url .= match ($page) {
                    'login', 'index' => '',
                    default => ''
                };
            }
        } else if (is_object($page)) {
            $pageUrl = $this->url($page->url);
            if ($pageUrl !== '' && $pageUrl !== null) {
                $url .= $pageUrl;
            } else {
                $url .= $page->url;
            }
        }

        if (is_int($index)) {
            $url = str_replace('{index}', $index, $url);
        }

        return $url;
    }
}
