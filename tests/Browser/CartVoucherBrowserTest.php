<?php

namespace PrestaFlow\Tests\Browser\CartVoucherFixtures {

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

namespace PrestaFlow\Tests\Browser\CartVoucherFixtures {

    use PrestaFlow\Library\Pages\v9\BackOffice\CartRules\Page as CartRulesPage;

    /** The cart rules list as it is, without the menu navigation. */
    class CartRules extends CartRulesPage
    {
        public function __construct()
        {
            $this->selectors = $this->defineSelectors();
        }

        public function getPage()
        {
            return Cart::$chromePage;
        }

        public function goTo(): void
        {
        }
    }
}

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Exceptions\SkipTestException;
    use PrestaFlow\Tests\Browser\CartVoucherFixtures\Cart;
    use PrestaFlow\Tests\Browser\CartVoucherFixtures\CartRules;

    /** The promo code block of the cart page (cart-voucher.tpl), as Classic (1.7 to 9) and hummingbird render it. */
    final class CartVoucherBrowserTest extends TestCase
    {
        private static $browser = null;

        public static function tearDownAfterClass(): void
        {
            self::$browser?->close();
            self::$browser = null;
        }

        /**
         * Stands in for the theme JS and the AJAX round trip: the toggle opens
         * the collapsed form; PFTESTOK is accepted (a voucher line, a lower
         * total), any other code is refused; the remove link takes the line
         * off and restores the total. Answers come late, like the shop's.
         */
        private const SCRIPT = '<style>.collapse{display:none}.collapse.in,.collapse.show{display:block}</style><script>
            function pfTotal(v){document.querySelector(".cart-total .value, .js-cart-summary-totals .cart-summary__value").textContent = "€" + v.toFixed(2);}
            document.addEventListener("click", function(e){
                var t = e.target.closest("[href=\"#promo-code\"],[data-bs-target=\"#promo-code\"]");
                if (t) { e.preventDefault(); document.getElementById("promo-code").classList.add("in", "show"); return; }
                var r = e.target.closest("[data-link-action=\"remove-voucher\"]");
                if (r) { e.preventDefault(); setTimeout(function(){ r.closest("li").remove(); pfTotal(19.12); }, 300); }
            });
            document.addEventListener("submit", function(e){
                e.preventDefault();
                var code = e.target.discount_name.value;
                setTimeout(function(){
                    if (code === "PFTESTOK") {
                        var li = document.createElement("li");
                        li.innerHTML = "<span>PrestaFlow voucher</span> -€1.91 <a href=\"#remove\" data-link-action=\"remove-voucher\">x</a>";
                        document.querySelector(".pf-vouchers").appendChild(li);
                        pfTotal(17.21);
                    } else {
                        document.querySelector(".js-error-text").textContent = "The voucher code is invalid.";
                    }
                }, 300);
            });
        </script>';

        private const CLASSIC = '<section id="main"><div class="cart-detailed-totals js-cart-detailed-totals">'
            . '<div class="card-block cart-summary-subtotals-container"><div class="cart-summary-line" id="cart-subtotal-products"><span class="label">1 item</span><span class="value">€19.12</span></div></div>'
            . '<div class="card-block cart-summary-totals js-cart-summary-totals"><div class="cart-summary-line cart-total"><span class="label">Total (tax incl.)</span><span class="value">€19.12</span></div></div>'
            . '<div class="block-promo"><div class="cart-voucher js-cart-voucher"><ul class="promo-name card-block pf-vouchers"></ul>'
            . '<p class="promo-code-button display-promo"><a class="collapse-button" href="#promo-code">Have a promo code?</a></p>'
            . '<div id="promo-code" class="collapse"><div class="promo-code">'
            . '<form action="#" data-link-action="add-voucher" method="post"><input type="hidden" name="addDiscount" value="1">'
            . '<input class="promo-input" type="text" name="discount_name" placeholder="Promo code"><button type="submit" class="btn btn-primary"><span>Add</span></button></form>'
            . '<div class="alert alert-danger js-error" role="alert"><span class="ml-1 js-error-text"></span></div>'
            . '</div></div></div></div></div></section>';

        private const HUMMINGBIRD = '<div class="cart-summary__totals js-cart-detailed-totals">'
            . '<div class="cart-summary__subtotals"><div class="cart-summary__line" id="cart-subtotal-products"><span class="cart-summary__label js-subtotal">1 item</span><span class="cart-summary__value">€19.12</span></div></div>'
            . '<div class="cart-summary__total js-cart-summary-totals"><div class="cart-summary__line cart-summary__line--bold"><span class="cart-summary__label">Total (tax incl.)</span><span class="cart-summary__value">€19.12</span></div></div>'
            . '<div class="cart-summary__voucher cart-voucher js-cart-voucher"><ul class="cart-voucher__list pf-vouchers"></ul>'
            . '<div class="cart-voucher__accordion accordion"><div class="accordion-item">'
            . '<button class="accordion-button collapsed" type="button" data-bs-target="#promo-code" data-bs-toggle="collapse">Promo code</button>'
            . '<div id="promo-code" class="accordion-collapse collapse js-voucher-accordion"><div class="accordion-body">'
            . '<form class="cart-voucher__form" action="#" data-link-action="add-voucher" method="post"><input type="hidden" name="addDiscount" value="1">'
            . '<input class="form-control js-voucher-input" type="text" name="discount_name" required><button type="submit" class="btn btn-primary">Apply</button></form>'
            . '<div class="cart-voucher__error alert alert-danger js-error" role="alert" style="display: none;"><span class="js-error-text"></span></div>'
            . '</div></div></div></div></div></div>';

        private function cart(string $markup): Cart
        {
            if (self::$browser === null) {
                try {
                    self::$browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [1000, 800], 'noSandbox' => true]);
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
        public function test_a_code_lowers_the_total_and_its_removal_restores_it(string $markup): void
        {
            $cart = $this->cart($markup);

            $cart->requireVoucherForm();
            $this->assertSame(19.12, $cart->getCartTotal());
            $this->assertSame(0, $cart->countVouchers());

            $cart->applyVoucher('PFTESTOK');

            $this->assertSame('', $cart->getVoucherError());
            $this->assertSame(1, $cart->countVouchers());
            $this->assertSame(17.21, $cart->getCartTotal());

            $cart->removeVoucher(1);

            $this->assertSame(0, $cart->countVouchers());
            $this->assertSame(19.12, $cart->getCartTotal());
        }

        /** @dataProvider themes */
        public function test_a_refused_code_reports_the_shop_error(string $markup): void
        {
            $cart = $this->cart($markup);

            $cart->applyVoucher('NOPE');

            $this->assertSame('The voucher code is invalid.', $cart->getVoucherError());
            $this->assertSame(0, $cart->countVouchers());
            $this->assertSame(19.12, $cart->getCartTotal());
        }

        public function test_no_promo_code_form_skips_the_test_only(): void
        {
            $cart = $this->cart('<div class="cart-detailed-totals js-cart-detailed-totals"><div class="cart-total"><span class="value">€19.12</span></div></div>');

            try {
                $cart->requireVoucherForm();
                $this->fail('A cart page without the promo code form must skip');
            } catch (SkipTestException $e) {
                $this->assertSame('This shop shows no promo code field on its cart page.', $e->getMessage());
                $this->assertFalse($e->skipRemaining);
            }
        }

        /** The HelperList of 1.7, 8 and 9: the delete URL only sits in the onclick's confirm_link(). */
        public function test_the_cart_rules_list_finds_rules_by_name(): void
        {
            $deleteUrl = 'http://shop.test/admin-dev/index.php?controller=AdminCartRules&amp;id_cart_rule=7&amp;deletecart_rule=1&amp;token=abc';
            $row = fn (int $id, string $name, string $url) => '<tr><td>' . $id . '</td><td>' . $name . '</td><td>PFTEST' . $id . '</td><td>'
                . '<a href="index.php?controller=AdminCartRules&amp;updatecart_rule=&amp;id_cart_rule=' . $id . '" class="edit btn btn-default">Edit</a>'
                . '<a href="#" title="Delete" class="delete" onclick="confirm_link(\'\', \'Delete selected item?\\n\\nName: ' . $name . '\', \'Yes\', \'No\', \'' . $url . '\', \'#\')">Delete</a></td></tr>';

            $this->cart('<table id="table-cart_rule" class="table cart_rule"><tbody>'
                . $row(8, 'Summer sale', str_replace('=7', '=8', $deleteUrl))
                . $row(7, 'PrestaFlow voucher 20261121-1', $deleteUrl)
                . '</tbody></table>');
            $list = new CartRules();

            $this->assertSame(1, $list->countCartRulesNamed('PrestaFlow voucher '));
            $this->assertSame(0, $list->countCartRulesNamed('PrestaFlow voucher 2025'));

            $links = (new \ReflectionMethod($list, 'pfDeleteLinksOf'))->invoke($list, 'PrestaFlow voucher ');
            $this->assertSame(['http://shop.test/admin-dev/index.php?controller=AdminCartRules&id_cart_rule=7&deletecart_rule=1&token=abc'], $links);
        }

        public function test_an_empty_cart_rules_list_has_nothing_to_delete(): void
        {
            $this->cart('<table id="table-cart_rule" class="table cart_rule"><tbody><tr><td class="list-empty" colspan="8">No records found</td></tr></tbody></table>');

            $this->assertSame(0, (new CartRules())->countCartRulesNamed('PrestaFlow voucher '));
        }
    }
}
