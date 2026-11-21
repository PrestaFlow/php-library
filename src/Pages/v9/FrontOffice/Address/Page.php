<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Address;

use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Pages\v9\FrontOffice\Addresses\Page as BasePage;

/**
 * The address form of the customer account (new address or update).
 *
 * Classic (1.7 to 9) and hummingbird render the same .js-address-form with
 * the fields PrestaShop posts (alias, firstname, address1, postcode, city,
 * id_country...), so the selectors use those names, whatever the language.
 * Extends the address book page: saving the form lands back on it.
 */
class Page extends BasePage
{
    public string $url = 'address';
    public $shopPage = 'address';

    /** Skip reason of requireAddressForm(). */
    public const NO_ADDRESS_FORM = 'This shop shows no address form on its address page'
        . ' (the customer address book is replaced or hidden by a module or the theme).';

    public function defineSelectors()
    {
        return [
            ...parent::defineSelectors(),
            'addressForm' => '.js-address-form form',
            'aliasInput' => '.js-address-form input[name="alias"]',
            'firstNameInput' => '.js-address-form input[name="firstname"]',
            'lastNameInput' => '.js-address-form input[name="lastname"]',
            'streetInput' => '.js-address-form input[name="address1"]',
            'postcodeInput' => '.js-address-form input[name="postcode"]',
            'cityInput' => '.js-address-form input[name="city"]',
            'countrySelect' => '.js-address-form select[name="id_country"]',
            'stateSelect' => '.js-address-form select[name="id_state"]',
            'phoneInput' => '.js-address-form input[name="phone"]',
            'submitButton' => '.js-address-form button[type="submit"]',
        ];
    }

    /** Whether the page shows the address form. */
    public function hasAddressForm(): bool
    {
        return $this->elementIsVisible($this->getSelector('streetInput'), 5000);
    }

    /** Skip the test and the rest of the suite when the page has no address form. */
    public function requireAddressForm(): void
    {
        if (!$this->hasAddressForm()) {
            throw new SkipTestException(self::NO_ADDRESS_FORM, skipRemaining: true);
        }
    }

    /**
     * Fill the form and save it.
     *
     * 'country' (a label) is only selected when given: the country the shop
     * preselects stays otherwise. A missing 'postcode' becomes one valid for
     * the country shown (the test is skipped for a country we know no format
     * of, when the postcode is required); a required state takes 'state' (a
     * label) or the first one. 'firstname' / 'lastname' only fill an empty
     * field: the shop prefills the customer's name.
     *
     * @param array{alias?:string,firstname?:?string,lastname?:?string,street?:string,city?:string,postcode?:?string,country?:?string,state?:?string,phone?:?string} $address
     */
    public function saveAddress(array $address): void
    {
        // Country first: changing it re-renders the form (AJAX) for that
        // country's address format, which drops what was typed before.
        if (!empty($address['country'])) {
            $this->selectOption($this->getSelector('countrySelect'), $address['country']);
            $this->waitForStable(3000);
        }

        $postcode = $this->pfPostcodeForForm(
            $address['postcode'] ?? null,
            $this->getSelector('countrySelect'),
            $this->getSelector('postcodeInput')
        );

        $this->setValueByJs($this->getSelector('aliasInput'), (string) ($address['alias'] ?? ''));
        $this->pfFillIfEmpty($this->getSelector('firstNameInput'), (string) ($address['firstname'] ?? '') ?: 'PrestaFlow');
        $this->pfFillIfEmpty($this->getSelector('lastNameInput'), (string) ($address['lastname'] ?? '') ?: 'Test');
        $this->setValueByJs($this->getSelector('streetInput'), (string) ($address['street'] ?? ''));
        $this->setValueByJs($this->getSelector('postcodeInput'), $postcode);
        $this->setValueByJs($this->getSelector('cityInput'), (string) ($address['city'] ?? ''));
        $this->pfFillState($this->getSelector('stateSelect'), $address['state'] ?? null);
        if (($address['phone'] ?? '') !== '') {
            $this->setValueByJs($this->getSelector('phoneInput'), (string) $address['phone']);
        }

        $this->submitAddressForm();
    }

    /** Change the city of the address being edited, and save it. */
    public function changeCity(mixed $city): void
    {
        $this->setValueByJs($this->getSelector('cityInput'), is_scalar($city) ? (string) $city : '');

        $this->submitAddressForm();
    }

    private function submitAddressForm(): void
    {
        // requestSubmit() rather than a mouse click: it cannot miss a button
        // that is moving, and still runs the theme's submit listeners
        // (hummingbird validates the form on "submit").
        $this->getPage()->evaluate(sprintf(
            '(function(){var b=document.querySelector(%s);if(!b)return;'
            . 'if(b.form&&b.form.requestSubmit){b.form.requestSubmit(b);}else{b.click();}})()',
            json_encode($this->getSelector('submitButton'))
        ));
        $this->waitForPageReload();
    }

    private function pfFillIfEmpty(string $selector, string $value): void
    {
        $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);if(!e||e.value.trim()!=="")return;e.value=%s;'
            . 'e.dispatchEvent(new Event("input",{bubbles:true}));e.dispatchEvent(new Event("change",{bubbles:true}));})()',
            json_encode($selector),
            json_encode($value)
        ));
    }
}
