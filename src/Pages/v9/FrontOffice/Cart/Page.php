<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Cart;

use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Cart';
    public string $url = 'cart';
    public $shopPage = 'cart';

    /** Skip reason of requireVoucherForm(). */
    public const NO_VOUCHER_FORM = 'This shop shows no promo code field on its cart page.';

    public function defineSelectors()
    {
        return [
            // Not a theme union: verified live on both Classic and hummingbird
            // (PS 9.2.0). On Classic, .cart-detailed-actions and .checkout both
            // land on the very same "Proceed to checkout" element (redundant,
            // not two locations). On hummingbird, .cart-detailed-actions never
            // matches at all — the button only carries .checkout there — so
            // .checkout a.btn alone already covers both themes; the
            // .cart-detailed-actions half is inert weight kept for Classic's
            // sake rather than a real theme fallback.
            'checkoutButton' => '.cart-detailed-actions a.btn, .checkout a.btn',
            // Same target, but guaranteed to be the one on the cart page rather
            // than the header cart preview's.
            'cartPageCheckoutButton' => 'body#cart .cart-detailed-actions a.btn, body#cart .checkout a.btn',
            // Cart lines, Classic (li.cart-item, 1.7 to 9) and hummingbird
            // (.js-cart-item), from the core templates of both themes.
            'cartLine' => '.js-cart .cart-item, .js-cart .js-cart-item',
            'lineQuantityInput' => '.js-cart-line-product-quantity',
            'lineTotal' => '.product-line-actions .product-price, .product-line__price',
            // TouchSpin's own buttons on Classic, the quantity component's on
            // hummingbird. Both react to real mouse events only (TouchSpin
            // spins on mousedown), hence leftClick().
            'lineIncreaseButton' => '.bootstrap-touchspin-up, .js-increment-button',
            'lineDecreaseButton' => '.bootstrap-touchspin-down, .js-decrement-button',
            'lineRemoveLink' => '[data-link-action="delete-from-cart"]',
            'productsSubtotal' => '#cart-subtotal-products .value, #cart-subtotal-products .cart-summary__value',
            // Grand total of the cart page: Classic's .cart-total line (1.7 to
            // 9), the first total line of hummingbird's summary.
            'cartTotal' => '.js-cart-detailed-totals .cart-total .value, .js-cart-detailed-totals .js-cart-summary-totals .cart-summary__value',
            // The promo code block (cart-voucher.tpl), Classic and hummingbird
            // alike: the form posts discount_name, the core JS sends it over
            // AJAX and refreshes the cart.
            'voucherForm' => '.js-cart-voucher form[data-link-action="add-voucher"]',
            'voucherInput' => '.js-cart-voucher form[data-link-action="add-voucher"] input[name="discount_name"]',
            'voucherSubmitButton' => '.js-cart-voucher form[data-link-action="add-voucher"] button[type="submit"]',
            // What opens the collapsed form: Classic's "Have a promo code?"
            // link, hummingbird's accordion button.
            'voucherToggle' => '.js-cart-voucher a.collapse-button[href="#promo-code"], .js-cart-voucher [data-bs-target="#promo-code"]',
            'voucherRemoveLink' => '.js-cart-voucher [data-link-action="remove-voucher"]',
            'voucherErrorText' => '.js-cart-voucher .js-error-text',
        ];
    }

    public function goToCart(): void
    {
        // Resolves to the shop cart URL via the urls catalog (e.g. FR:
        // "panier?action=show"); falls back to "cart".
        $this->goToPage('cart');
    }

    /**
     * Whether the cart holds at least one product.
     *
     * The checkout button is only rendered on a non-empty cart, so its presence
     * is the cheapest reliable signal. Worth asserting before heading to the
     * checkout: on an empty cart PrestaShop redirects the order controller back
     * here, and the resulting failure then points at the checkout rather than at
     * the add-to-cart step that actually went wrong.
     */
    public function hasItems(): bool
    {
        // Scoped to the cart page on purpose. The header's cart preview carries
        // a checkout link too, so an unscoped match returns true from ANY page
        // once the cart is filled — which silently hid the fact that /cart was
        // redirecting to the home page for want of its action=show parameter.
        return $this->elementIsVisible($this->getSelector('cartPageCheckoutButton'), 5000);
    }

    public function proceedToCheckout(): void
    {
        $this->click($this->getSelector('checkoutButton'));
        $this->waitForPageReload();
    }

    /** Number of product lines in the cart. */
    public function countLines(): int
    {
        try {
            return (int) $this->getPage()->evaluate(sprintf(
                'document.querySelectorAll(%s).length',
                json_encode($this->getSelector('cartLine'))
            ))->getReturnValue();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Quantity of the $line-th product line (1-based), 0 when there is no such line. */
    public function getLineQuantity(int $line = 1): int
    {
        return (int) $this->pfLineEval($line, 'var q=l.querySelector(%s);return q?q.value:"0";', 'lineQuantityInput');
    }

    /** Total of the $line-th product line (1-based), as a number. */
    public function getLineTotal(int $line = 1): float
    {
        return $this->parsePrice((string) $this->pfLineEval($line, 'var t=l.querySelector(%s);return t?t.textContent:"";', 'lineTotal'));
    }

    /**
     * Products subtotal of the cart, as a number. Not the grand total on
     * purpose: shipping may change with the quantity (weight, price ranges).
     */
    public function getProductsSubtotal(): float
    {
        $text = $this->getTextContent($this->getSelector('productsSubtotal'), 1, true, 5000);

        return is_string($text) ? $this->parsePrice($text) : 0.0;
    }

    /** Add one to the quantity of the $line-th line and wait for the cart to follow. */
    public function increaseLineQuantity(int $line = 1): void
    {
        $this->pfChangeLineQuantity($line, 'lineIncreaseButton', +1);
    }

    /** Remove one from the quantity of the $line-th line and wait for the cart to follow. */
    public function decreaseLineQuantity(int $line = 1): void
    {
        $this->pfChangeLineQuantity($line, 'lineDecreaseButton', -1);
    }

    /** Remove the $line-th line from the cart and wait for it to be gone. */
    public function removeLine(int $line = 1): void
    {
        $before = $this->countLines();
        $this->pfMarkInLine($line, 'lineRemoveLink', 'remove');
        $this->click('[data-pf-cart-target="remove"]');

        $this->waitForJsCondition(sprintf(
            'document.querySelectorAll(%s).length < %d',
            json_encode($this->getSelector('cartLine')),
            $before
        ), 10000);
        $this->waitForStable(3000);
    }

    /** Whether the cart page shows the promo code form. */
    public function hasVoucherForm(): bool
    {
        return $this->elementIsVisible($this->getSelector('voucherForm'), 5000);
    }

    /**
     * Skip the test when the cart page has no promo code form: the theme
     * does not show it, or the shop has no active cart rule at all (the
     * block is only rendered then).
     */
    public function requireVoucherForm(): void
    {
        if (!$this->hasVoucherForm()) {
            throw new SkipTestException(self::NO_VOUCHER_FORM, skipRemaining: false);
        }
    }

    /** Grand total of the cart, as a number. */
    public function getCartTotal(): float
    {
        $text = $this->getTextContent($this->getSelector('cartTotal'), 1, true, 5000);

        return is_string($text) ? $this->parsePrice($text) : 0.0;
    }

    /** Number of vouchers applied to the cart (the lines that can be removed). */
    public function countVouchers(): int
    {
        return (int) $this->getPage()->evaluate(sprintf(
            'document.querySelectorAll(%s).length',
            json_encode($this->getSelector('voucherRemoveLink'))
        ))->getReturnValue();
    }

    /** The error the shop answered the last promo code with, '' when there is none. */
    public function getVoucherError(): string
    {
        $text = $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return e?e.textContent:"";})()',
            json_encode($this->getSelector('voucherErrorText'))
        ))->getReturnValue();

        return trim((string) $text);
    }

    /**
     * Type $code in the promo code field and send it, then wait for the cart
     * to show the voucher, or for the shop's error.
     */
    public function applyVoucher(string $code): void
    {
        $vouchers = $this->countVouchers();
        $total = $this->pfCartTotalText();

        // The form sits in a collapsed block: open it like a customer would.
        if (!$this->pfIsShown('voucherInput')) {
            $this->click($this->getSelector('voucherToggle'));
            $this->waitForJsCondition($this->pfShownJs('voucherInput'), 3000);
        }

        if ($this->pfIsShown('voucherInput')) {
            $this->setValue($this->getSelector('voucherInput'), $code);
            $this->click($this->getSelector('voucherSubmitButton'));
        } else {
            // Still collapsed (a theme animation that never ended): send the
            // very same form, through its submit event so the AJAX handler runs.
            $this->setValueByJs($this->getSelector('voucherInput'), $code);
            $this->getPage()->evaluate(sprintf(
                '(function(){var f=document.querySelector(%s);if(f){f.requestSubmit();}})()',
                json_encode($this->getSelector('voucherForm'))
            ));
        }

        // Done when the voucher line is there and the total was refreshed, or
        // when the shop refused the code.
        $this->waitForJsCondition(sprintf(
            '(function(){var e=document.querySelector(%s);if(e&&e.textContent.trim()!=="")return true;'
            . 'var t=document.querySelector(%s);'
            . 'return document.querySelectorAll(%s).length>%d&&!!t&&t.textContent!==%s;})()',
            json_encode($this->getSelector('voucherErrorText')),
            json_encode($this->getSelector('cartTotal')),
            json_encode($this->getSelector('voucherRemoveLink')),
            $vouchers,
            json_encode($total)
        ), 10000);
        $this->waitForStable(3000);
    }

    /** Remove the $nth applied voucher (1-based) and wait for the cart to follow. */
    public function removeVoucher(int $nth = 1): void
    {
        $vouchers = $this->countVouchers();
        $total = $this->pfCartTotalText();

        $this->getPage()->evaluate(sprintf(
            '(function(){[].slice.call(document.querySelectorAll("[data-pf-cart-target]")).forEach(function(e){e.removeAttribute("data-pf-cart-target");});'
            . 'var e=document.querySelectorAll(%s)[%d];if(e){e.setAttribute("data-pf-cart-target","voucher");}})()',
            json_encode($this->getSelector('voucherRemoveLink')),
            $nth - 1
        ));
        $this->click('[data-pf-cart-target="voucher"]');

        $this->waitForJsCondition(sprintf(
            '(function(){var t=document.querySelector(%s);'
            . 'return document.querySelectorAll(%s).length<%d&&!!t&&t.textContent!==%s;})()',
            json_encode($this->getSelector('cartTotal')),
            json_encode($this->getSelector('voucherRemoveLink')),
            $vouchers,
            json_encode($total)
        ), 10000);
        $this->waitForStable(3000);
    }

    private function pfCartTotalText(): string
    {
        return (string) $this->getPage()->evaluate(sprintf(
            '(function(){var t=document.querySelector(%s);return t?t.textContent:"";})()',
            json_encode($this->getSelector('cartTotal'))
        ))->getReturnValue();
    }

    /**
     * JS expression: whether the element $key is rendered, not collapsed away
     * and not in the middle of a collapse animation (.collapsing), where a
     * click would land on a moving target.
     */
    private function pfShownJs(string $key): string
    {
        return sprintf(
            '(function(){var e=document.querySelector(%s);return !!e&&e.offsetParent!==null'
            . '&&e.getBoundingClientRect().height>0&&!e.closest(".collapsing");})()',
            json_encode($this->getSelector($key))
        );
    }

    private function pfIsShown(string $key): bool
    {
        return $this->getPage()->evaluate($this->pfShownJs($key))->getReturnValue() === true;
    }

    private function pfChangeLineQuantity(int $line, string $buttonKey, int $change): void
    {
        $expected = $this->getLineQuantity($line) + $change;
        $totalBefore = (string) $this->pfLineEval($line, 'var t=l.querySelector(%s);return t?t.textContent:"";', 'lineTotal');

        $this->pfMarkInLine($line, $buttonKey, 'quantity');
        $this->leftClick('[data-pf-cart-target="quantity"]');

        // The cart is refreshed over AJAX: done when the line shows the new
        // quantity and a new total.
        $this->waitForJsCondition(sprintf(
            '(function(){var l=document.querySelectorAll(%s)[%d];if(!l)return false;'
            . 'var q=l.querySelector(%s),t=l.querySelector(%s);'
            . 'return !!q&&parseInt(q.value,10)===%d&&!!t&&t.textContent!==%s;})()',
            json_encode($this->getSelector('cartLine')),
            $line - 1,
            json_encode($this->getSelector('lineQuantityInput')),
            json_encode($this->getSelector('lineTotal')),
            $expected,
            json_encode($totalBefore)
        ), 10000);
        $this->waitForStable(3000);
    }

    /** Tag the element $key of the $line-th line, so it can be clicked by a plain selector. */
    private function pfMarkInLine(int $line, string $key, string $name): void
    {
        $this->getPage()->evaluate(sprintf(
            '(function(){[].slice.call(document.querySelectorAll("[data-pf-cart-target]")).forEach(function(e){e.removeAttribute("data-pf-cart-target");});'
            . 'var l=document.querySelectorAll(%s)[%d];if(!l)return;var e=l.querySelector(%s);'
            . 'if(e){e.setAttribute("data-pf-cart-target",%s);}})()',
            json_encode($this->getSelector('cartLine')),
            $line - 1,
            json_encode($this->getSelector($key)),
            json_encode($name)
        ));
    }

    /** Evaluate $body (with `l` = the $line-th line, %s = selector $key) in the page. */
    private function pfLineEval(int $line, string $body, string $key): mixed
    {
        try {
            return $this->getPage()->evaluate(sprintf(
                '(function(){var l=document.querySelectorAll(%s)[%d];if(!l)return null;' . $body . '})()',
                json_encode($this->getSelector('cartLine')),
                $line - 1,
                json_encode($this->getSelector($key))
            ))->getReturnValue();
        } catch (\Throwable) {
            return null;
        }
    }
}
