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
        // null => the suite's locale (the project's language in the app). Friendly
        // URLs and their per-language overrides are read in that language.
        'locale' => null,
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
        $testSuite->importPage('BackOffice\OrderSettings');
        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\OnePageCheckout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');

        $this->params['guestEmail'] = ($this->params['guestEmail'] ?? null) ?: self::pfUniqueEmail('pf-opc-guest');

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
        ->it('log in on the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('login');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);
        })
        ->it('enable guest checkout', function () use ($backOfficeOrderSettingsPage) {
            // Not restored on purpose: each scenario sets the state it requires up
            // front so scenarios stay independent of execution order; restoring
            // costs time and guarantees nothing if the run dies midway.
            $backOfficeOrderSettingsPage->goTo((int) $this->getParam('shopId'));
            $backOfficeOrderSettingsPage->setGuestCheckout(true);

            Expect::that($backOfficeOrderSettingsPage->isGuestCheckoutEnabled())->equals(true);
        })
        ->it('switch the shop to the one page checkout', function () use ($backOfficeCheckoutLayoutPage) {
            // Same rationale as above: set up front, never restored.
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
            $frontOfficeOnePageCheckoutPage->requireRenderedOrRestore($backOfficeCheckoutLayoutPage, (int) $this->getParam('shopId'));

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
                    'state' => $this->getParam('addressState'),
                'phone' => $this->getParam('addressPhone'),
            ]);

            // Carriers only appear once the guest is identified and the address
            // is persisted, so this asserts the whole guest-init chain worked —
            // including the required consent boxes, without which the module
            // never creates the guest and the sections stay blocked.
            Expect::that($frontOfficeOnePageCheckoutPage->hasCarriers())->equals(true);
        })
        ->it('choose shipping and payment, then place the order', function () use ($frontOfficeOnePageCheckoutPage) {
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
