<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Place an order through the One Page Checkout as a guest.
 *
 * Two shop settings are involved and they live in different places: the checkout
 * layout in the ps_onepagecheckout configuration, guest checkout in Shop
 * Parameters > Order Settings. Both are set up front and neither is restored —
 * each scenario is responsible for the state it requires.
 */
class OnePageCheckoutGuest extends Scenario
{
    public $params = [
        // The reference shop (ps92rc1 container) runs in English.
        'locale' => 'en',
        'guestEmail' => 'pf-opc-guest@example.com',
        'firstName' => 'PrestaFlow',
        'lastName' => 'Guest',
        'addressStreet' => '16 Main street',
        'addressCity' => 'Paris',
        'addressPostcode' => '75002',
        'addressCountry' => 'France',
        'addressPhone' => '0102030405',
        // Canonical product path — friendly URLs can't be rebuilt from an id.
        // Mug (id 6): no combinations, so add-to-cart works without posting an
        // id_product_attribute, unlike the demo t-shirt this used to point to.
        'productUrl' => '6-mug-the-best-is-yet-to-come.html',
        'cartQuantity' => 1,
    ];

    public function steps($testSuite)
    {
        // The One Page Checkout module does not exist before 9.2: skip with an
        // explicit message rather than failing later on a missing selector.
        if (version_compare((string) $this->getMinorVersion(), '9.2', '<')) {
            $testSuite->skip('One Page Checkout requires PrestaShop 9.2+', function () {
            });

            return $testSuite;
        }

        // importPage() resolves friendly URLs from the SUITE's locale, so propagate
        // this scenario's locale before importing pages.
        $testSuite->params['locale'] = $this->params['locale'] ?? 'en';

        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\CheckoutLayout');
        $testSuite->importPage('BackOffice\OrderSettings');
        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\OnePageCheckout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');

        extract($testSuite->pages);

        $testSuite
        // Every step below asserts its own effect. Without that, a step that
        // merely clicks reports success even when nothing happened — click()
        // returns false on a missing selector instead of raising — so a failed
        // back-office login would go unnoticed and every later step would
        // "pass" while doing nothing at all.
        ->it('log in on the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('login');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);
        })
        ->it('enable guest checkout', function () use ($backOfficeOrderSettingsPage) {
            // Not restored on purpose: each scenario sets the state it requires up
            // front so scenarios stay independent of execution order; restoring
            // costs time and guarantees nothing if the run dies midway.
            $backOfficeOrderSettingsPage->goTo();
            $backOfficeOrderSettingsPage->setGuestCheckout(true);

            Expect::that($backOfficeOrderSettingsPage->isGuestCheckoutEnabled())->equals(true);
        })
        ->it('switch the shop to the one page checkout', function () use ($backOfficeCheckoutLayoutPage) {
            // Same rationale as above: set up front, never restored.
            $backOfficeCheckoutLayoutPage->goTo();
            $backOfficeCheckoutLayoutPage->switchToOnePageCheckout();

            Expect::that($backOfficeCheckoutLayoutPage->isOnePageCheckoutSelected())->equals(true);
        })
        ->it('add a product to the cart', function () use ($frontOfficeProductPage, $frontOfficeCartPage) {
            $frontOfficeProductPage->goToProductPath($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));

            $frontOfficeCartPage->goToCart();
            Expect::that($frontOfficeCartPage->hasItems())->equals(true);
        })
        ->it('reach the one page checkout', function () use ($frontOfficeCartPage, $frontOfficeOnePageCheckoutPage) {
            $frontOfficeCartPage->proceedToCheckout();

            Expect::that($frontOfficeOnePageCheckoutPage->isOnePageCheckoutActive())->equals(true);
        })
        ->it('check out as a guest and enter an address', function () use ($frontOfficeOnePageCheckoutPage) {
            $frontOfficeOnePageCheckoutPage->continueAsGuest($this->getParam('guestEmail'));
            $frontOfficeOnePageCheckoutPage->fillNewAddress([
                'firstName' => $this->getParam('firstName'),
                'lastName' => $this->getParam('lastName'),
                'street' => $this->getParam('addressStreet'),
                'postcode' => $this->getParam('addressPostcode'),
                'city' => $this->getParam('addressCity'),
                'country' => $this->getParam('addressCountry'),
                'phone' => $this->getParam('addressPhone'),
            ]);

            // Carriers only appear once the guest is identified and the address
            // is persisted, so this asserts the whole guest-init chain worked —
            // including the required consent boxes, without which the module
            // never creates the guest and the sections stay blocked.
            Expect::that($frontOfficeOnePageCheckoutPage->hasCarriers())->equals(true);
        })
        ->it('choose shipping and payment, then place the order', function () use ($frontOfficeOnePageCheckoutPage) {
            $frontOfficeOnePageCheckoutPage->selectFirstCarrier();
            $frontOfficeOnePageCheckoutPage->selectFirstPayment();
            $frontOfficeOnePageCheckoutPage->acceptTerms();

            Expect::that($frontOfficeOnePageCheckoutPage->isReadyToPlaceOrder())->equals(true);

            $frontOfficeOnePageCheckoutPage->placeOrder();
        })
        ->it('reach the order confirmation', function () use ($frontOfficeOrderConfirmationPage) {
            Expect::that($frontOfficeOrderConfirmationPage->isConfirmed())->equals(true);
        });

        return $testSuite;
    }
}
