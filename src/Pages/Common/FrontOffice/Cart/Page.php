<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice\Cart;

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
        ];
    }

    public function goToCart(): void
    {
        // Resolves to the shop cart URL via the urls catalog (e.g. FR:
        // "panier?action=show"); falls back to "cart".
        $this->goToPage('cart');
    }

    public function proceedToCheckout(): void
    {
        $this->click($this->getSelector('checkoutButton'));
        $this->waitForPageReload();
    }
}
