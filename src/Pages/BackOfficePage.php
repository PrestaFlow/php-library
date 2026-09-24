<?php

namespace PrestaFlow\Library\Pages;

use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Resolvers\Urls;
use PrestaFlow\Library\Tests\TestsSuite;
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
            // Present on every authenticated back-office page of 1.7, 8 and 9
            // alike, and on none of their login pages. See
            // getPrestashopVersion() for why that asymmetry is the whole point.
            'shopVersionBlock' => '#shop_version',
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
        // Bascule contextuelle : ne recrée la page que si on arrive depuis le
        // FrontOffice (ou au 1er appel) — évite un close+createPage inutile
        // pour deux navigations BO consécutives.
        TestsSuite::recreatePageIfContextChanged('BO');
        $this->getPage()->navigate($url)->waitForNavigation(\HeadlessChromium\Page::DOM_CONTENT_LOADED);
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

    /**
     * Put the back office in a single shop context.
     *
     * With the multistore feature on, the back office starts in an "All stores"
     * context, and several pages refuse to render their form there — the
     * ps_onepagecheckout configuration among them, which shows "Note that this
     * page is available in a single shop context only" and nothing else. A test
     * that does not switch context fails on a missing selector with no clue as
     * to why.
     *
     * setShopContext is PrestaShop's own switch and persists in the employee
     * session, so one call covers every page visited afterwards. On a shop
     * without multistore it is simply ignored.
     */
    /**
     * The version the shop reports about itself, or null when it does not.
     *
     * `#shop_version` is the only node that actually holds a version across
     * 1.7.8, 8.2 and 9.2 — the header info bar writes it on every
     * authenticated back-office page, and 9 additionally repeats it in the
     * menu logo block. No login page carries it on 8 or 9 (1.7's does, in
     * `#login-header > div.text-center`, but reading version-dependent nodes
     * is what produced the defect this replaces), so a caller that has not
     * logged in yet gets null rather than whatever text happens to sit nearby.
     *
     * Null is a real answer here: "this page does not state a version". The
     * previous implementation read `#login_form h4` and returned the login
     * form's required-field marker on 9 ("* PrestaShop") or the shop name on
     * 8 and 1.7 ("PrestaShop") — non-empty strings that satisfied every
     * isNotEmpty() guard pointed at them while stating nothing about the
     * version. A method that cannot answer must not answer falsely.
     */
    public function getPrestashopVersion(): ?string
    {
        $value = $this->getTextContent($this->getSelector('shopVersionBlock'), 1, true, 2000);

        if (!is_string($value)) {
            return null;
        }

        $value = ltrim(trim($value), 'vV');

        // Structural guard, not a format check: anything that does not begin
        // like a version number ("9.2.0", "1.7.8.11", "9.0.0-rc.1.test") is a
        // label that drifted into the node, and a label is not an answer.
        if (!preg_match('/^\d+\.\d+/', $value)) {
            return null;
        }

        return $value;
    }

    public function setSingleShopContext(int $idShop = 1): void
    {
        $url = $this->getGlobals()['BO']['URL'];
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }

        $this->getPage()
            ->navigate($url . '?setShopContext=s-' . $idShop)
            ->waitForNavigation(\HeadlessChromium\Page::DOM_CONTENT_LOADED);
    }

    public function goToSubMenu(string $parentSelector, string $linkSelector): void
    {
        // The sidebar sub-link is an <a> that may live inside a collapsed section
        // (not clickable by coordinates) and whose parent is itself a navigation
        // link (clicking it navigates away too early). Read the sub-link's
        // resolved href and navigate to it directly, then wait for the page.
        $page = $this->getPage();

        try {
            $page->waitUntilContainsElement($linkSelector, 10000);
        } catch (\Throwable $e) {
            // fall through; the evaluate below will report a null href
        }

        // The menu entry is often a <li> wrapping the real <a>; read the anchor's
        // href (or the element's own href when the selector already targets a link).
        $sel = json_encode($linkSelector);
        $href = $page->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s+" a")||document.querySelector(%s);return e&&e.href?e.href:null;})()',
            $sel,
            $sel
        ))->getReturnValue();

        if (is_string($href) && $href !== '') {
            $page->navigate($href)->waitForNavigation();
        }
    }
}
