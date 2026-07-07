<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class CheckoutOrder extends Scenario
{
    public $params = [
        // French demo shop: locale drives the friendly URLs (connexion/panier/
        // commande) resolved from src/Urls/fr.json.
        'locale' => 'fr',
        // PrestaShop demo customer (John DOE); override per shop.
        'customerEmail' => 'pub@prestashop.com',
        'customerPassword' => 'PrestaFlow2026!',
        // Canonical product path — friendly URLs can't be rebuilt from an id.
        'productUrl' => '1-1-hummingbird-printed-t-shirt.html',
        'cartQuantity' => 1,
    ];

    public function steps($testSuite)
    {
        // importPage() resolves friendly URLs from the SUITE's locale, so
        // propagate this scenario's locale (fr on the demo shop) before importing
        // pages — otherwise login/cart/order URLs fall back to English and 404.
        $testSuite->params['locale'] = $this->params['locale'] ?? 'fr';

        $testSuite->importPage('FrontOffice\Login');
        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\Checkout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Orders');

        extract($testSuite->pages);

        $testSuite
        ->it('log in on the FrontOffice', function () use ($frontOfficeLoginPage) {
            $frontOfficeLoginPage->goToPage('login');
            $frontOfficeLoginPage->login(
                $this->getParam('customerEmail'),
                $this->getParam('customerPassword')
            );
        })
        ->it('add a product to the cart', function () use ($frontOfficeProductPage) {
            $frontOfficeProductPage->goToProductPath($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));
        })
        ->it('go through the checkout tunnel', function () use ($frontOfficeCartPage, $frontOfficeCheckoutPage) {
            $frontOfficeCartPage->goToCart();
            $frontOfficeCartPage->proceedToCheckout();
            $frontOfficeCheckoutPage->confirmAddresses();
            $frontOfficeCheckoutPage->chooseShipping();
            $frontOfficeCheckoutPage->choosePaymentAndConfirm();
        })
        ->it('reach the order confirmation', function () use ($frontOfficeOrderConfirmationPage) {
            Expect::that($frontOfficeOrderConfirmationPage->isConfirmed())->equals(true);

            $this->store('orderReference', $frontOfficeOrderConfirmationPage->getOrderReference());
        })
        ->it('find the order in the BackOffice', function () use ($backOfficeLoginPage, $backOfficeOrdersPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            $backOfficeOrdersPage->goTo();
            $backOfficeOrdersPage->filterByReference($this->retrieve('orderReference'));

            Expect::that($backOfficeOrdersPage->getOrderReferenceInList(1))
                ->contains($this->retrieve('orderReference'));
        });

        return $testSuite;
    }
}
