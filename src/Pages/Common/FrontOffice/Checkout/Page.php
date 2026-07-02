<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice\Checkout;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Checkout';
    public string $url = 'order';

    public function defineSelectors()
    {
        return [
            // Best-effort PS 9 checkout tunnel (order controller) — corrected live.
            'addressesContinueButton' => '#checkout-addresses-step button[name="confirm-addresses"]',
            'shippingOption' => '#checkout-delivery-step input[name^="delivery_option"]',
            'shippingContinueButton' => '#checkout-delivery-step button[name="confirmDeliveryOption"]',
            'paymentOption' => 'input[name="payment-option"]',
            'termsCheckbox' => '#conditions-to-approve input[type="checkbox"]',
            'placeOrderButton' => '#payment-confirmation button',
        ];
    }

    public function confirmAddresses(): void
    {
        $this->click($this->getSelector('addressesContinueButton'));
        $this->waitForPageReload();
    }

    public function chooseShipping(): void
    {
        $this->click($this->getSelector('shippingOption'));
        $this->click($this->getSelector('shippingContinueButton'));
        $this->waitForPageReload();
    }

    public function choosePaymentAndConfirm(): void
    {
        $this->click($this->getSelector('paymentOption'));
        $this->click($this->getSelector('termsCheckbox'));
        $this->click($this->getSelector('placeOrderButton'));
        $this->waitForPageReload();
    }
}
