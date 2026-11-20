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
        // Used only when the account has no address yet (the tunnel then shows
        // the address form). Same defaults as GuestCheckout.
        'addressStreet' => '16 Main street',
        'addressCity' => 'Paris',
        'addressPostcode' => null,
        'addressCountry' => null,
        'addressState' => null,
        'addressPhone' => '0102030405',
        // null => the first product of the shop that can be added to the cart
        // as it is (Product::findPurchasable()). Set a product path or URL to
        // pin one product.
        'productUrl' => null,
        'cartQuantity' => 1,
        // null => the first carrier offered; an id_carrier picks that one.
        'carrierId' => null,
        // The first payment option of these modules is used. Offline ones by
        // default: no redirection to a payment provider, nothing charged. When
        // the shop offers none of them the order steps are skipped.
        'paymentModules' => \PrestaFlow\Library\Pages\FrontOfficePage::OFFLINE_PAYMENT_MODULES,
        // Which shop the back-office settings are written for. The checkout
        // layout is stored per shop, so a multistore run must say which one.
        // null => the project's shop (PRESTAFLOW_SHOP_ID, default 1).
        'shopId' => null,
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
            if (!$frontOfficeProductPage->goToPurchasableProduct($this->getParam('productUrl'))) {
                $this->skipTest(Scenario::NO_PURCHASABLE_PRODUCT, skipRemaining: true);
            }
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));

            $frontOfficeCartPage->goToCart();
            Expect::that($frontOfficeCartPage->hasItems())->equals(true);
        })
        ->it('go through the checkout tunnel', function () use ($frontOfficeCartPage, $frontOfficeCheckoutPage) {
            $frontOfficeCartPage->goToCart();
            $frontOfficeCartPage->proceedToCheckout();

            // An account that never ordered has no address: the step shows
            // its form instead of the address cards.
            if ($frontOfficeCheckoutPage->hasAddressForm()) {
                $frontOfficeCheckoutPage->fillNewAddress([
                    'street' => $this->getParam('addressStreet'),
                    'city' => $this->getParam('addressCity'),
                    'postcode' => $this->getParam('addressPostcode'),
                    'country' => $this->getParam('addressCountry'),
                    'state' => $this->getParam('addressState'),
                    'phone' => $this->getParam('addressPhone'),
                ]);
            } else {
                $frontOfficeCheckoutPage->confirmAddresses();
            }

            Expect::that($frontOfficeCheckoutPage->hasReachedShippingStep())->equals(true);

            $frontOfficeCheckoutPage->chooseShipping(Scenario::carrierIdParam($this->getParam('carrierId')));

            Expect::that($frontOfficeCheckoutPage->hasReachedPaymentStep())->equals(true);

            $paymentModules = (array) $this->getParam('paymentModules');
            if ($frontOfficeCheckoutPage->choosePayment($paymentModules) === null) {
                $this->skipTest(Scenario::noPaymentMethodReason($paymentModules), skipRemaining: true);
            }
            $frontOfficeCheckoutPage->confirmOrder();
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
