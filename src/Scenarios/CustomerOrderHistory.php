<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * The customer finds an order in "Order history", opens its detail and
 * downloads its invoice.
 *
 * The order is placed like CheckoutOrder does (product found on the shop,
 * offline payment, skipped without one), or, with useLastOrder, the newest
 * order of the account is used and no order is placed.
 *
 * PrestaShop only issues an invoice once the order reaches a status that
 * generates one ("Payment accepted"), which an offline payment never does by
 * itself. So the back office moves the order to orderStatusId (2,
 * PS_OS_PAYMENT, by default) before the invoice is checked: that works on any
 * shop with invoices enabled, instead of depending on the order's luck. With
 * invoices disabled (PS_INVOICE), the invoice step is skipped with its reason.
 */
class CustomerOrderHistory extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => the project's customer account (FO_EMAIL / FO_PASSWD).
        'customerEmail' => null,
        'customerPassword' => null,
        // true => no new order: the newest order of the account is used.
        'useLastOrder' => false,
        // Used only when the account has no address yet.
        'addressStreet' => '16 Main street',
        'addressCity' => 'Paris',
        'addressPostcode' => null,
        'addressCountry' => null,
        'addressState' => null,
        'addressPhone' => '0102030405',
        // null => the first product of the shop that can be added to the cart.
        'productUrl' => null,
        // null => the first carrier offered.
        'carrierId' => null,
        // Offline payment modules only by default: nothing is charged.
        'paymentModules' => \PrestaFlow\Library\Pages\FrontOfficePage::OFFLINE_PAYMENT_MODULES,
        // Status the back office gives the order so that it has an invoice
        // (2 = PS_OS_PAYMENT, "Payment accepted", on every install).
        'orderStatusId' => 2,
        // null => the project's shop (PRESTAFLOW_SHOP_ID, default 1).
        'shopId' => null,
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        // The order is placed through the four-page tunnel.
        $this->requireFourPageCheckout($testSuite);

        $testSuite->importPage('FrontOffice\Login');
        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\Checkout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');
        $testSuite->importPage('FrontOffice\OrderHistory');
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Orders');
        $testSuite->importPage('BackOffice\OrderView');

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
        ->it('place an order', function () use ($frontOfficeOrderHistoryPage, $frontOfficeProductPage, $frontOfficeCartPage, $frontOfficeCheckoutPage) {
            // Skipped when the suite reuses the customer's last order.
            $frontOfficeOrderHistoryPage->skipWhenReusingLastOrder($this->getParam('useLastOrder'));

            // Skips the suite when no product of the shop can be added to the cart.
            $frontOfficeProductPage->goToPurchasableProduct($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart(1);
            $frontOfficeCartPage->goToCart();

            Expect::that($frontOfficeCartPage->hasItems())->equals(true);

            $frontOfficeCartPage->proceedToCheckout();
            $frontOfficeCheckoutPage->confirmOrFillAddress([
                'street' => $this->getParam('addressStreet'),
                'city' => $this->getParam('addressCity'),
                'postcode' => $this->getParam('addressPostcode'),
                'country' => $this->getParam('addressCountry'),
                'state' => $this->getParam('addressState'),
                'phone' => $this->getParam('addressPhone'),
            ]);

            Expect::that($frontOfficeCheckoutPage->hasReachedShippingStep())->equals(true);

            $frontOfficeCheckoutPage->chooseCarrier($this->getParam('carrierId'));

            Expect::that($frontOfficeCheckoutPage->hasReachedPaymentStep())->equals(true);

            // Skips the suite when the shop offers none of these payment modules.
            $frontOfficeCheckoutPage->choosePayment($this->getParam('paymentModules'));
            $frontOfficeCheckoutPage->acceptTermsAndPlaceOrder();
        })
        ->it('reach the order confirmation', function () use ($frontOfficeOrderHistoryPage, $frontOfficeOrderConfirmationPage) {
            $frontOfficeOrderHistoryPage->skipWhenReusingLastOrder($this->getParam('useLastOrder'));

            Expect::that($frontOfficeOrderConfirmationPage->isConfirmed())->equals(true);

            $this->store('orderReference', $frontOfficeOrderConfirmationPage->getOrderReference());
        })
        ->it('the order is in the order history', function () use ($frontOfficeOrderHistoryPage) {
            $frontOfficeOrderHistoryPage->goToPage($frontOfficeOrderHistoryPage);

            // The order just placed, or the newest one with useLastOrder
            // (skips the suite when the account has none).
            $reference = $frontOfficeOrderHistoryPage->orderReferenceOrLatest($this->retrieve('orderReference'));

            Expect::that($frontOfficeOrderHistoryPage->hasOrder($reference))->equals(true);

            $this->store('orderReference', $reference);
        })
        ->it('the order detail opens', function () use ($frontOfficeOrderHistoryPage) {
            $frontOfficeOrderHistoryPage->openOrderDetail($this->retrieve('orderReference'));

            Expect::that($frontOfficeOrderHistoryPage->isOrderDetailFor($this->retrieve('orderReference')))->equals(true);
        })
        ->it('accept the payment in the BackOffice', function () use ($backOfficeLoginPage, $backOfficeOrdersPage, $backOfficeOrderViewPage) {
            // Skips the rest of the suite when no back-office account is configured.
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);

            $backOfficeOrdersPage->goTo();
            $backOfficeOrdersPage->filterByReference($this->retrieve('orderReference'));
            $backOfficeOrdersPage->openOrder(1);

            Expect::that($backOfficeOrderViewPage->getOrderReference())->contains($this->retrieve('orderReference'));

            $label = $backOfficeOrderViewPage->changeStatus($this->getParam('orderStatusId'));

            Expect::that($backOfficeOrderViewPage->hasStatusInHistory($label))->equals(true);
        })
        ->it('download the invoice', function () use ($frontOfficeOrderHistoryPage) {
            $frontOfficeOrderHistoryPage->goToPage($frontOfficeOrderHistoryPage);

            // Skips this step when the shop gives the order no invoice.
            $invoice = $frontOfficeOrderHistoryPage->downloadInvoice($this->retrieve('orderReference'));

            Expect::that($invoice->pageCount())->isGreaterThan(0);
            Expect::that($invoice->contains($this->retrieve('orderReference')))->equals(true);
        });

        return $testSuite;
    }
}
