<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class GuestCheckout extends Scenario
{
    public $params = [
        // French demo shop: locale drives the friendly URLs (connexion/panier/
        // commande) resolved from src/Urls/fr.json.
        'locale' => 'fr',
        // Canonical product path — friendly URLs can't be rebuilt from an id.
        'productUrl' => '1-1-hummingbird-printed-t-shirt.html',
        'cartQuantity' => 1,
        'guestEmail' => 'pf-guest@example.com',
        'firstName' => 'PrestaFlow',
        'lastName' => 'Guest',
        'addressStreet' => '16 Main street',
        'addressCity' => 'Paris',
        'addressPostcode' => '75002',
        'addressCountry' => 'France',
        'addressPhone' => '0102030405',
    ];

    public function steps($testSuite)
    {
        // importPage() resolves friendly URLs from the SUITE's locale, so
        // propagate this scenario's locale (fr on the demo shop) before importing
        // pages — otherwise cart/checkout URLs fall back to English and 404.
        $testSuite->params['locale'] = $this->params['locale'] ?? 'fr';

        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\Checkout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');

        extract($testSuite->pages);

        $testSuite
        ->it('add a product to the cart', function () use ($frontOfficeProductPage) {
            $frontOfficeProductPage->goToProductPath($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));
        })
        ->it('checkout as a guest and enter an address', function () use ($frontOfficeCartPage, $frontOfficeCheckoutPage) {
            $frontOfficeCartPage->goToCart();
            $frontOfficeCartPage->proceedToCheckout();

            $frontOfficeCheckoutPage->checkoutAsGuest(
                $this->getParam('guestEmail'),
                $this->getParam('firstName'),
                $this->getParam('lastName')
            );
            $frontOfficeCheckoutPage->fillNewAddress([
                'street' => $this->getParam('addressStreet'),
                'city' => $this->getParam('addressCity'),
                'postcode' => $this->getParam('addressPostcode'),
                'country' => $this->getParam('addressCountry'),
                'phone' => $this->getParam('addressPhone'),
            ]);
        })
        ->it('choose shipping and place the order', function () use ($frontOfficeCheckoutPage) {
            $frontOfficeCheckoutPage->chooseShipping();
            $frontOfficeCheckoutPage->choosePaymentAndConfirm();
        })
        ->it('reach the order confirmation', function () use ($frontOfficeOrderConfirmationPage) {
            Expect::that($frontOfficeOrderConfirmationPage->isConfirmed())->equals(true);
        });

        return $testSuite;
    }
}
