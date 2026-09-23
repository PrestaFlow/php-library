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

    /**
     * Whether the tunnel has advanced into the delivery (shipping) step. A
     * false here means the previous click (guest/address form submit) was a
     * no-op — the click() layer returns false on a missing selector instead
     * of raising, so the tunnel can silently stay on the step it started on.
     */
    public function hasReachedShippingStep(): bool
    {
        return $this->elementIsVisible($this->getSelector('shippingOption'), 5000);
    }

    /**
     * Whether the tunnel has advanced into the payment step, i.e. the
     * shipping option was actually selected and confirmed.
     */
    public function hasReachedPaymentStep(): bool
    {
        return $this->elementIsVisible($this->getSelector('paymentOption'), 5000);
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
        // Only touch the country <select> when it doesn't already show the
        // requested country. PS9's checkout (hummingbird included) treats ANY
        // "change" event on this field as a real edit and re-renders the
        // address form via an AJAX call (order?ajax=1&action=addressForm) to
        // account for country-specific fields. The HTML that call returns
        // swaps the "Continue" button for a generic <button type="submit">
        // that has lost its name="confirm-addresses" attribute, so
        // addressesContinueButton's selector stops matching anything and the
        // final click() below silently no-ops, leaving the tunnel stuck on a
        // fully-filled step 2. Every scenario defaults to the country the
        // shop already pre-selects, so this reselection is normally a no-op
        // we can — and must — skip.
        //
        // The limitation this leaves: a scenario that genuinely needs a
        // DIFFERENT country still triggers the re-render and still gets stuck.
        // That is PrestaShop's behaviour, not something a page object can work
        // around — checkout/_partials/address-form.tpl holds two submit buttons,
        // and the AJAX re-render returns the "Save" branch, which carries no
        // name attribute at all. Do not "fix" it by matching that button: it
        // submits a different form in a different context.
        if (!empty($address['country']) && !$this->countryAlreadySelected($address['country'])) {
            $this->selectValue($this->getSelector('addressCountrySelect'), $address['country']);
        }
        $this->setValue($this->getSelector('addressPhoneInput'), $address['phone'] ?? '');

        $this->click($this->getSelector('addressesContinueButton'));
        $this->waitForPageReload();
    }

    private function countryAlreadySelected(string $country): bool
    {
        $selector = $this->getSelector('addressCountrySelect');

        return $this->getPage()->evaluate(sprintf(
            '(function(){var s=document.querySelector(%s);if(!s)return false;'
            . 'var o=s.options[s.selectedIndex];return !!o && o.text.trim()===%s;})()',
            json_encode($selector),
            json_encode($country)
        ))->getReturnValue() === true;
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
