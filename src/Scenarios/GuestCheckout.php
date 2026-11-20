<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class GuestCheckout extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app). Friendly
        // URLs and their per-language overrides are read in that language.
        'locale' => null,
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
        // null => a unique address per run (a guest e-mail already used by a
        // registered customer would be refused).
        'guestEmail' => null,
        'firstName' => 'PrestaFlow',
        'lastName' => 'Guest',
        'addressStreet' => '16 Main street',
        'addressCity' => 'Paris',
        // null => a postcode valid for the country the form shows.
        'addressPostcode' => null,
        // null => keep the country the shop preselects; a label (as listed in
        // the form, in the shop's language) selects that one.
        'addressCountry' => null,
        // null => the first state when the country requires one; or a label.
        'addressState' => null,
        'addressPhone' => '0102030405',
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

        // Same precondition as CheckoutOrder: this scenario walks the four-page
        // tunnel, and the layout is a shop-wide setting another scenario may
        // have flipped.
        $this->requireFourPageCheckout($testSuite);

        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\Checkout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');

        $this->params['guestEmail'] = ($this->params['guestEmail'] ?? null) ?: self::pfUniqueEmail('pf-guest');

        extract($testSuite->pages);

        $testSuite
        ->it('add a product to the cart', function () use ($frontOfficeProductPage, $frontOfficeCartPage) {
            if (!$frontOfficeProductPage->goToPurchasableProduct($this->getParam('productUrl'))) {
                $this->skipWithReason(Scenario::NO_PURCHASABLE_PRODUCT, skipRemaining: true);
            }
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));

            $frontOfficeCartPage->goToCart();
            Expect::that($frontOfficeCartPage->hasItems())->equals(true);
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
                    'state' => $this->getParam('addressState'),
                'phone' => $this->getParam('addressPhone'),
            ]);

            Expect::that($frontOfficeCheckoutPage->hasReachedShippingStep())->equals(true);
        })
        ->it('choose shipping and place the order', function () use ($frontOfficeCheckoutPage) {
            $frontOfficeCheckoutPage->chooseCarrier(Scenario::pfCarrierIdParam($this->getParam('carrierId')));

            Expect::that($frontOfficeCheckoutPage->hasReachedPaymentStep())->equals(true);

            $paymentModules = (array) $this->getParam('paymentModules');
            if ($frontOfficeCheckoutPage->choosePayment($paymentModules) === null) {
                $this->skipWithReason(Scenario::pfNoPaymentMethodReason($paymentModules), skipRemaining: true);
            }
            $frontOfficeCheckoutPage->acceptTermsAndPlaceOrder();
        })
        ->it('reach the order confirmation', function () use ($frontOfficeOrderConfirmationPage) {
            Expect::that($frontOfficeOrderConfirmationPage->isConfirmed())->equals(true);
        });

        return $testSuite;
    }
}
