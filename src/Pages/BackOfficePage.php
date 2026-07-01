<?php

namespace PrestaFlow\Library\Pages;

use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Resolvers\Urls;
use PrestaFlow\Library\Traits\Locale;

class BackOfficePage extends CommonPage
{
    use Locale;
    use Urls;

    public string $menuSelector = '';
    public string $parentMenuSelector = '';

    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->globals = $globals;
        $this->customs = array_merge($this->customs, $customs);
        $this->initLocale(locale: $locale);

        $selectors = [
        ];

        $this->selectors = $this->getSelectors(selectors: $selectors);

        $this->messages = $this->getMessages();

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals, customs: $customs);
    }

    public function goToPage($page = null, $params = null)
    {
        if ($page === null) {
            $page = $this;
        }

        $url = $this->getPageURL($page, $params);
        $this->getPage()->navigate($url)->waitForNavigation();
    }

    public function getPageURL($page, $params = null): string
    {
        $url = $this->getGlobals()['BO']['URL'];
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }

        if (is_string($page)) {
            $pageUrl = $this->url($page);
            if ($pageUrl !== '' && $pageUrl !== null && !in_array($pageUrl, ['login', 'index'])) {
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

        if (is_array($params) && count($params) > 0) {
            foreach ($params as $key => $value) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        }

        return $url;
    }

    public function goToSubMenu(string $parentSelector, string $linkSelector): void
    {
        // The sidebar sub-link is an <a> that may live inside a collapsed section
        // (not clickable by coordinates) and whose parent is itself a navigation
        // link (clicking it navigates away too early). Read the sub-link's
        // resolved href and navigate to it directly, then wait for the page.
        try {
            $this->getPage()->waitUntilContainsElement($linkSelector, 10000);
        } catch (\Throwable $e) {
            // fall through; the evaluate below will report a null href
        }

        // The menu entry is often a <li> wrapping the real <a>; read the anchor's
        // href (or the element's own href when the selector already targets a link).
        $sel = json_encode($linkSelector);
        $href = $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s+" a")||document.querySelector(%s);return e&&e.href?e.href:null;})()',
            $sel,
            $sel
        ))->getReturnValue();

        if (is_string($href) && $href !== '') {
            $this->getPage()->navigate($href)->waitForNavigation();
        }
    }
}
