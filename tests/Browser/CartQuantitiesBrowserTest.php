<?php

namespace PrestaFlow\Tests\Browser\CartFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\Cart\Page as CartPage;

    /** The Cart page on a chrome-php page handed in by the test, without a suite. */
    class Cart extends CartPage
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
    }
}

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Tests\Browser\CartFixtures\Cart;

    /**
     * Cart line methods on the markup of the core themes (Classic 1.7 to 9,
     * hummingbird), with a small script standing in for the AJAX refresh.
     */
    final class CartQuantitiesBrowserTest extends TestCase
    {
        private static $browser = null;

        public static function tearDownAfterClass(): void
        {
            self::$browser?->close();
            self::$browser = null;
        }

        /** A cart of one line at 12.50 a unit, refreshed 150 ms after each action. */
        private const SCRIPT = '<script>
            var unit = 12.5;
            function fmt(v){return "€" + v.toFixed(2).replace(".", ",") + " ";}
            function refresh(line, q){
                setTimeout(function(){
                    if (q <= 0) { line.remove(); document.querySelector(".sub").textContent = fmt(0); return; }
                    line.querySelector(".js-cart-line-product-quantity").value = q;
                    line.querySelector(".total").textContent = fmt(unit * q);
                    document.querySelector(".sub").textContent = fmt(unit * q + 3);
                }, 150);
            }
            document.addEventListener("click", function(e){
                var line = e.target.closest(".cart-item, .js-cart-item"); if (!line) return;
                var q = parseInt(line.querySelector(".js-cart-line-product-quantity").value, 10);
                if (e.target.closest(".bootstrap-touchspin-up, .js-increment-button")) refresh(line, q + 1);
                if (e.target.closest(".bootstrap-touchspin-down, .js-decrement-button")) refresh(line, q - 1);
                if (e.target.closest("[data-link-action=delete-from-cart]")) { e.preventDefault(); refresh(line, 0); }
            });
        </script>';

        private const CLASSIC = '<div class="cart-overview js-cart"><ul class="cart-items"><li class="cart-item">'
            . '<div class="product-line-grid"><div class="product-line-grid-right product-line-actions">'
            . '<div class="qty"><div class="bootstrap-touchspin"><input class="js-cart-line-product-quantity" value="1">'
            . '<button class="bootstrap-touchspin-up" style="width:40px;height:20px">+</button>'
            . '<button class="bootstrap-touchspin-down" style="width:40px;height:20px">-</button></div></div>'
            . '<div class="price"><span class="product-price"><strong class="total">€12,50</strong></span></div>'
            . '<a class="remove-from-cart" href="#" data-link-action="delete-from-cart">x</a>'
            . '</div></div></li></ul></div>'
            . '<div class="cart-summary-line" id="cart-subtotal-products"><span class="label">Subtotal</span><span class="value sub">€15,50</span></div>';

        private const HUMMINGBIRD = '<div class="cart__overview cart-overview js-cart"><div class="cart__list"><div class="cart__item js-cart-item">'
            . '<div class="product-line"><div class="product-line__content-right"><div class="quantity-button__group">'
            . '<button class="btn decrement js-decrement-button" style="width:40px;height:20px">-</button>'
            . '<input class="js-cart-line-product-quantity form-control" value="1">'
            . '<button class="btn increment js-increment-button" style="width:40px;height:20px">+</button></div>'
            . '<div class="product-line__price total">€12,50</div></div>'
            . '<div class="product-line__actions"><a class="js-remove-from-cart" href="#" data-link-action="delete-from-cart">x</a></div>'
            . '</div></div></div></div>'
            . '<div class="cart-summary__line" id="cart-subtotal-products"><span class="cart-summary__label">Subtotal</span><span class="cart-summary__value sub">€15,50</span></div>';

        private function cart(string $markup): Cart
        {
            if (self::$browser === null) {
                try {
                    self::$browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true]);
                } catch (\Throwable $e) {
                    if (getenv('CI') === false || getenv('CI') === '') {
                        $this->markTestSkipped('Chrome indisponible : ' . $e->getMessage());
                    }
                    $this->fail('Chrome indisponible en CI : ' . $e->getMessage());
                }
            }

            $page = self::$browser->createPage();
            $page->navigate('data:text/html,' . rawurlencode('<meta charset="utf-8"><body>' . $markup . self::SCRIPT . '</body>'))->waitForNavigation();
            Cart::$chromePage = $page;

            return new Cart();
        }

        public static function themes(): array
        {
            return ['classic' => [self::CLASSIC], 'hummingbird' => [self::HUMMINGBIRD]];
        }

        /** @dataProvider themes */
        public function test_lines_and_totals_are_read_as_numbers(string $markup): void
        {
            $cart = $this->cart($markup);

            $this->assertSame(1, $cart->countLines());
            $this->assertSame(1, $cart->getLineQuantity(1));
            $this->assertSame(12.5, $cart->getLineTotal(1));
            $this->assertSame(15.5, $cart->getProductsSubtotal());
            $this->assertSame(0, $cart->getLineQuantity(2));
        }

        /** @dataProvider themes */
        public function test_quantity_changes_wait_for_the_refresh(string $markup): void
        {
            $cart = $this->cart($markup);

            $cart->increaseLineQuantity(1);
            $this->assertSame(2, $cart->getLineQuantity(1));
            $this->assertSame(25.0, $cart->getLineTotal(1));
            $this->assertSame(28.0, $cart->getProductsSubtotal());

            $cart->decreaseLineQuantity(1);
            $this->assertSame(1, $cart->getLineQuantity(1));
            $this->assertSame(12.5, $cart->getLineTotal(1));
        }

        /** @dataProvider themes */
        public function test_removing_the_line_empties_the_cart(string $markup): void
        {
            $cart = $this->cart($markup);

            $cart->removeLine(1);

            $this->assertSame(0, $cart->countLines());
        }
    }
}
