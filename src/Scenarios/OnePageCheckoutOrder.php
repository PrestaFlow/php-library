<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Place an order through the One Page Checkout as a logged-in customer.
 *
 * The OPC ships as the ps_onepagecheckout module in PrestaShop 9.2 and is off by
 * default, so the scenario switches the shop to the one-page layout in the back
 * office before touching the front office. It does not restore the previous
 * layout: each scenario sets the state it needs, which is what keeps them
 * independent of execution order.
 */
class OnePageCheckoutOrder extends Scenario
{
    public $params = [
        // The reference shop (ps92rc1 container) runs in English.
        'locale' => 'en',
        // Left null on purpose: FrontOffice\Login::login() then falls back to the
        // FO_EMAIL / FO_PASSWD globals, fed by PRESTAFLOW_FO_EMAIL and
        // PRESTAFLOW_FO_PASSWD — the same route the back-office step already
        // uses. Set these params only to override the environment for one run;
        // do not hardcode a password here.
        'customerEmail' => null,
        'customerPassword' => null,
        // Canonical product path — friendly URLs can't be rebuilt from an id.
        // Mug (id 6): no combinations, so add-to-cart works without posting an
        // id_product_attribute, unlike the demo t-shirt this used to point to.
        'productUrl' => '6-mug-the-best-is-yet-to-come.html',
        'cartQuantity' => 1,
        // Used only when the customer has no saved address yet.
        'firstName' => 'PrestaFlow',
        'lastName' => 'Fixture',
        'addressStreet' => '16 Main street',
        'addressCity' => 'Paris',
        'addressPostcode' => '75002',
        'addressCountry' => 'France',
        'addressPhone' => '0102030405',
        // Which shop the back-office settings are written for. The checkout
        // layout is stored per shop, so a multistore run must say which one.
        'shopId' => 1,
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
        $testSuite->importPage('FrontOffice\Login');
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
        ->it('switch the shop to the one page checkout', function () use ($backOfficeCheckoutLayoutPage) {
            $backOfficeCheckoutLayoutPage->goTo((int) $this->getParam('shopId'));
            $backOfficeCheckoutLayoutPage->switchToOnePageCheckout();

            Expect::that($backOfficeCheckoutLayoutPage->isOnePageCheckoutSelected())->equals(true);
        })
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
        ->it('reach the one page checkout', function () use ($frontOfficeCartPage, $frontOfficeOnePageCheckoutPage) {
            $frontOfficeCartPage->proceedToCheckout();

            Expect::that($frontOfficeOnePageCheckoutPage->isOnePageCheckoutActive())->equals(true);
        })
        ->it('fill the checkout in one page', function () use ($frontOfficeOnePageCheckoutPage) {
            // A customer who has ordered before picks an existing address; one
            // that has never ordered — a freshly provisioned fixture, say — gets
            // the inline form instead. Both are legitimate states for a
            // logged-in buyer, and the carrier assertion below gates them alike.
            if ($frontOfficeOnePageCheckoutPage->hasSavedAddresses()) {
                $frontOfficeOnePageCheckoutPage->selectAddress();
            } else {
                $frontOfficeOnePageCheckoutPage->fillNewAddress([
                    'firstName' => $this->getParam('firstName'),
                    'lastName' => $this->getParam('lastName'),
                    'street' => $this->getParam('addressStreet'),
                    'postcode' => $this->getParam('addressPostcode'),
                    'city' => $this->getParam('addressCity'),
                    'country' => $this->getParam('addressCountry'),
                    'phone' => $this->getParam('addressPhone'),
                ]);
            }

            // Carriers only appear once an address is selected and persisted:
            // assert it here rather than letting a silent failure surface later
            // as an unexplained timeout on the payment section.
            Expect::that($frontOfficeOnePageCheckoutPage->hasCarriers())->equals(true);

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
