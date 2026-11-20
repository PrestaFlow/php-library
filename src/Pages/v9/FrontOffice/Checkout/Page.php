<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Checkout;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Checkout';
    public string $url = 'order';
    public ?string $shopPage = 'order';

    public function defineSelectors()
    {
        return [
            // Best-effort PS 9 checkout tunnel (order controller) — corrected live.
            'addressesContinueButton' => '#checkout-addresses-step button[name="confirm-addresses"]',
            'shippingOption' => '#checkout-delivery-step input[name^="delivery_option"]',
            'shippingOptionById' => '#checkout-delivery-step input[name^="delivery_option"][id="delivery_option_${carrierId}"]',
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
            'addressStateSelect' => '#checkout-addresses-step select[name="id_state"]',
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

    /**
     * Whether the addresses step shows the address form, i.e. the customer
     * has no address yet (a guest, or an account that never ordered).
     */
    public function hasAddressForm(): bool
    {
        return $this->elementIsVisible($this->getSelector('addressStreetInput'), 3000);
    }

    /**
     * Fill and submit the addresses step's form.
     *
     * 'country' (a label) is only selected when given: the shop's preselected
     * country stays otherwise. A missing 'postcode' becomes one valid for the
     * country shown; a required state takes 'state' (a label) or the first one.
     *
     * @param array{street?:string,city?:string,postcode?:?string,country?:?string,state?:?string,phone?:string} $address
     */
    public function fillNewAddress(array $address): void
    {
        // Country first: changing it re-renders the form for that country's
        // address format (postcode, state).
        //
        // Only touch the country <select> when it doesn't already show the
        // requested country: a "change" here makes the checkout re-render the
        // address form through an AJAX call, and re-rendering costs a round
        // trip for nothing when the value is the one already selected.
        //
        // An earlier version of this comment blamed that re-render for leaving
        // the tunnel stuck on a filled step 2, and called it PrestaShop's
        // behaviour. That was wrong. The re-render returns a form whose submit
        // button has no name="confirm-addresses" ONLY when the shop is running
        // the One Page Checkout, because the module replaces the whole checkout
        // process and OrderController::displayAjaxAddressForm() then finds no
        // CheckoutAddressesStep to read form_has_continue_button from. With the
        // module active, nothing in the real interface calls that endpoint --
        // the module points its own JS at its own controller -- so no shop is
        // affected by it.
        //
        // What was actually stuck was us: the scenario walked the four-page
        // tunnel on a shop another scenario had switched to One Page Checkout,
        // so these selectors were describing a page that was no longer there.
        // The fix for that lives in Scenario::requireFourPageCheckout(); this
        // guard stays because skipping a pointless re-render is worth doing on
        // its own, not because it works around a defect.
        if (!empty($address['country']) && !$this->countryAlreadySelected($address['country'])) {
            $this->selectValue($this->getSelector('addressCountrySelect'), $address['country']);
            $this->waitForStable(3000);
        }

        $postcode = $address['postcode'] ?? null;
        if ($postcode === null || $postcode === '') {
            $postcode = self::postcodeFor($this->selectedCountryIso($this->getSelector('addressCountrySelect')));
        }

        $this->setValue($this->getSelector('addressStreetInput'), $address['street'] ?? '');
        $this->setValue($this->getSelector('addressCityInput'), $address['city'] ?? '');
        $this->setValue($this->getSelector('addressPostcodeInput'), $postcode);
        $this->fillState($this->getSelector('addressStateSelect'), $address['state'] ?? null);
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

    /**
     * Pick a carrier and confirm the delivery step: $carrierId when given
     * (fails when the shop does not offer it here), else the first one.
     */
    public function chooseShipping(?int $carrierId = null): void
    {
        if ($carrierId !== null) {
            $selector = $this->getSelector('shippingOptionById', ['carrierId' => $carrierId]);
            Expect::that($this->elementIsVisible($selector, 5000))
                ->equals(true, 'Carrier ' . $carrierId . ' is not offered for this cart and address');
            $this->click($selector);
        } else {
            $this->click($this->getSelector('shippingOption'));
        }
        $this->click($this->getSelector('shippingContinueButton'));
        $this->waitForPageReload();
    }

    /**
     * Select the first payment option whose module is in $modules (offline
     * ones by default). Answers the module chosen, or null when the shop
     * offers none of them: the caller skips rather than pays for real.
     */
    public function choosePayment(array $modules = self::OFFLINE_PAYMENT_MODULES): ?string
    {
        return $this->clickPaymentOption($this->getSelector('paymentOption'), $modules);
    }

    /** Accept the terms and place the order, once a payment option is selected. */
    public function confirmOrder(): void
    {
        $this->click($this->getSelector('termsCheckbox'));
        $this->click($this->getSelector('placeOrderButton'));
        $this->waitForPageReload();
    }

    /**
     * Select the FIRST payment option, whatever it is, and place the order.
     * Prefer choosePayment() + confirmOrder(): the first option may be a
     * real payment provider.
     */
    public function choosePaymentAndConfirm(): void
    {
        $this->click($this->getSelector('paymentOption'));
        $this->click($this->getSelector('termsCheckbox'));
        $this->click($this->getSelector('placeOrderButton'));
        $this->waitForPageReload();
    }
}
