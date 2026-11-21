<?php

namespace PrestaFlow\Tests\Browser\CombinationsFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\Cart\Page as CartPage;
    use PrestaFlow\Library\Pages\v9\FrontOffice\Product\Page as ProductPage;

    /** The Product page on a chrome-php page handed in by the test, without a suite. */
    class Product extends ProductPage
    {
        public static $chromePage = null;

        /** Listings the test hands in, in place of the shop's discovered ones. */
        public static array $listings = [];

        public function __construct()
        {
            $this->selectors = $this->defineSelectors();
        }

        public function getPage()
        {
            return self::$chromePage;
        }

        public function discoverListingUrls(bool $allProducts = true): array
        {
            return self::$listings;
        }
    }

    class Cart extends CartPage
    {
        public function __construct()
        {
            $this->selectors = $this->defineSelectors();
        }

        public function getPage()
        {
            return Product::$chromePage;
        }
    }
}

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Exceptions\SkipTestException;
    use PrestaFlow\Tests\Browser\CombinationsFixtures\Cart;
    use PrestaFlow\Tests\Browser\CombinationsFixtures\Product;

    /** Combinations on the product page and the cart, as Classic (1.7 to 9) and hummingbird render them. */
    final class ProductCombinationsBrowserTest extends TestCase
    {
        private static $browser = null;

        private static $page = null;

        public static function tearDownAfterClass(): void
        {
            self::$browser?->close();
            self::$browser = null;
        }

        /**
         * Stands in for the theme's AJAX refresh: on an attribute change, the
         * product data and the add-to-cart button follow the combination
         * (size id-color id => [id_product_attribute, size, color, orderable]).
         * M / White (3) is out of stock.
         */
        private const SCRIPT = '<script>
            var combos = {"1-8": [1, "S", "White", true], "2-8": [3, "M", "White", false],
                "2-11": [4, "M", "Black", true], "1-11": [2, "S", "Black", true]};
            function pick(name) {
                var s = document.querySelector("select[name=\"" + name + "\"]");
                if (s) return s.value;
                var r = document.querySelector("input[name=\"" + name + "\"]:checked");
                return r ? r.value : null;
            }
            document.addEventListener("change", function (e) {
                if (!e.target.hasAttribute("data-product-attribute")) return;
                setTimeout(function () {
                    var c = combos[pick("group[1]") + "-" + pick("group[2]")];
                    var d = document.querySelector("[data-product]");
                    d.setAttribute("data-product", JSON.stringify({id_product: 7, id_product_attribute: c[0],
                        attributes: {1: {name: c[1], group: "Size"}, 2: {name: c[2], group: "Color"}}}));
                    document.querySelector(".add-to-cart").disabled = !c[3];
                }, 150);
            });
        </script>';

        private const DATA = '{"id_product":7,"id_product_attribute":1,"attributes":{"1":{"name":"S","group":"Size"},"2":{"name":"White","group":"Color"}}}';

        private const CLASSIC = '<div class="product-variants js-product-variants">'
            . '<div class="product-variants-item"><select class="form-control form-control-select" data-product-attribute="1" name="group[1]">'
            . '<option value="1" selected="selected">S</option><option value="2">M</option></select></div>'
            . '<div class="product-variants-item"><ul>'
            . '<li class="input-container"><label><input class="input-color" type="radio" data-product-attribute="2" name="group[2]" value="8" checked="checked"><span class="color"></span></label></li>'
            . '<li class="input-container"><label><input class="input-color" type="radio" data-product-attribute="2" name="group[2]" value="11"><span class="color"></span></label></li>'
            . '</ul></div></div>'
            . '<div class="js-product-details tab-pane fade" id="product-details" data-product=\'' . self::DATA . '\'></div>'
            . '<div class="product-prices"><div class="product-price"><span>€19.12</span></div></div>'
            . '<button class="btn btn-primary add-to-cart" data-button-action="add-to-cart" type="submit">Add</button>';

        private const HUMMINGBIRD = '<div class="product__variants js-product-variants">'
            . '<fieldset class="product-variant"><div class="product-variant__radios">'
            . '<div class="product-variant__radio"><input class="btn-check" type="radio" id="i1" data-product-attribute="1" name="group[1]" value="1" checked="checked"><label class="btn" for="i1">S</label></div>'
            . '<div class="product-variant__radio"><input class="btn-check" type="radio" id="i2" data-product-attribute="1" name="group[1]" value="2"><label class="btn" for="i2">M</label></div>'
            . '</div></fieldset>'
            . '<fieldset class="product-variant"><div class="product-variant__colors">'
            . '<div class="product-variant__color input-color"><input class="input-color__input" type="radio" id="c8" data-product-attribute="2" name="group[2]" value="8" checked="checked"><label for="c8"></label></div>'
            . '<div class="product-variant__color input-color"><input class="input-color__input" type="radio" id="c11" data-product-attribute="2" name="group[2]" value="11"><label for="c11"></label></div>'
            . '</div></fieldset></div>'
            . '<div class="js-product-details" data-product=\'' . self::DATA . '\'></div>'
            . '<div class="product__prices"><div class="product-price"><span>€19.12</span></div></div>'
            . '<button class="btn btn-primary add-to-cart" data-button-action="add-to-cart" type="submit">Add</button>';

        private static function url(string $body): string
        {
            return 'data:text/html,' . rawurlencode('<meta charset="utf-8"><body>' . $body . '</body>');
        }

        private function load(string $body): void
        {
            if (self::$browser === null) {
                try {
                    self::$browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true]);
                    self::$page = self::$browser->createPage();
                } catch (\Throwable $e) {
                    if (getenv('CI') === false || getenv('CI') === '') {
                        $this->markTestSkipped('Chrome indisponible : ' . $e->getMessage());
                    }
                    $this->fail('Chrome indisponible en CI : ' . $e->getMessage());
                }
            }

            self::$page->navigate(self::url($body))->waitForNavigation();
            Product::$chromePage = self::$page;
        }

        public static function themes(): array
        {
            return ['classic' => [self::CLASSIC], 'hummingbird' => [self::HUMMINGBIRD]];
        }

        /** @dataProvider themes */
        public function test_the_initial_combination_is_read_from_the_product_data(string $markup): void
        {
            $this->load($markup . self::SCRIPT);
            $product = new Product();

            $this->assertTrue($product->hasCombinations());
            $this->assertSame(7, $product->getProductId());
            $this->assertSame(1, $product->getCombinationId());
            $this->assertSame('S / White', $product->getCombinationValues());
        }

        /** @dataProvider themes */
        public function test_another_orderable_combination_is_chosen_past_an_out_of_stock_one(string $markup): void
        {
            $this->load($markup . self::SCRIPT);
            $product = new Product();

            // M / White (3) is out of stock: the next value leads to M / Black (4).
            $this->assertTrue($product->selectOtherCombination(3000));
            $this->assertSame(4, $product->getCombinationId());
            $this->assertSame('Black / M', $product->getCombinationValues());
            $this->assertTrue($product->isAddableToCart());
        }

        public function test_no_other_orderable_combination_answers_false(): void
        {
            $this->load(str_replace('"2-11": [4, "M", "Black", true], "1-11": [2, "S", "Black", true]', '"2-11": [4, "M", "Black", false], "1-11": [2, "S", "Black", false]', self::CLASSIC . self::SCRIPT));

            $this->assertFalse((new Product())->selectOtherCombination(1000));
        }

        public function test_a_product_with_single_values_has_no_combinations(): void
        {
            $this->load('<div class="product-variants js-product-variants"><select data-product-attribute="1" name="group[1]"><option value="1" selected>One size</option></select></div>'
                . '<button class="add-to-cart" data-button-action="add-to-cart">Add</button>');

            $this->assertFalse((new Product())->hasCombinations());
        }

        public function test_the_first_product_with_combinations_is_found(): void
        {
            $simple = self::url('<div id="product-details" data-product=\'{"id_product":3,"id_product_attribute":0}\'></div>'
                . '<button class="add-to-cart" data-button-action="add-to-cart">Add</button>');
            $withCombinations = self::url(self::CLASSIC);
            Product::$listings = [self::url('<div id="js-product-list"><div class="products">'
                . '<article class="product-miniature js-product-miniature"><h3 class="product-title"><a href="' . $simple . '">Mug</a></h3></article>'
                . '<article class="product-miniature js-product-miniature"><h3 class="product-title"><a href="' . $withCombinations . '">T-shirt</a></h3></article>'
                . '</div></div>')];
            $this->load('<p>Home</p>');
            $product = new Product();

            $this->assertSame($withCombinations, $product->findProductWithCombinations());
            $this->assertTrue($product->goToProductWithCombinations());
            $this->assertSame(1, $product->getCombinationId());
        }

        public function test_no_product_with_combinations_skips_the_suite(): void
        {
            $simple = self::url('<button class="add-to-cart" data-button-action="add-to-cart">Add</button>');
            Product::$listings = [self::url('<div id="js-product-list"><article class="product-miniature js-product-miniature">'
                . '<h3 class="product-title"><a href="' . $simple . '">Mug</a></h3></article></div>')];
            $this->load('<p>Home</p>');

            try {
                (new Product())->goToProductWithCombinations();
                $this->fail('A shop without products with combinations must skip');
            } catch (SkipTestException $e) {
                $this->assertSame('No product with combinations that can be added to the cart was found among the first products of this shop.', $e->getMessage());
                $this->assertTrue($e->skipRemaining);
            }
        }

        public static function carts(): array
        {
            return [
                'classic' => ['<div class="cart-overview js-cart"><ul class="cart-items">'
                    . '<li class="cart-item"><div class="product-line-info"><a class="label" href="#">Mug</a></div>'
                    . '<a class="remove-from-cart" data-link-action="delete-from-cart" data-id-product="3" data-id-product-attribute="0" href="#"></a></li>'
                    . '<li class="cart-item"><div class="product-line-info"><a class="label" href="#">T-shirt</a></div>'
                    . '<div class="product-line-info size"><span class="label">Size:</span> <span class="value">M</span></div>'
                    . '<div class="product-line-info color"><span class="label">Color:</span> <span class="value">Black</span></div>'
                    . '<a class="remove-from-cart" data-link-action="delete-from-cart" data-id-product="7" data-id-product-attribute="4" href="#"></a></li>'
                    . '</ul></div>'],
                'hummingbird' => ['<div class="cart-overview js-cart">'
                    . '<div class="product-line js-cart-item"><a class="product-line__title" href="#">Mug</a>'
                    . '<a class="remove-from-cart" data-link-action="delete-from-cart" data-id-product="3" data-id-product-attribute="0" href="#"></a></div>'
                    . '<div class="product-line js-cart-item"><a class="product-line__title" href="#">T-shirt</a>'
                    . '<div class="product-line__item product-line__item--info size"><span class="product-line__item-label">Size:</span><span class="product-line__item-value">M</span></div>'
                    . '<div class="product-line__item product-line__item--info color"><span class="product-line__item-label">Color:</span><span class="product-line__item-value"> Black </span></div>'
                    . '<a class="remove-from-cart" data-link-action="delete-from-cart" data-id-product="7" data-id-product-attribute="4" href="#"></a></div>'
                    . '</div>'],
            ];
        }

        /** @dataProvider carts */
        public function test_the_cart_line_of_a_combination_is_found_with_its_values(string $markup): void
        {
            $this->load($markup);
            $cart = new Cart();

            $this->assertSame(2, $cart->findLineOfCombination(7, 4));
            $this->assertSame(0, $cart->findLineOfCombination(7, 3));
            $this->assertSame('Black / M', $cart->getLineCombinationValues(2));
            $this->assertSame('', $cart->getLineCombinationValues(1));
        }
    }
}
