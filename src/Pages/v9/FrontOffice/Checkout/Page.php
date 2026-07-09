<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Checkout;

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
            // Guest personal-information step — best-effort PS 9, corrected live.
            'guestToggle' => '#checkout-personal-information-step .nav-item a[href*="guest"], #checkout-guest-form-tab',
            'personalEmailInput' => '#checkout-personal-information-step input[name="email"]',
            'personalFirstNameInput' => '#checkout-personal-information-step input[name="firstname"]',
            'personalLastNameInput' => '#checkout-personal-information-step input[name="lastname"]',
            'personalContinueButton' => '#checkout-personal-information-step button[type="submit"]',
            // Guest new-address step.
            'addressStreetInput' => '#checkout-addresses-step input[name="address1"]',
            'addressCityInput' => '#checkout-addresses-step input[name="city"]',
            'addressPostcodeInput' => '#checkout-addresses-step input[name="postcode"]',
            'addressCountrySelect' => '#checkout-addresses-step select[name="id_country"]',
            'addressPhoneInput' => '#checkout-addresses-step input[name="phone"]',
        ];
    }

    public function confirmAddresses(): void
    {
        $this->click($this->getSelector('addressesContinueButton'));
        $this->waitForPageReload();
    }

    public function checkoutAsGuest(string $email, string $firstName, string $lastName): void
    {
        // If a guest/sign-in toggle is present, switch to the guest form.
        $this->click($this->getSelector('guestToggle'));

        $this->setValue($this->getSelector('personalFirstNameInput'), $firstName);
        $this->setValue($this->getSelector('personalLastNameInput'), $lastName);
        $this->setValue($this->getSelector('personalEmailInput'), $email);

        // Tick every required consent checkbox (GDPR terms + data privacy) — the
        // step refuses to advance otherwise.
        $this->getPage()->evaluate(
            '(function(){[].slice.call(document.querySelectorAll("#checkout-personal-information-step input[type=checkbox]")).forEach(function(c){if(c.required&&!c.checked){c.click();}});})()'
        );

        $this->click($this->getSelector('personalContinueButton'));
        $this->waitForPageReload();
    }

    public function fillNewAddress(array $address): void
    {
        $this->setValue($this->getSelector('addressStreetInput'), $address['street'] ?? '');
        $this->setValue($this->getSelector('addressCityInput'), $address['city'] ?? '');
        $this->setValue($this->getSelector('addressPostcodeInput'), $address['postcode'] ?? '');
        if (!empty($address['country'])) {
            $this->selectValue($this->getSelector('addressCountrySelect'), $address['country']);
        }
        $this->setValue($this->getSelector('addressPhoneInput'), $address['phone'] ?? '');

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
