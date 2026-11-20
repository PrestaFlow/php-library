<?php

namespace PrestaFlow\Library\Pages;

use Exception;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page as DomPage;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Resolvers\Translations;
use PrestaFlow\Library\Resolvers\Urls;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Traits\Locale;

class FrontOfficePage extends CommonPage
{
    use Translations;
    use Urls;
    use Locale;

    // Redeclared here, not only through the trait: before PHP 8.3 a trait's
    // static property used again in a child class stays shared with the
    // parent (CommonPage), so the back office and the front office would
    // overwrite each other's language (BO_LOCALE).
    protected static $localeInit = null;
    protected static string $locale = 'en';

    /**
     * Standard pages whose URL the shop publishes in prestashop.urls.pages,
     * by the names callers use: page key => [prestashop.urls.pages key, controller].
     * The controller is the language-independent last resort.
     */
    public const SHOP_PAGES = [
        'registration' => ['register', 'registration'],
        'register' => ['register', 'registration'],
        'login' => ['authentication', 'authentication'],
        'authentication' => ['authentication', 'authentication'],
        'authentification' => ['authentication', 'authentication'],
        'cart' => ['cart', 'cart'],
        'order' => ['order', 'order'],
        'checkout' => ['order', 'order'],
        'password' => ['password', 'password'],
        'contact' => ['contact', 'contact'],
        'search' => ['search', 'search'],
        'history' => ['history', 'history'],
        'order-history' => ['history', 'history'],
        'addresses' => ['addresses', 'addresses'],
        'address' => ['address', 'address'],
        'identity' => ['identity', 'identity'],
        'prices-drop' => ['prices_drop', 'prices-drop'],
        'prices_drop' => ['prices_drop', 'prices-drop'],
        'new-products' => ['new_products', 'new-products'],
        'new_products' => ['new_products', 'new-products'],
        'best-sales' => ['best_sales', 'best-sales'],
        'best_sales' => ['best_sales', 'best-sales'],
        'guest-tracking' => ['guest_tracking', 'guest-tracking'],
        'guest_tracking' => ['guest_tracking', 'guest-tracking'],
        'my-account' => ['my_account', 'my-account'],
        'my_account' => ['my_account', 'my-account'],
        'order-follow' => ['order_follow', 'order-follow'],
        'order-slip' => ['order_slip', 'order-slip'],
        'discount' => ['discount', 'discount'],
        'sitemap' => ['sitemap', 'sitemap'],
        'stores' => ['stores', 'stores'],
        'manufacturer' => ['manufacturer', 'manufacturer'],
        'supplier' => ['supplier', 'supplier'],
    ];

    /** Off: getPageURL() never asks the shop (tables and page values only). */
    public static bool $shopUrlDiscovery = true;

    /** prestashop.urls.pages already read, per front-office base URL. */
    protected static array $shopUrlsCache = [];

    /**
     * prestashop.urls.pages to use instead of reading the shop; null reads it.
     * Meant for tests and for callers that already hold the table.
     */
    public ?array $shopUrls = null;

    /**
     * The SHOP_PAGES key of this page, for goToPage($thisPage): lets a page
     * whose $url is a guess (a friendly URL) resolve to the shop's real one.
     */
    public ?string $shopPage = null;

    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->initVersion(patchVersion: $patchVersion, globals: $globals);
        $this->globals = $globals;
        $this->customs = array_merge($this->customs, $customs);
        $this->initLocale(locale: $locale);

        $selectors = [
            'pageTitle' => 'h1',
            'maintenanceBlock' => '#content.page-maintenance',
            'desktopLogo' => '#_desktop_logo',
            'userInfoLink' => '#_desktop_user_info',
            'accountLink' => '#_desktop_user_info .user-info a[href*=\'/my-account\']',
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

        // close()+createPage() peut floter au démarrage à froid : l'énumération des
        // targets pendant qu'une page se ferme/crée lève « Call to a member function
        // getTargetInfo() on null ». On réessaie la séquence quelques fois.
        $attempts = 3;
        for ($try = 1; ; $try++) {
            try {
                TestsSuite::recreatePageIfContextChanged('FO');
                $this->getPage()->navigate($url)->waitForNavigation(DomPage::DOM_CONTENT_LOADED);
                break;
            } catch (\Throwable $e) {
                if ($try >= $attempts) {
                    throw $e;
                }
                usleep(800000);
            }
        }

        // Check anti-« [Debug] This page has moved » : évalue une expression JS
        // au lieu de faire un snapshot DOM complet via getTextContent('body') —
        // qui coûtait ~2s par navigation sur une home PS chargée.
        try {
            $hasDebugMoved = $this->getPage()
                ->evaluate("!!(document.body && document.body.innerText && document.body.innerText.indexOf('[Debug] This page has moved') !== -1)")
                ->getReturnValue();
            if ($hasDebugMoved) {
                Expect::setWarning('debug-mode');
                $this->click('a');
                $this->waitForPageLoaded();
            }
        } catch (OperationTimedOut | Exception $e) {
            Expect::setWarning('debug-mode');
            $this->click('a');
            $this->waitForPageLoaded();
        }
    }

    /**
     * URL of a page, by priority:
     *  1. an override of the project (Tests/Urls/<locale>.json, customs['urls']);
     *  2. what the shop publishes (prestashop.urls.pages), for standard pages;
     *  3. the library's tables (src/Urls/<locale>.json), then the page's own value;
     *  4. index.php?controller=<x> for a standard page nothing else knows.
     */
    public function getPageURL($page, $params = null): string
    {
        $url = $this->resolvePageURL($page);

        // A scalar $params is the common call shape — goToPage('category', 3),
        // goToPage('product', $productId) — and means the single placeholder the
        // default templates carry: {index}. Substitution used to be gated on
        // is_array(), so every scalar call left '{index}' in the URL and it got
        // percent-encoded into %7Bindex%7D. Normalise to the array form first.
        if (is_scalar($params) && $params !== '') {
            $params = ['index' => $params];
        }

        if (is_array($params) && count($params) > 0) {
            foreach ($params as $key => $value) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        }

        return $url;
    }

    /** Front-office base URL, with the language prefix when the shop uses one. */
    public function getBaseURL(): string
    {
        $url = $this->getGlobals()['FO']['URL'];
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }
        $useIsoCode = $this->getGlobals()['PREFIX_LOCALE'] ?? false;
        if ($useIsoCode) {
            $url .= $this->getLocale() . '/';
        }

        return $url;
    }

    protected function resolvePageURL($page): string
    {
        $url = $this->getBaseURL();

        if (is_string($page)) {
            $override = $this->overrideFor([$page]);
            if ($override !== null) {
                return $url . $override;
            }

            $shopUrl = isset(self::SHOP_PAGES[$page]) ? $this->shopUrl($page) : null;
            if ($shopUrl !== null) {
                return $shopUrl;
            }

            $pages = ['home', 'index', 'login', 'authentification', 'prices-drop', 'category', 'product'];
            $pageUrl = $this->url($page);
            if ($pageUrl !== '' && $pageUrl !== null && !in_array($pageUrl, $pages)) {
                if ($pageUrl === $page && isset(self::SHOP_PAGES[$page]) && !$this->isEnglishFriendlyUrl($page)) {
                    // Nothing knows this page in this language: the controller
                    // works whatever the language and the friendly URLs.
                    return $url . 'index.php?controller=' . self::SHOP_PAGES[$page][1];
                }

                return $url . $pageUrl;
            }

            return $url . match ($page) {
                'home', 'index' => '',
                'login', 'authentification' => 'login',
                'prices-drop' => 'prices-drop',
                'category' => '{index}-category',
                'product' => '{index}-product.html',
                default => ''
            };
        }

        if (is_object($page)) {
            $keys = array_values(array_filter([$page->url, $page->shopPage ?? null], 'is_string'));
            $override = $this->overrideFor($keys);
            if ($override !== null) {
                return $url . $override;
            }

            if (is_string($page->shopPage ?? null)) {
                $shopUrl = $this->shopUrl($page->shopPage);
                if ($shopUrl !== null) {
                    return $shopUrl;
                }
            }

            $pageUrl = $this->url($page->url);

            return $url . (($pageUrl !== '' && $pageUrl !== null) ? $pageUrl : $page->url);
        }

        return $url;
    }

    /**
     * Whether the untranslated key is itself the page's friendly URL, which is
     * the case on an English shop: keep it rather than the controller form.
     */
    private function isEnglishFriendlyUrl(string $page): bool
    {
        return str_starts_with($this->getLocale(), 'en');
    }

    private function overrideFor(array $keys): ?string
    {
        $overrides = $this->getOverrideUrls();
        foreach ($keys as $key) {
            if (isset($overrides[$key]) && is_string($overrides[$key]) && $overrides[$key] !== '') {
                return $overrides[$key];
            }
        }

        return null;
    }

    /**
     * Absolute URL the shop itself publishes for a standard page, in the
     * current language (prestashop.urls.pages, PrestaShop 1.7 to 9, any theme).
     * Null when the page is not a standard one or the shop could not be read.
     *
     * $page is a SHOP_PAGES key ('login', 'prices-drop'...) or a raw
     * prestashop.urls.pages key ('prices_drop').
     */
    public function shopUrl(string $page): ?string
    {
        $key = self::SHOP_PAGES[$page][0] ?? $page;

        $urls = $this->shopUrls ?? $this->readShopUrls();
        $url = $urls[$key] ?? null;
        if (!is_string($url) || $url === '') {
            return null;
        }

        // The cart controller only renders the cart with action=show; without
        // it, it answers with a redirect.
        if ($key === 'cart' && !str_contains($url, 'action=')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'action=show';
        }

        return $url;
    }

    /**
     * prestashop.urls.pages, read once per base URL: from the current page when
     * it is already a page of this shop, otherwise from its home page. Only
     * while a run drives a browser; any failure answers [] (the tables apply).
     */
    protected function readShopUrls(): array
    {
        if (!self::$shopUrlDiscovery || !TestsSuite::hasBrowser()) {
            return [];
        }

        $base = $this->getBaseURL();
        if (array_key_exists($base, self::$shopUrlsCache)) {
            return self::$shopUrlsCache[$base];
        }

        $script = '(function(){try{return (window.prestashop&&prestashop.urls&&prestashop.urls.pages)'
            . '?JSON.stringify(prestashop.urls.pages):null;}catch(e){return null;}})()';

        $map = [];
        try {
            $json = null;
            if (TestsSuite::$currentContext === 'FO') {
                $current = (string) $this->getPage()->evaluate('location.href')->getReturnValue();
                if (str_starts_with($current, $base)) {
                    $json = $this->getPage()->evaluate($script)->getReturnValue();
                }
            }

            if (!is_string($json)) {
                TestsSuite::recreatePageIfContextChanged('FO');
                $this->getPage()->navigate($base)->waitForNavigation(DomPage::DOM_CONTENT_LOADED);
                $json = $this->getPage()->evaluate($script)->getReturnValue();
            }

            $decoded = is_string($json) ? json_decode($json, true) : null;
            if (is_array($decoded)) {
                $map = array_filter($decoded, fn ($value) => is_string($value) && $value !== '');
            }
        } catch (\Throwable) {
            $map = [];
        }

        return self::$shopUrlsCache[$base] = $map;
    }

    /** Forget what was read from the shops (another shop, another language). */
    public static function clearShopUrls(): void
    {
        self::$shopUrlsCache = [];
    }

    public function goToUrl(string $url)
    {
        // Override intentionnel de CommonPage::goToUrl (simple navigate) : recrée
        // la page pour partir sur une session FrontOffice propre (utile pour ne
        // pas hériter des cookies BackOffice) avant d'atteindre une URL absolue.
        // recreatePage() se charge de réappliquer les en-têtes persistants.
        TestsSuite::recreatePageIfContextChanged('FO');
        $this->getPage()->navigate($url)->waitForNavigation(DomPage::DOM_CONTENT_LOADED);
    }

    public function goToUrlInPlace(string $url)
    {
        // Navigation sans recréation de page : conserve la session (cookies, JS
        // state) de la page courante. À utiliser pour un scénario multi-étapes
        // qui reste en FrontOffice (ex. home → fiche produit dont l'URL est lue
        // depuis la home). Préférer goToUrl() quand on veut repartir d'une
        // session propre (ex. après du BackOffice).
        parent::goToUrl($url);
    }

    public function getTitle()
    {
        return $this->getTextContent($this->selector('pageTitle'));
    }

    public function getPageTitle()
    {
        return $this->getTitle();
    }
}
