<?php

namespace PrestaFlow\Tests\Unit\Pages\ShopUrlFixtures {

    use PrestaFlow\Library\Pages\FrontOfficePage;

    /**
     * Resolution without a browser: the shop's prestashop.urls.pages table is
     * injected through $shopUrls, the way a run would have read it.
     */
    class Page extends FrontOfficePage
    {
        public function __construct(array $globals, string $locale = 'en', array $urls = [])
        {
            $this->globals = $globals;
            $this->initLocale(locale: $locale);
            $this->setLocale($locale);
            $this->customs['urls'] = $urls;
        }

        public function getGlobals(): array
        {
            return $this->globals;
        }
    }

    class PricesDropLike extends FrontOfficePage
    {
        public string $url = 'promotions';
        public $shopPage = 'prices-drop';

        public function __construct()
        {
        }
    }

    class LoginLike extends FrontOfficePage
    {
        public string $url = 'login';
        public $shopPage = 'login';

        public function __construct()
        {
        }
    }

    /** A project page reusing a library page for a module's own login. */
    class ModuleLogin extends LoginLike
    {
        public string $url = 'module/mymodule/login';
    }
}

namespace PrestaFlow\Tests\Unit\Pages {

    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Pages\FrontOfficePage;
    use PrestaFlow\Tests\Unit\Pages\ShopUrlFixtures\Page as UrlPage;
    use PrestaFlow\Tests\Unit\Pages\ShopUrlFixtures\LoginLike;
    use PrestaFlow\Tests\Unit\Pages\ShopUrlFixtures\ModuleLogin;
    use PrestaFlow\Tests\Unit\Pages\ShopUrlFixtures\PricesDropLike;

    /**
     * getPageURL(): a project override first, then what the shop publishes,
     * then the library's tables and the page's own value, then the controller.
     */
    final class ShopUrlResolutionTest extends TestCase
    {
        private const SHOP = [
            'authentication' => 'https://shop.test/fr/connexion',
            'register' => 'https://shop.test/fr/inscription',
            'cart' => 'https://shop.test/fr/panier',
            'order' => 'https://shop.test/fr/commande',
            'prices_drop' => 'https://shop.test/fr/promotions',
            'contact' => 'https://shop.test/fr/nous-contacter',
        ];

        private string $testsPath;

        protected function setUp(): void
        {
            $this->testsPath = sys_get_temp_dir() . '/pf-shop-urls-' . bin2hex(random_bytes(4));
            mkdir($this->testsPath . '/Urls', 0777, true);
        }

        protected function tearDown(): void
        {
            @unlink($this->testsPath . '/Urls/fr.json');
            @unlink($this->testsPath . '/Urls/en.json');
            @rmdir($this->testsPath . '/Urls');
            @rmdir($this->testsPath);
        }

        private function page(string $locale = 'fr', ?array $shop = self::SHOP, array $customs = []): UrlPage
        {
            $page = new UrlPage([
                'FO' => ['URL' => 'https://shop.test/'],
                'LOCALE' => $locale,
                'PREFIX_LOCALE' => false,
                'TESTS_PATH' => $this->testsPath,
            ], $locale, $customs);
            $page->shopUrls = $shop;

            return $page;
        }

        public function test_the_shop_url_wins_over_the_library_table(): void
        {
            // src/Urls/fr.json says "connexion" too, but the shop is the authority.
            $this->assertSame('https://shop.test/fr/connexion', $this->page()->getPageURL('login'));
            $this->assertSame('https://shop.test/fr/nous-contacter', $this->page()->getPageURL('contact'));
            $this->assertSame('https://shop.test/fr/inscription', $this->page()->getPageURL('registration'));
        }

        public function test_the_cart_gets_action_show(): void
        {
            $this->assertSame('https://shop.test/fr/panier?action=show', $this->page()->getPageURL('cart'));

            $page = $this->page(shop: ['cart' => 'https://shop.test/index.php?controller=cart']);
            $this->assertSame('https://shop.test/index.php?controller=cart&action=show', $page->getPageURL('cart'));
        }

        public function test_a_project_override_file_wins_over_the_shop(): void
        {
            file_put_contents($this->testsPath . '/Urls/fr.json', json_encode(['login' => 'ma-connexion']));

            $this->assertSame('https://shop.test/ma-connexion', $this->page()->getPageURL('login'));
        }

        public function test_suite_customs_win_over_the_shop(): void
        {
            $page = $this->page(customs: ['contact' => 'contactez-nous']);

            $this->assertSame('https://shop.test/contactez-nous', $page->getPageURL('contact'));
        }

        public function test_without_the_shop_the_library_table_applies(): void
        {
            $this->assertSame('https://shop.test/connexion', $this->page(shop: [])->getPageURL('login'));
            $this->assertSame('https://shop.test/panier?action=show', $this->page(shop: [])->getPageURL('cart'));
        }

        public function test_without_the_shop_or_a_table_a_standard_page_uses_its_controller(): void
        {
            // No "contact" in src/Urls/fr.json: the English slug would 404 on a
            // French shop, the controller works in every language.
            $this->assertSame('https://shop.test/index.php?controller=contact', $this->page(shop: [])->getPageURL('contact'));
            $this->assertSame('https://shop.test/index.php?controller=my-account', $this->page(shop: [])->getPageURL('my-account'));
        }

        public function test_an_english_shop_keeps_its_friendly_urls_as_before(): void
        {
            $this->assertSame('https://shop.test/contact', $this->page('en', [])->getPageURL('contact'));
            $this->assertSame('https://shop.test/cart?action=show', $this->page('en', [])->getPageURL('cart'));
            $this->assertSame('https://shop.test/login', $this->page('en', [])->getPageURL('login'));
        }

        public function test_non_standard_pages_never_ask_the_shop(): void
        {
            $page = $this->page(shop: ['index' => 'https://elsewhere.test/']);

            $this->assertSame('https://shop.test/', $page->getPageURL('home'));
            $this->assertSame('https://shop.test/3-category', $page->getPageURL('category', 3));
            $this->assertSame('https://shop.test/6-product.html', $page->getPageURL('product', 6));
        }

        public function test_a_page_object_resolves_through_its_shop_page(): void
        {
            $this->assertSame('https://shop.test/fr/promotions', $this->page()->getPageURL(new PricesDropLike()));
        }

        public function test_a_page_object_falls_back_to_the_table_then_its_url(): void
        {
            // fr: src/Urls/fr.json knows "prices-drop" (the shopPage key).
            $this->assertSame('https://shop.test/promotions', $this->page(shop: [])->getPageURL(new PricesDropLike()));
            // en: no table entry, the page's own value as before.
            $this->assertSame('https://shop.test/promotions', $this->page('en', [])->getPageURL(new PricesDropLike()));
        }

        public function test_a_page_object_override_keyed_by_its_url_alias_still_wins(): void
        {
            // The app writes overrides under the page's $url: "promotions".
            $page = $this->page(customs: ['promotions' => 'soldes']);

            $this->assertSame('https://shop.test/soldes', $page->getPageURL(new PricesDropLike()));
        }

        public function test_a_project_page_with_its_own_url_never_takes_the_shop_one(): void
        {
            $this->assertSame('https://shop.test/fr/connexion', $this->page()->getPageURL(new LoginLike()));
            $this->assertSame('https://shop.test/module/mymodule/login', $this->page()->getPageURL(new ModuleLogin()));

            // Same when the URL is changed at run time.
            $login = new LoginLike();
            $login->url = 'module/other/login';
            $this->assertSame('https://shop.test/module/other/login', $this->page()->getPageURL($login));
        }

        public function test_standard_keys_use_the_controller_outside_english(): void
        {
            $de = $this->page('de', []);

            $this->assertSame('https://shop.test/index.php?controller=authentication', $de->getPageURL('login'));
            $this->assertSame('https://shop.test/index.php?controller=prices-drop', $de->getPageURL('prices-drop'));
            $this->assertSame('https://shop.test/index.php?controller=history', $de->getPageURL('order-history'));
            $this->assertSame('https://shop.test/index.php?controller=history', $this->page(shop: [])->getPageURL('history'));
            // The French table still wins over the controller.
            $this->assertSame('https://shop.test/promotions', $this->page(shop: [])->getPageURL('prices-drop'));
        }

        public function test_shop_url_accepts_raw_keys_and_unknown_pages(): void
        {
            $this->assertSame('https://shop.test/fr/promotions', $this->page()->pfShopUrl('prices_drop'));
            $this->assertNull($this->page()->pfShopUrl('history'));
        }

        public function test_no_browser_means_no_discovery(): void
        {
            // shopUrls left null: a unit test holds no browser, so nothing is
            // read and nothing is launched.
            $this->assertSame('https://shop.test/connexion', $this->page(shop: null)->getPageURL('login'));
            $this->assertTrue(FrontOfficePage::$pfShopUrlDiscovery);
        }
    }
}
