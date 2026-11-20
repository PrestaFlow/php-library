<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class CheckoutOrder extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app). Friendly
        // URLs and their per-language overrides are read in that language.
        'locale' => null,
        // Left null on purpose: FrontOffice\Login::login() then falls back to the
        // project's customer account (FO_EMAIL / FO_PASSWD globals, fed by
        // PRESTAFLOW_FO_EMAIL and PRESTAFLOW_FO_PASSWD), like OnePageCheckoutOrder.
        // Set these params only to use another account for one suite.
        'customerEmail' => null,
        'customerPassword' => null,
        // A canonical product path. An id would work too — PrestaShop
        // canonicalises on the id and ignores the slug (/1-anything redirects to
        // the real URL), and goToPage('product', $id) now substitutes the
        // {index} placeholder for scalar params. The path is kept because it
        // pins this exact product *and* combination (id_product 1,
        // id_product_attribute 1), which an id alone cannot express.
        'productUrl' => '1-1-hummingbird-printed-t-shirt.html',
        'cartQuantity' => 1,
        // Which shop the back-office settings are written for. The checkout
        // layout is stored per shop, so a multistore run must say which one.
        'shopId' => 1,
    ];

    public function steps($testSuite)
    {
        // importPage() resolves friendly URLs from the SUITE's locale, so
        // propagate an explicit locale before importing pages. Without one,
        // the suite's own locale applies.
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        // The four-page tunnel this scenario drives only exists while the shop
        // is on the four-page layout. Set it before anything touches the front
        // office — a One Page Checkout scenario may have run first.
        $this->requireFourPageCheckout($testSuite);

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

            Expect::that($frontOfficeLoginPage->isLoggedIn())->equals(true);
        })
        ->it('add a product to the cart', function () use ($frontOfficeProductPage, $frontOfficeCartPage) {
            $frontOfficeProductPage->goToProductPath($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));

            $frontOfficeCartPage->goToCart();
            Expect::that($frontOfficeCartPage->hasItems())->equals(true);
        })
        ->it('go through the checkout tunnel', function () use ($frontOfficeCartPage, $frontOfficeCheckoutPage) {
            $frontOfficeCartPage->goToCart();
            $frontOfficeCartPage->proceedToCheckout();
            $frontOfficeCheckoutPage->confirmAddresses();

            Expect::that($frontOfficeCheckoutPage->hasReachedShippingStep())->equals(true);

            $frontOfficeCheckoutPage->chooseShipping();

            Expect::that($frontOfficeCheckoutPage->hasReachedPaymentStep())->equals(true);

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
