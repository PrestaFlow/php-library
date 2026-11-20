<?php

namespace PrestaFlow\Library\Pages;

use Exception;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page as DomPage;
use PrestaFlow\Library\Exceptions\SkipTestException;
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
    public static $pfShopUrlDiscovery = true;

    /** prestashop.urls.pages already read, per front-office base URL. */
    protected static $pfShopUrlsCache = [];

    /**
     * prestashop.urls.pages to use instead of reading the shop; null reads it.
     * Meant for tests and for callers that already hold the table.
     */
    public $shopUrls = null;

    /**
     * The SHOP_PAGES key of this page, for goToPage($thisPage): lets a page
     * whose $url is a guess (a friendly URL) resolve to the shop's real one.
     */
    // Untyped on purpose: a project page declaring its own $shopPage must
    // not hit a property type mismatch.
    public $shopPage = null;

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

        // The only place that may navigate to read the shop's own URLs:
        // getPageURL() just answers an URL, from what is already known.
        $this->pfPrimeShopUrls($page);

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
     *  2. what the shop publishes (prestashop.urls.pages), for standard pages,
     *     when it is already known (read by goToPage(), or from the current
     *     page if it is a page of the shop) — this method never navigates;
     *  3. the library's tables (src/Urls/<locale>.json);
     *  4. for a standard page outside English, index.php?controller=<x>,
     *     which works in every language;
     *  5. the page's own value (the English friendly URL).
     */
    public function getPageURL($page, $params = null): string
    {
        $url = $this->pfResolvePageUrl($page);

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
    public function pfBaseUrl(): string
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

    protected function pfResolvePageUrl($page): string
    {
        $url = $this->pfBaseUrl();

        if (is_string($page)) {
            $override = $this->overrideFor([$page]);
            if ($override !== null) {
                return $url . $override;
            }

            $standard = isset(self::SHOP_PAGES[$page]);

            $shopUrl = $standard ? $this->pfShopUrl($page) : null;
            if ($shopUrl !== null) {
                return $shopUrl;
            }

            $defaults = $this->getDefaultUrls();
            if (isset($defaults[$page]) && is_string($defaults[$page]) && $defaults[$page] !== '') {
                return $url . $defaults[$page];
            }

            if ($standard && !$this->isEnglishLocale()) {
                // Nothing knows this page in this language: the controller
                // works whatever the language and the friendly URLs.
                return $url . 'index.php?controller=' . self::SHOP_PAGES[$page][1];
            }

            return $url . match ($page) {
                'home', 'index' => '',
                'login', 'authentification' => 'login',
                'category' => '{index}-category',
                'product' => '{index}-product.html',
                default => $page,
            };
        }

        if (is_object($page)) {
            $shopPage = $this->pfShopPageOf($page);
            $keys = array_values(array_filter([$page->url, $shopPage], 'is_string'));
            $override = $this->overrideFor($keys);
            if ($override !== null) {
                return $url . $override;
            }

            if ($shopPage !== null) {
                $shopUrl = $this->pfShopUrl($shopPage);
                if ($shopUrl !== null) {
                    return $shopUrl;
                }
            }

            $defaults = $this->getDefaultUrls();
            foreach ($keys as $key) {
                if (isset($defaults[$key]) && is_string($defaults[$key]) && $defaults[$key] !== '') {
                    return $url . $defaults[$key];
                }
            }

            if ($shopPage !== null && isset(self::SHOP_PAGES[$shopPage]) && !$this->isEnglishLocale()) {
                return $url . 'index.php?controller=' . self::SHOP_PAGES[$shopPage][1];
            }

            return $url . $page->url;
        }

        return $url;
    }

    /**
     * The SHOP_PAGES key a page object may be resolved through, or null.
     *
     * Only while the page still has the $url its library class declares: a
     * project page that extends a library page and declares its own $url
     * (a module's login, say) keeps that URL — the shop's standard one would
     * send it elsewhere.
     */
    protected function pfShopPageOf(object $page): ?string
    {
        $shopPage = $page->shopPage ?? null;
        if (!is_string($shopPage) || $shopPage === '' || !property_exists($page, 'url')) {
            return null;
        }

        try {
            $declaring = (new \ReflectionProperty($page, 'shopPage'))->getDeclaringClass()->getName();
            $default = (new \ReflectionProperty($declaring, 'url'))->getDefaultValue();
        } catch (\ReflectionException) {
            return null;
        }

        return $page->url === $default ? $shopPage : null;
    }

    /** English shops: the untranslated keys are their friendly URLs. */
    private function isEnglishLocale(): bool
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
     * Null when the page is not a standard one or the table is not known yet:
     * this never navigates (see pfLoadShopUrls()).
     *
     * $page is a SHOP_PAGES key ('login', 'prices-drop'...) or a raw
     * prestashop.urls.pages key ('prices_drop').
     */
    public function pfShopUrl(string $page): ?string
    {
        $key = self::SHOP_PAGES[$page][0] ?? $page;

        $urls = $this->shopUrls ?? $this->pfReadShopUrls();
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
     * Read prestashop.urls.pages before going to $page when resolving it
     * needs the shop and the table is not known yet. Navigates to the home
     * page when the current page is not a page of the shop.
     */
    protected function pfPrimeShopUrls($page): void
    {
        if ($this->shopUrls !== null) {
            return;
        }

        if (is_string($page)) {
            $needed = isset(self::SHOP_PAGES[$page]) && $this->overrideFor([$page]) === null;
        } elseif (is_object($page)) {
            $shopPage = $this->pfShopPageOf($page);
            $needed = $shopPage !== null && $this->overrideFor([$page->url, $shopPage]) === null;
        } else {
            $needed = false;
        }

        if ($needed) {
            $this->pfLoadShopUrls();
        }
    }

    /**
     * prestashop.urls.pages of this shop, reading it from the home page if the
     * current page is not one of the shop's. [] when it cannot be read.
     */
    public function pfLoadShopUrls(): array
    {
        $known = $this->pfReadShopUrls();
        if ($known !== [] || !self::$pfShopUrlDiscovery || !TestsSuite::pfHasBrowser()) {
            return $known;
        }

        $base = $this->pfBaseUrl();
        if (array_key_exists($base, self::$pfShopUrlsCache)) {
            return self::$pfShopUrlsCache[$base];
        }

        $map = [];
        try {
            TestsSuite::recreatePageIfContextChanged('FO');
            $this->getPage()->navigate($base)->waitForNavigation(DomPage::DOM_CONTENT_LOADED, $this->navigationTimeout);
            $map = $this->pfShopUrlsOnCurrentPage() ?? [];
        } catch (\Throwable) {
            $map = [];
        }

        // Kept even when empty: a shop that cannot be read is not loaded
        // again before every page of the suite.
        return self::$pfShopUrlsCache[$base] = $map;
    }

    /**
     * prestashop.urls.pages already known for this shop: cached, or read from
     * the current page when it is a front-office page of this shop. Never
     * navigates; [] when unknown.
     */
    protected function pfReadShopUrls(): array
    {
        if (!self::$pfShopUrlDiscovery || !TestsSuite::pfHasBrowser()) {
            return [];
        }

        $base = $this->pfBaseUrl();
        if (array_key_exists($base, self::$pfShopUrlsCache)) {
            return self::$pfShopUrlsCache[$base];
        }

        if (TestsSuite::$currentContext !== 'FO') {
            return [];
        }

        try {
            $current = (string) $this->getPage()->evaluate('location.href')->getReturnValue();
            if (!str_starts_with($current, $base)) {
                return [];
            }
            $map = $this->pfShopUrlsOnCurrentPage();
        } catch (\Throwable) {
            return [];
        }

        // Only a successful read is cached: a passive miss must not stop
        // goToPage() from loading the home page later.
        if ($map === null || $map === []) {
            return [];
        }

        return self::$pfShopUrlsCache[$base] = $map;
    }

    /** prestashop.urls.pages of the page open now, null when it has none. */
    private function pfShopUrlsOnCurrentPage(): ?array
    {
        $json = $this->getPage()->evaluate(
            '(function(){try{return (window.prestashop&&prestashop.urls&&prestashop.urls.pages)'
            . '?JSON.stringify(prestashop.urls.pages):null;}catch(e){return null;}})()'
        )->getReturnValue();

        $decoded = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($decoded)) {
            return null;
        }

        return array_filter($decoded, fn ($value) => is_string($value) && $value !== '');
    }

    /** Forget what was read from the shops (another shop, another language). */
    public static function pfClearShopUrls(): void
    {
        self::$pfShopUrlsCache = [];
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

    /**
     * Product miniatures of the current page, in page order, as
     * [['name' => ..., 'url' => ...], ...]. Theme-agnostic: every theme keeps
     * the .product-miniature / .js-product-miniature classes the core JS
     * relies on. $scope narrows the search (e.g. '#js-product-list'); when it
     * matches nothing, the whole page is searched.
     */
    public function readProductMiniatures(?string $scope = null): array
    {
        $script = '(function(scope){'
            . 'var sel=".js-product-miniature, .product-miniature";'
            . 'var root=(scope&&document.querySelector(scope))||document;'
            . 'var items=[].slice.call(root.querySelectorAll(sel)).filter(function(e){'
            . 'return !(e.parentElement&&e.parentElement.closest(sel));});'
            . 'var out=[];items.forEach(function(e){'
            . 'var a=e.querySelector(".product-title a, a.product-miniature__title, [itemprop=name] a")||e.querySelector("a[href]");'
            . 'if(!a||!a.href)return;'
            . 'var t=e.querySelector(".product-title, .product-miniature__title, [itemprop=name]")||a;'
            . 'out.push({name:(t.textContent||"").replace(/\\s+/g," ").trim(),url:a.href});});'
            . 'return JSON.stringify(out);})(' . json_encode($scope) . ')';

        try {
            $decoded = json_decode((string) $this->getPage()->evaluate($script)->getReturnValue(), true);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * Product listings worth browsing on this shop, best first: the home
     * page's "all products" link, the categories of the main menu, those of
     * the sitemap, the root category by id, then the home page itself (its
     * featured products). Leaves the browser on the home page or the sitemap.
     *
     * $allProducts false keeps only real category pages (menu, sitemap).
     */
    public function discoverListingUrls(bool $allProducts = true): array
    {
        $base = $this->pfBaseUrl();

        TestsSuite::recreatePageIfContextChanged('FO');
        $current = '';
        try {
            $current = (string) $this->getPage()->evaluate('location.href')->getReturnValue();
        } catch (\Throwable) {
        }
        if (rtrim($current, '/') !== rtrim($base, '/')) {
            $this->goToUrlInPlace($base);
        }

        $urls = [];
        if ($allProducts) {
            $urls = $this->pfReadLinks('a.all-product-link, .featured-products a.all-product-link, .ps-featuredproducts .module-products__buttons a');
        }

        $categories = $this->readCategoryLinks();
        if ($categories === []) {
            $sitemap = $this->pfShopUrl('sitemap') ?? $this->getPageURL('sitemap');
            try {
                $this->goToUrlInPlace($sitemap);
                $categories = $this->readCategoryLinks();
            } catch (\Throwable) {
            }
        }
        $urls = [...$urls, ...$categories];

        if ($allProducts) {
            // id_category 2 is the root ("Home") category of every install.
            $urls[] = $base . 'index.php?id_category=2&controller=category';
            $urls[] = $base;
        }

        return array_values(array_unique($urls));
    }

    /**
     * Whether the product page open now lets a buyer add the product as it
     * is: an add-to-cart button (core's data-button-action, else
     * $buttonSelector), enabled — PrestaShop disables it when the product is
     * out of stock, not available for order, or waits for a required
     * customization — and no required customization field.
     */
    protected function pfIsAddableToCartHere(string $buttonSelector): bool
    {
        $script = sprintf(
            '(function(sel){var b=document.querySelector(\'[data-button-action="add-to-cart"]\')||document.querySelector(sel);'
            . 'if(!b)return false;'
            . 'if(b.disabled||b.hasAttribute("disabled")||b.classList.contains("disabled"))return false;'
            . 'if(document.querySelector(".product-customization [required]"))return false;'
            . 'return true;})(%s)',
            json_encode($buttonSelector)
        );

        try {
            return $this->getPage()->evaluate($script)->getReturnValue() === true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * From the listing open now, open its products in turn and stop on the
     * first one that can be added to the cart: answers its miniature
     * (['name' => ..., 'url' => ...]) and leaves the browser on it. Null when
     * none qualifies within $maxProducts product pages ($seen counts and
     * skips the ones already tried, across listings).
     */
    protected function pfOpenFirstPurchasable(int $maxProducts, array &$seen, string $buttonSelector): ?array
    {
        foreach ($this->readProductMiniatures('#js-product-list') as $miniature) {
            $url = $miniature['url'] ?? null;
            if (!is_string($url) || isset($seen[$url])) {
                continue;
            }
            if (count($seen) >= $maxProducts) {
                return null;
            }
            $seen[$url] = true;

            try {
                $this->goToUrlInPlace($url);
            } catch (\Throwable) {
                continue;
            }

            if ($this->pfIsAddableToCartHere($buttonSelector)) {
                return ['name' => (string) ($miniature['name'] ?? ''), 'url' => $url];
            }
        }

        return null;
    }

    /** Links to category pages on the current page (main menu, sitemap tree). */
    public function readCategoryLinks(): array
    {
        return $this->pfReadLinks('[id^="category-"] > a[href], a[id^="category-"][href], [id^="category-"] a[href]');
    }

    /**
     * id_country => ISO code of the countries a PrestaShop install creates
     * with fixed ids (same ids from 1.7 to 9). Lets a test know which country
     * a form preselected without reading its translated name.
     */
    public const INSTALL_COUNTRY_IDS = [
        1 => 'DE', 2 => 'AT', 3 => 'BE', 4 => 'CA', 6 => 'ES', 8 => 'FR', 10 => 'IT',
        12 => 'LU', 13 => 'NL', 14 => 'PL', 15 => 'PT', 17 => 'GB', 19 => 'CH', 21 => 'US',
    ];

    /** A postcode each country's zip_code_format accepts. */
    public const POSTCODES = [
        'FR' => '75002', 'BE' => '1000', 'CH' => '1200', 'DE' => '10115', 'AT' => '1010',
        'ES' => '28001', 'IT' => '00184', 'GB' => 'SW1A 1AA', 'US' => '10001', 'CA' => 'K1A 0B1',
        'NL' => '1012 AB', 'PT' => '1000-001', 'LU' => '1009', 'PL' => '00-001',
    ];

    /** Valid postcode for an ISO country code; null for a country not in POSTCODES. */
    public static function postcodeFor(?string $iso): ?string
    {
        return self::POSTCODES[strtoupper((string) $iso)] ?? null;
    }

    /**
     * The postcode to type in an address form: $given when set, else one
     * valid for the country the form shows. A country we know no format for
     * skips the test (and the suite) when the form requires a postcode,
     * rather than typing one the shop would refuse.
     */
    protected function pfPostcodeForForm(?string $given, string $countrySelector, string $postcodeSelector): string
    {
        if ($given !== null && $given !== '') {
            return $given;
        }

        $postcode = self::postcodeFor($this->pfSelectedCountryIso($countrySelector));
        if ($postcode !== null) {
            return $postcode;
        }

        $required = $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return !!e&&(e.required||e.hasAttribute("required"));})()',
            json_encode($postcodeSelector)
        ))->getReturnValue();

        if ($required === true) {
            throw new SkipTestException(
                'No known postcode format for the country this shop preselects: set the addressPostcode param'
                . ' (or addressCountry to a country such as France).',
                skipRemaining: true
            );
        }

        return '';
    }

    /** ISO code of an install country id, null for a country added later. */
    public static function countryIsoFromId(mixed $idCountry): ?string
    {
        return is_numeric($idCountry) ? (self::INSTALL_COUNTRY_IDS[(int) $idCountry] ?? null) : null;
    }

    /** ISO code of the country an address form currently shows, when known. */
    protected function pfSelectedCountryIso(string $countrySelector): ?string
    {
        try {
            $value = $this->getPage()->evaluate(sprintf(
                '(function(){var s=document.querySelector(%s);return s?s.value:null;})()',
                json_encode($countrySelector)
            ))->getReturnValue();
        } catch (\Throwable) {
            return null;
        }

        return self::countryIsoFromId($value);
    }

    /**
     * The state of an address form: $label when given, otherwise, when the
     * country has states and none is chosen yet, its first real option.
     */
    protected function pfFillState(string $stateSelector, ?string $label = null): void
    {
        if ($label !== null && $label !== '') {
            $this->selectOption($stateSelector, $label);

            return;
        }

        $this->getPage()->evaluate(sprintf(
            '(function(){var s=document.querySelector(%s);if(!s||(s.value&&s.value!=="0"))return;'
            . 'var o=[].slice.call(s.options).find(function(x){return x.value&&x.value!=="0";});'
            . 'if(!o)return;s.value=o.value;s.dispatchEvent(new Event("change",{bubbles:true}));})()',
            json_encode($stateSelector)
        ));
    }

    /**
     * Offline payment modules a scenario may pay with: no redirection to a
     * payment provider, no card, nothing really charged.
     */
    public const OFFLINE_PAYMENT_MODULES = ['ps_wirepayment', 'ps_checkpayment', 'ps_cashondelivery'];

    /** Skip reason when no product of the shop can be added to the cart. */
    public const NO_PURCHASABLE_PRODUCT = 'No product of this shop can be added to the cart as it is'
        . ' (in stock, available for order, no required customization): set the productUrl param.';

    /** Skip reason when the shop offers none of the payment modules a scenario may use. */
    public static function pfNoPaymentMethodReason(array $modules): string
    {
        return sprintf(
            'No offline payment method offered by the shop (looked for: %s). Set the paymentModules param'
            . ' to a payment module that does not redirect to a payment provider.',
            $modules === [] ? 'none' : implode(', ', $modules)
        );
    }

    /** A carrier id as a suite passes it (null, '', int or numeric string) => ?int. */
    public static function pfCarrierId(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /** Payment modules as a suite passes them (list, one name, null) => list; null = the offline ones. */
    public static function pfPaymentModules(mixed $modules): array
    {
        if ($modules === null || $modules === '') {
            return self::OFFLINE_PAYMENT_MODULES;
        }

        return array_values(array_filter((array) $modules, fn ($module) => is_string($module) && $module !== ''));
    }

    /**
     * Pick the first payment option whose module is in $modules, or skip the
     * test and the rest of the suite with a reason: never pay for real.
     */
    protected function pfPayWithOrSkip(string $optionSelector, mixed $modules): string
    {
        $modules = self::pfPaymentModules($modules);

        $module = $this->pfClickPaymentOption($optionSelector, $modules);
        if ($module === null) {
            throw new SkipTestException(self::pfNoPaymentMethodReason($modules), skipRemaining: true);
        }

        return $module;
    }

    /**
     * Expect this page's title to contain $expected, ignoring case (themes
     * upper-case headings in CSS) and a trailing "..." (truncated miniature
     * names). Does nothing when $expected is null or '': an optional check a
     * scenario can always call.
     */
    public function expectTitleContains(mixed $expected): void
    {
        $expected = is_scalar($expected) ? trim(rtrim(trim((string) $expected), '.…')) : '';
        if ($expected === '') {
            return;
        }

        Expect::that(mb_strtolower((string) $this->getTitle()))->contains(mb_strtolower($expected));
    }

    /**
     * Click the first payment option, in page order, whose data-module-name
     * is in $modules, and answer that module's name; null when the shop offers
     * none of them (nothing is clicked then).
     */
    protected function pfClickPaymentOption(string $optionSelector, array $modules): ?string
    {
        $script = sprintf(
            '(function(sel,modules){var o=[].slice.call(document.querySelectorAll(sel)).find(function(i){'
            . 'return modules.indexOf(i.getAttribute("data-module-name"))!==-1;});'
            . 'if(!o)return null;o.click();return o.getAttribute("data-module-name");})(%s,%s)',
            json_encode($optionSelector),
            json_encode(array_values($modules))
        );

        $module = $this->getPage()->evaluate($script)->getReturnValue();

        return is_string($module) && $module !== '' ? $module : null;
    }

    /** Distinct absolute hrefs of the links matching $selector, in page order. */
    protected function pfReadLinks(string $selector): array
    {
        $script = sprintf(
            '(function(){var out=[];[].slice.call(document.querySelectorAll(%s)).forEach(function(a){'
            . 'if(a.href&&a.href.indexOf("http")===0&&out.indexOf(a.href)<0){out.push(a.href);}});'
            . 'return JSON.stringify(out);})()',
            json_encode($selector)
        );

        try {
            $decoded = json_decode((string) $this->getPage()->evaluate($script)->getReturnValue(), true);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
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
