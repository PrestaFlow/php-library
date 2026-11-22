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
        // null => the suite's locale (the project's language in the app). Friendly
        // URLs and their per-language overrides are read in that language.
        'locale' => null,
        // Left null on purpose: FrontOffice\Login::login() then falls back to the
        // FO_EMAIL / FO_PASSWD globals, fed by PRESTAFLOW_FO_EMAIL and
        // PRESTAFLOW_FO_PASSWD — the same route the back-office step already
        // uses. Set these params only to override the environment for one run;
        // do not hardcode a password here.
        'customerEmail' => null,
        'customerPassword' => null,
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
        // Used only when the customer has no saved address yet.
        'firstName' => 'PrestaFlow',
        'lastName' => 'Fixture',
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
        // The One Page Checkout module does not exist before 9.2: skip with an
        // explicit message rather than failing later on a missing selector.
        if (version_compare((string) $this->getMinorVersion(), '9.2', '<')) {
            $testSuite->skip('One Page Checkout requires PrestaShop 9.2+', function () {
            });

            return $testSuite;
        }

        // importPage() resolves friendly URLs from the SUITE's locale, so propagate
        // this scenario's locale before importing pages.
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

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
        ->it('the theme supports the one page checkout', function () use ($frontOfficeOnePageCheckoutPage) {
            // Skips the suite on the Classic theme, before the layout is switched.
            $frontOfficeOnePageCheckoutPage->requireCompatibleTheme();

            Expect::that($frontOfficeOnePageCheckoutPage->getShopThemeName())->notEquals('classic');
        })
        ->it('log in on the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('login');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);
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
            // Avant la bascule du tunnel : un saut ici (aucun produit commandable)
            // ne doit pas laisser la boutique sur une mise en page jamais vérifiée.
            // Le panier ne dépend pas de la mise en page du tunnel.
            // Skips the suite when no product of the shop can be added to the cart.
            $frontOfficeProductPage->goToPurchasableProduct($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));

            $frontOfficeCartPage->goToCart();
            Expect::that($frontOfficeCartPage->hasItems())->equals(true);
        })
        ->it('switch the shop to the one page checkout', function () use ($backOfficeCheckoutLayoutPage) {
            $backOfficeCheckoutLayoutPage->goTo((int) $this->getParam('shopId'));
            $backOfficeCheckoutLayoutPage->switchToOnePageCheckout();

            Expect::that($backOfficeCheckoutLayoutPage->isOnePageCheckoutSelected())->equals(true);
        })
        ->it('reach the one page checkout', function () use ($frontOfficeCartPage, $frontOfficeOnePageCheckoutPage, $backOfficeCheckoutLayoutPage) {
            $frontOfficeCartPage->goToCart();
            $frontOfficeCartPage->proceedToCheckout();

            // Le front doit afficher la commande en une page ; sinon (thème sans
            // gabarit pour le module) la boutique est remise en quatre étapes
            // sur-le-champ et la suite est sautée : jamais de tunnel cassé.
            self::pfRequireOnePageCheckoutRendered(
                $frontOfficeOnePageCheckoutPage,
                $backOfficeCheckoutLayoutPage,
                (int) $this->getParam('shopId')
            );

            Expect::that($frontOfficeOnePageCheckoutPage->isOnePageCheckoutActive())->equals(true);
        })
        ->it('fill the checkout in one page', function () use ($frontOfficeOnePageCheckoutPage) {
            // A customer who has ordered before picks an existing address; one
            // that has never ordered — a freshly provisioned fixture, say — gets
            // the inline form instead. Both are legitimate states for a
            // logged-in buyer, and the carrier assertion below gates them alike.
            $frontOfficeOnePageCheckoutPage->selectSavedAddressOrFill([
                'firstName' => $this->getParam('firstName'),
                'lastName' => $this->getParam('lastName'),
                'street' => $this->getParam('addressStreet'),
                'postcode' => $this->getParam('addressPostcode'),
                'city' => $this->getParam('addressCity'),
                'country' => $this->getParam('addressCountry'),
                'state' => $this->getParam('addressState'),
                'phone' => $this->getParam('addressPhone'),
            ]);

            // Carriers only appear once an address is selected and persisted:
            // assert it here rather than letting a silent failure surface later
            // as an unexplained timeout on the payment section.
            Expect::that($frontOfficeOnePageCheckoutPage->hasCarriers())->equals(true);

            $frontOfficeOnePageCheckoutPage->selectCarrier($this->getParam('carrierId'));
            // Skips the suite when the shop offers none of these payment modules.
            $frontOfficeOnePageCheckoutPage->selectPayment($this->getParam('paymentModules'));
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
