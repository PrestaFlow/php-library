<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Cart;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Cart';
    public string $url = 'cart';
    public $shopPage = 'cart';

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
            // Attribute values of a line (Classic: .product-line-info .value,
            // hummingbird: .product-line__item--info .product-line__item-value).
            'lineAttributeValue' => '.product-line-info > .value, .product-line__item--info > .product-line__item-value',
            'productsSubtotal' => '#cart-subtotal-products .value, #cart-subtotal-products .cart-summary__value',
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

    /**
     * The line (1-based) holding combination $combinationId of product
     * $productId, read from the line's remove link, which carries both ids
     * (data-id-product, data-id-product-attribute) in Classic 1.7 to 9 and in
     * hummingbird. 0 when the cart has no such line.
     */
    public function findLineOfCombination(int $productId, int $combinationId): int
    {
        try {
            return (int) $this->getPage()->evaluate(sprintf(
                '(function(lines,link,p,c){var all=document.querySelectorAll(lines);'
                . 'for(var i=0;i<all.length;i++){var a=all[i].querySelector(link);'
                . 'if(a&&parseInt(a.getAttribute("data-id-product"),10)===p'
                . '&&parseInt(a.getAttribute("data-id-product-attribute"),10)===c)return i+1;}return 0;})(%s,%s,%d,%d)',
                json_encode($this->getSelector('cartLine')),
                json_encode($this->getSelector('lineRemoveLink')),
                $productId,
                $combinationId
            ))->getReturnValue();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Attribute values the $line-th line shows, sorted and joined with " / "
     * (e.g. "Black / M"), to compare with Product::getCombinationValues().
     * "" when none.
     */
    public function getLineCombinationValues(int $line = 1): string
    {
        $json = $this->pfLineEval(
            $line,
            'return JSON.stringify([].slice.call(l.querySelectorAll(%s)).map(function(v){return v.textContent.replace(/\\s+/g," ").trim();}));',
            'lineAttributeValue'
        );
        $values = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($values)) {
            return '';
        }
        $values = array_values(array_filter($values, fn ($v) => is_string($v) && $v !== ''));
        sort($values);

        return implode(' / ', $values);
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
