<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\OnePageCheckout;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

/**
 * One Page Checkout, shipped as the ps_onepagecheckout module since PrestaShop 9.2.
 *
 * Same controller as the classic tunnel ('order'): the module takes over through
 * actionCheckoutBuildProcess and displayOverrideTemplate, so there is no new route.
 * The DOM, however, is entirely different, and the sections refresh over AJAX —
 * hence the state-based waits instead of waitForPageReload().
 *
 * Selectors come from the module's own map, views/js/selectors.js.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Checkout';
    public string $url = 'order';

    public function defineSelectors()
    {
        return [
            'opcForm' => '#opc-form',
            // Contact section (guest only; a logged-in customer sees their account info)
            'guestEmailInput' => '#field-email',
            'contactRequiredCheckbox' => '.js-opc-contact-section input[type="checkbox"][required]',
            // Addresses: existing ones as radio cards, or an inline form when there is none
            'addressRadio' => '#opc-delivery-address-content-list .js-opc-address-radio',
            'addressRadioById' => '#opc-address-delivery-${idAddress}',
            'addressFields' => '#opc-delivery-address-fields',
            'addressCountrySelect' => '#opc-delivery-address-fields select[name="id_country"]',
            'addressFirstNameInput' => '#opc-delivery-address-fields input[name="firstname"]',
            'addressLastNameInput' => '#opc-delivery-address-fields input[name="lastname"]',
            'addressStreetInput' => '#opc-delivery-address-fields input[name="address1"]',
            'addressPostcodeInput' => '#opc-delivery-address-fields input[name="postcode"]',
            'addressCityInput' => '#opc-delivery-address-fields input[name="city"]',
            'addressPhoneInput' => '#opc-delivery-address-fields input[name="phone"]',
            // Delivery
            'carrierOption' => '#opc-delivery-methods input[name="delivery_option"]',
            // Payment
            'paymentOption' => '#opc-payment-methods input[name="payment-option"]',
            // Footer
            'termsCheckbox' => '#conditions-to-approve input[type="checkbox"]',
            'payButton' => '#opc-pay-button',
        ];
    }

    /**
     * Whether the shop currently renders the One Page Checkout.
     *
     * Doubles as an assertion after switching the layout in the back office and as
     * a branch point for a caller that supports both checkouts.
     */
    public function isOnePageCheckoutActive(): bool
    {
        return $this->elementIsVisible($this->getSelector('opcForm'), 5000);
    }

    /**
     * Guest path: fill the contact e-mail, then tick the required consent
     * checkboxes (customer_privacy, psgdpr on the reference shop).
     *
     * The module's guest-init (which creates the guest customer and persists the
     * address) cannot run while a required checkbox in the contact section is
     * unchecked: with none ticked the address form stays visible but inert, no
     * customer is created, no address is persisted, and the carrier/payment
     * sections dead-end on "Please accept the required terms above to see the
     * delivery and payment options." forever. Ticking only the checkboxes marked
     * required unblocks guest-init; optin and newsletter are marketing consents,
     * not gating conditions, so a fixture guest must not be silently opted into
     * them just to make the checkout proceed.
     */
    public function continueAsGuest(string $email): void
    {
        $this->setValueByJs($this->getSelector('guestEmailInput'), $email);

        $contactRequiredCheckbox = json_encode($this->getSelector('contactRequiredCheckbox'));

        $this->getPage()->evaluate(
            '(function(){[].slice.call(document.querySelectorAll(' . $contactRequiredCheckbox . '))'
            . '.forEach(function(c){if(!c.checked){c.click();}});})()'
        );

        $addressFields = json_encode($this->getSelector('addressFields'));

        $this->waitForCondition(
            'document.querySelector(' . $addressFields . ') '
            . '&& !document.querySelector(' . $addressFields . ').classList.contains("d-none")'
        );
    }

    /**
     * Pick an existing address card. Without an id, the first card is used, which
     * keeps callers free of hardcoded fixture ids.
     *
     * Selecting an address triggers the carrier fetch, so we wait for the carrier
     * list rather than returning while the section still shows its loader.
     */
    public function selectAddress(?int $idAddress = null): void
    {
        $selector = $idAddress === null
            ? $this->getSelector('addressRadio')
            : $this->getSelector('addressRadioById', ['idAddress' => $idAddress]);

        $this->click($selector);

        $this->waitForCarriers();
    }

    /**
     * Fill the inline address form (guest, or a customer with no address yet).
     *
     * Country goes first on purpose: changing id_country re-renders the whole form
     * from the back end (address format per country), which would wipe fields set
     * before it. The other fields autosave on input, debounced by the module, so
     * there is no save button to click — the carrier list appearing is the signal
     * that the address was persisted.
     *
     * @param array{firstName?:string,lastName?:string,street?:string,postcode?:string,city?:string,country?:string,phone?:string} $address
     */
    public function fillNewAddress(array $address): void
    {
        if (!empty($address['country'])) {
            $this->selectValue($this->getSelector('addressCountrySelect'), $address['country']);
            // The re-render replaces the inputs: wait for the street field to be
            // back in the DOM before typing into it.
            $this->waitForCondition(
                '!!document.querySelector(' . json_encode($this->getSelector('addressStreetInput')) . ')'
            );
        }

        foreach ([
            'addressFirstNameInput' => $address['firstName'] ?? '',
            'addressLastNameInput' => $address['lastName'] ?? '',
            'addressStreetInput' => $address['street'] ?? '',
            'addressPostcodeInput' => $address['postcode'] ?? '',
            'addressCityInput' => $address['city'] ?? '',
            'addressPhoneInput' => $address['phone'] ?? '',
        ] as $selectorKey => $value) {
            if ($value === '') {
                continue;
            }

            $this->setValueByJs($this->getSelector($selectorKey), $value);
        }

        $this->waitForCarriers();
    }

    /**
     * Select the first available carrier, then wait for the payment options the
     * module fetches in response.
     */
    public function selectFirstCarrier(): void
    {
        $this->waitForCarriers();
        $this->click($this->getSelector('carrierOption'));

        $this->waitForCondition(
            '!!document.querySelector(' . json_encode($this->getSelector('paymentOption')) . ')'
        );
    }

    /**
     * Whether the customer already has saved addresses to pick from.
     *
     * A customer who has never ordered — a freshly registered fixture, for
     * instance — gets the inline address form instead of the radio cards, so a
     * caller cannot assume selectAddress() is the right move.
     */
    public function hasSavedAddresses(): bool
    {
        return $this->elementIsVisible($this->getSelector('addressRadio'), 3000);
    }

    /**
     * Whether the delivery section has resolved to an actual carrier list.
     *
     * The section starts as an "awaiting address" placeholder and is only
     * replaced once the buyer is identified AND their address is persisted.
     * That makes it the assertion worth writing after the address steps: it is
     * what distinguishes "the address went through" from "the module is still
     * waiting on something", which otherwise only surfaces much later as an
     * unexplained timeout.
     */
    public function hasCarriers(): bool
    {
        return $this->elementIsVisible($this->getSelector('carrierOption'), 5000);
    }

    /**
     * Whether the module considers the checkout complete enough to submit.
     *
     * The pay button is rendered disabled and only enabled by the module's own
     * validateForm(), once address, carrier, payment and terms are all valid —
     * so this is the single honest "ready" signal, and the right thing to
     * assert before placing the order.
     */
    public function isReadyToPlaceOrder(): bool
    {
        return $this->waitForCondition(
            '!document.querySelector(' . json_encode($this->getSelector('payButton')) . ').disabled',
            5000
        );
    }

    /**
     * Select the first payment option.
     */
    public function selectFirstPayment(): void
    {
        $this->click($this->getSelector('paymentOption'));
    }

    /**
     * Tick every required terms checkbox, then wait for the pay button to be
     * enabled: the module's validateForm() only enables it once address, carrier,
     * payment and terms are all valid, which makes it the single reliable signal
     * that the checkout is ready to submit.
     */
    public function acceptTerms(): void
    {
        $termsCheckbox = json_encode($this->getSelector('termsCheckbox'));

        $this->getPage()->evaluate(
            '(function(){[].slice.call(document.querySelectorAll(' . $termsCheckbox . '))'
            . '.forEach(function(c){if(!c.checked){c.click();}});})()'
        );

        $this->waitForCondition(
            '!document.querySelector(' . json_encode($this->getSelector('payButton')) . ').disabled'
        );
    }

    /**
     * Submit the order. The module posts over fetch and then navigates with
     * window.location.href, so this is a real navigation.
     */
    public function placeOrder(): void
    {
        $this->click($this->getSelector('payButton'));
        $this->waitForPageReload();
    }

    /**
     * Wait for the carrier list to be rendered inside its placeholder. The section
     * starts as an "awaiting address" message and is replaced once the address is
     * known, so we wait for a radio input, not for the container.
     */
    private function waitForCarriers(): void
    {
        $this->waitForCondition(
            '!!document.querySelector(' . json_encode($this->getSelector('carrierOption')) . ')'
        );
    }
}
