<?php

namespace PrestaFlow\Tests\Browser\ShopAgnosticFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\Checkout\Page as CheckoutPage;
    use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as ListingPage;
    use PrestaFlow\Library\Pages\v9\FrontOffice\Product\Page as ProductPage;

    /** Drives a chrome-php page handed in by the test, without a suite. */
    trait OnGivenPage
    {
        public static $chromePage = null;

        public function __construct()
        {
            $this->selectors = $this->defineSelectors();
        }

        public function getPage()
        {
            return self::$chromePage;
        }

        public function state(string $selector, ?string $label = null): void
        {
            $this->fillState($selector, $label);
        }

        public function countryIso(string $selector): ?string
        {
            return $this->selectedCountryIso($selector);
        }
    }

    class Checkout extends CheckoutPage
    {
        use OnGivenPage;
    }

    class Product extends ProductPage
    {
        use OnGivenPage;
    }

    class Listing extends ListingPage
    {
        use OnGivenPage;
    }
}

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Tests\Browser\ShopAgnosticFixtures\Checkout;
    use PrestaFlow\Tests\Browser\ShopAgnosticFixtures\Listing;
    use PrestaFlow\Tests\Browser\ShopAgnosticFixtures\Product;

    /** The DOM helpers scenarios use to stay independent of the demo shop, on simulated markup. */
    final class ShopAgnosticHelpersBrowserTest extends TestCase
    {
        private static $browser = null;

        private static $page = null;

        public static function tearDownAfterClass(): void
        {
            self::$browser?->close();
            self::$browser = null;
        }

        private function load(string $body): void
        {
            if (self::$browser === null) {
                try {
                    self::$browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true]);
                    self::$page = self::$browser->createPage();
                } catch (\Throwable $e) {
                    if (getenv('CI') === false || getenv('CI') === '') {
                        $this->markTestSkipped('Chrome indisponible : '.$e->getMessage());
                    }
                    $this->fail('Chrome indisponible en CI : '.$e->getMessage());
                }
            }

            self::$page->navigate('data:text/html,'.rawurlencode('<meta charset="utf-8"><body>'.$body.'</body>'))->waitForNavigation();
            Checkout::$chromePage = Product::$chromePage = Listing::$chromePage = self::$page;
        }

        private const PAYMENTS = '<div id="checkout-payment-step">'
            .'<input type="radio" name="payment-option" id="payment-option-1" data-module-name="stripe_official">'
            .'<input type="radio" name="payment-option" id="payment-option-2" data-module-name="ps_checkpayment">'
            .'<input type="radio" name="payment-option" id="payment-option-3" data-module-name="ps_wirepayment">'
            .'</div>';

        public function test_payment_is_the_first_offline_option_in_page_order(): void
        {
            $this->load(self::PAYMENTS);
            $checkout = new Checkout();

            $this->assertSame('ps_checkpayment', $checkout->choosePayment());
            $this->assertTrue(self::$page->evaluate('document.getElementById("payment-option-2").checked')->getReturnValue());
            $this->assertFalse(self::$page->evaluate('document.getElementById("payment-option-1").checked')->getReturnValue());
        }

        public function test_payment_honours_the_given_modules(): void
        {
            $this->load(self::PAYMENTS);

            $this->assertSame('ps_wirepayment', (new Checkout())->choosePayment(['ps_wirepayment']));
        }

        public function test_no_offline_payment_clicks_nothing(): void
        {
            $this->load('<input type="radio" name="payment-option" id="p" data-module-name="paypal">');

            $this->assertNull((new Checkout())->choosePayment());
            $this->assertFalse(self::$page->evaluate('document.getElementById("p").checked')->getReturnValue());
        }

        public function test_add_to_cart_button_state(): void
        {
            $this->load('<button class="add-to-cart" data-button-action="add-to-cart">Add</button>');
            $this->assertTrue((new Product())->isAddableToCart());

            $this->load('<button class="add-to-cart" data-button-action="add-to-cart" disabled>Add</button>');
            $this->assertFalse((new Product())->isAddableToCart());

            $this->load('<div class="product-customization"><textarea required></textarea></div>'
                .'<button class="add-to-cart" data-button-action="add-to-cart">Add</button>');
            $this->assertFalse((new Product())->isAddableToCart());

            $this->load('<p>Catalog mode: no button</p>');
            $this->assertFalse((new Product())->isAddableToCart());
        }

        public function test_listing_reads_miniatures(): void
        {
            $this->load('<div id="js-product-list"><div class="products">'
                .'<article class="product-miniature js-product-miniature"><a class="thumbnail" href="https://shop.test/1-a.html"></a>'
                .'<h3 class="product-title"><a href="https://shop.test/1-a.html">  Mug   blue </a></h3></article>'
                .'<article class="product-miniature js-product-miniature"><a class="product-miniature__title" href="https://shop.test/2-b.html">Poster</a></article>'
                .'</div></div>');
            $listing = new Listing();

            $this->assertSame(2, $listing->countProducts());
            $this->assertSame('Mug blue', $listing->getProductName(1));
            $this->assertSame('https://shop.test/2-b.html', $listing->getProductUrl(2));
            $this->assertNull($listing->getProductUrl(3));
        }

        public function test_category_links(): void
        {
            $this->load('<ul id="top-menu"><li id="category-3"><a href="https://shop.test/3-clothes">Clothes</a>'
                .'<ul><li id="category-4"><a href="https://shop.test/4-men">Men</a></li></ul></li>'
                .'<li id="cms-1"><a href="https://shop.test/content/1-delivery">Delivery</a></li></ul>');

            $this->assertSame(['https://shop.test/3-clothes', 'https://shop.test/4-men'], (new Listing())->readCategoryLinks());
        }

        public function test_required_state_and_preselected_country(): void
        {
            $this->load('<select name="id_country" id="c"><option value="8">France</option><option value="21" selected>United States</option></select>'
                .'<select name="id_state" id="s"><option value="">-</option><option value="0">--</option><option value="12">Alabama</option><option value="13">Alaska</option></select>');
            $checkout = new Checkout();

            $this->assertSame('US', $checkout->countryIso('#c'));

            $checkout->state('#s');
            $this->assertSame('12', self::$page->evaluate('document.getElementById("s").value')->getReturnValue());

            $checkout->state('#s', 'Alaska');
            $this->assertSame('13', self::$page->evaluate('document.getElementById("s").value')->getReturnValue());
        }
    }
}
