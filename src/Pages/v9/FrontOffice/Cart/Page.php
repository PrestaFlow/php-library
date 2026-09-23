<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Cart;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Cart';
    public string $url = 'cart';

    public function defineSelectors()
    {
        return [
            // Best-effort PS 9 classic theme — corrected live.
            'checkoutButton' => '.cart-detailed-actions a.btn, .checkout a.btn',
            // Same target, but guaranteed to be the one on the cart page rather
            // than the header cart preview's.
            'cartPageCheckoutButton' => 'body#cart .cart-detailed-actions a.btn, body#cart .checkout a.btn',
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
}
