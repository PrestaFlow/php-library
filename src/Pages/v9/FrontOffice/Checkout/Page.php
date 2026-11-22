<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Checkout;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Checkout';
    public string $url = 'order';
    public $shopPage = 'order';

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
            'personalInformationStep' => '#checkout-personal-information-step',
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

    /**
     * Étape « Informations personnelles » en invité : remplit le formulaire,
     * coche les consentements obligatoires, vérifie le tout, puis le valide et
     * attend l'étape des adresses.
     *
     * Le remplissage n'est plus fait à l'aveugle : sur un Chrome Linux en
     * conteneur ou distant, il arrivait avant que la page de commande soit en
     * place (clic du panier lent à naviguer), et le formulaire partait vide —
     * titre, prénom, nom, e-mail vides, cases décochées — pour échouer 40 s plus
     * tard sur l'étape de livraison. On attend donc le formulaire, on relit les
     * valeurs saisies (ressaisie en JS si besoin, pfFillInputsVerified()) et un
     * formulaire toujours incomplet échoue ici, avec le nom des champs.
     */
    public function checkoutAsGuest(string $email, string $firstName, string $lastName): void
    {
        $emailInput = json_encode($this->getSelector('personalEmailInput'));
        Expect::that($this->waitForJsCondition(
            '!!document.querySelector(' . $emailInput . ')',
            20000
        ))->equals(true, 'The checkout personal information form did not show up');

        // If a guest/sign-in toggle is present, switch to the guest form.
        $this->click($this->getSelector('guestToggle'));
        // Le champ doit être affiché (onglet invité actif) avant d'y taper.
        $this->waitForJsCondition(
            '(function(e){return !!e && e.getClientRects().length > 0;})(document.querySelector(' . $emailInput . '))',
            5000
        );
        $this->waitForStable(3000);

        $unfilled = $this->pfFillInputsVerified([
            $this->getSelector('personalFirstNameInput') => $firstName,
            $this->getSelector('personalLastNameInput') => $lastName,
            $this->getSelector('personalEmailInput') => $email,
        ]);
        Expect::that($unfilled === [])->equals(true, 'Guest form fields still not filled: ' . implode(', ', $unfilled));

        // Tick every required consent checkbox (GDPR terms + data privacy) — the
        // step refuses to advance otherwise.
        $unticked = $this->pfTickRequiredCheckboxes($this->getSelector('personalInformationStep'));
        Expect::that($unticked === 0)->equals(true, 'Required consent checkboxes of the guest form are still unticked');

        $this->click($this->getSelector('personalContinueButton'));
        $this->waitForPageReload();

        // L'étape suivante : le formulaire d'adresse (invité) ou ses cartes.
        $this->waitForJsCondition(
            '!!document.querySelector(' . json_encode($this->getSelector('addressStreetInput')) . ')'
            . ' || !!document.querySelector(' . json_encode($this->getSelector('addressesContinueButton')) . ')',
            15000
        );
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
     * The addresses step whatever the account: confirm its saved address, or
     * fill and submit $address (see fillNewAddress()) when it has none yet.
     */
    public function confirmOrFillAddress(array $address): void
    {
        if ($this->hasAddressForm()) {
            $this->fillNewAddress($address);

            return;
        }

        $this->confirmAddresses();
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
        // Le formulaire doit être là avant toute saisie (page encore en
        // chargement après l'étape précédente).
        $this->waitForJsCondition(
            '!!document.querySelector(' . json_encode($this->getSelector('addressStreetInput')) . ')',
            15000
        );

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

        $postcode = $this->pfPostcodeForForm(
            $address['postcode'] ?? null,
            $this->getSelector('addressCountrySelect'),
            $this->getSelector('addressPostcodeInput')
        );

        // Même précaution que checkoutAsGuest() : saisie relue, ressaisie si
        // le texte n'a pas tenu (focus perdu, re-rendu du formulaire).
        $unfilled = $this->pfFillInputsVerified([
            $this->getSelector('addressStreetInput') => (string) ($address['street'] ?? ''),
            $this->getSelector('addressCityInput') => (string) ($address['city'] ?? ''),
            $this->getSelector('addressPostcodeInput') => $postcode,
            $this->getSelector('addressPhoneInput') => (string) ($address['phone'] ?? ''),
        ]);
        Expect::that($unfilled === [])->equals(true, 'Address form fields still not filled: ' . implode(', ', $unfilled));
        $this->pfFillState($this->getSelector('addressStateSelect'), $address['state'] ?? null);

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

    /** Pick the first carrier and confirm the delivery step. */
    public function chooseShipping(): void
    {
        $this->chooseCarrier();
    }

    /**
     * Pick a carrier and confirm the delivery step: $carrierId when given
     * (fails when the shop does not offer it here), else the first one.
     * A new method rather than a parameter on chooseShipping(): a project
     * page overriding chooseShipping() keeps a compatible signature.
     * $carrierId is taken as a suite passes it: null, '', an int or a
     * numeric string.
     */
    public function chooseCarrier(mixed $carrierId = null): void
    {
        $carrierId = self::pfCarrierId($carrierId);
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
     * Select the first payment option whose module is in $modules (a list, a
     * module name, or null for the offline ones) and answer that module. When
     * the shop offers none of them, skips the test and the rest of the suite
     * rather than pay for real.
     */
    public function choosePayment(mixed $modules = self::OFFLINE_PAYMENT_MODULES): ?string
    {
        return $this->pfPayWithOrSkip($this->getSelector('paymentOption'), $modules);
    }

    /** Accept the terms and place the order, once a payment option is selected. */
    public function acceptTermsAndPlaceOrder(): void
    {
        $this->click($this->getSelector('termsCheckbox'));
        $this->click($this->getSelector('placeOrderButton'));
        $this->waitForPageReload();
    }

    /**
     * Select the FIRST payment option, whatever it is, and place the order.
     * Prefer choosePayment() + acceptTermsAndPlaceOrder(): the first option may be a
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
