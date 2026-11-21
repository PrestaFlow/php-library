<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * The customer manages the address book: adds an address, finds it in the
 * list, changes its city, then deletes it.
 *
 * The address carries an alias unique to the run ("PF test <suffix>") and is
 * only ever found by it: the account's other addresses are never opened nor
 * deleted (other scenarios order with them), and the last step checks they
 * are all still there. The country stays the one the shop preselects unless
 * addressCountry is set; the postcode is one valid for that country.
 *
 * Needs the project's customer account (FO_EMAIL / FO_PASSWD) with at least
 * one address (a checkout scenario adds one): the first address of an account
 * becomes its cart's address, which PrestaShop refuses to delete. Without the
 * account, or without an address on it, the suite is skipped.
 */
class AddressBook extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => the project's customer account (FO_EMAIL / FO_PASSWD).
        'customerEmail' => null,
        'customerPassword' => null,
        // null => 'PF test <unique suffix>' (an alias is 32 characters at most).
        'addressAlias' => null,
        'addressStreet' => '16 Main street',
        'addressCity' => 'Paris',
        // The city the address is changed to.
        'addressNewCity' => 'Lyon',
        // null => a postcode valid for the country the form shows.
        'addressPostcode' => null,
        // null => the country the shop preselects (a label otherwise).
        'addressCountry' => null,
        // null => the first state when the country has states (a label otherwise).
        'addressState' => null,
        'addressPhone' => '0102030405',
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $this->params['addressAlias'] = ($this->params['addressAlias'] ?? null) ?: 'PF test ' . self::pfUniqueSuffix();

        $testSuite->importPage('FrontOffice\Login');
        $testSuite->importPage('FrontOffice\Addresses');
        $testSuite->importPage('FrontOffice\Address');

        extract($testSuite->pages);

        $testSuite
        ->it('log in on the FrontOffice', function () use ($frontOfficeLoginPage) {
            // Skips the suite when no customer account is configured.
            $frontOfficeLoginPage->goToPage('login');
            $frontOfficeLoginPage->login(
                $this->getParam('customerEmail'),
                $this->getParam('customerPassword')
            );

            Expect::that($frontOfficeLoginPage->isLoggedIn())->equals(true);
        })
        ->it('open the address book', function () use ($frontOfficeAddressesPage) {
            $frontOfficeAddressesPage->goToPage($frontOfficeAddressesPage);

            Expect::that($frontOfficeAddressesPage->getDocumentStatus())->isBetween(200, 399);

            // Skips the suite when the account has no address: the new one
            // would become the cart's address, which the shop refuses to delete.
            $frontOfficeAddressesPage->requireExistingAddress();

            // The addresses the account already has: they must survive the run.
            $this->store('otherAddressIds', $frontOfficeAddressesPage->addressIds());
        })
        ->it('add an address', function () use ($frontOfficeAddressesPage, $frontOfficeAddressPage) {
            $frontOfficeAddressesPage->openNewAddressForm();

            // Skips the suite when the page shows no address form.
            $frontOfficeAddressPage->requireAddressForm();
            $frontOfficeAddressPage->saveAddress([
                'alias' => $this->getParam('addressAlias'),
                'street' => $this->getParam('addressStreet'),
                'city' => $this->getParam('addressCity'),
                'postcode' => $this->getParam('addressPostcode'),
                'country' => $this->getParam('addressCountry'),
                'state' => $this->getParam('addressState'),
                'phone' => $this->getParam('addressPhone'),
            ]);

            Expect::that($frontOfficeAddressesPage->hasAddress($this->getParam('addressAlias')))->equals(true);
        })
        ->it('the new address is in the address book', function () use ($frontOfficeAddressesPage) {
            $frontOfficeAddressesPage->goToPage($frontOfficeAddressesPage);

            Expect::that($frontOfficeAddressesPage->addressText($this->getParam('addressAlias')))->contains($this->getParam('addressStreet'));
            Expect::that($frontOfficeAddressesPage->addressText($this->getParam('addressAlias')))->contains($this->getParam('addressCity'));
        })
        ->it('change the city of the address', function () use ($frontOfficeAddressesPage, $frontOfficeAddressPage) {
            $frontOfficeAddressesPage->editAddress($this->getParam('addressAlias'));
            $frontOfficeAddressPage->changeCity($this->getParam('addressNewCity'));

            Expect::that($frontOfficeAddressesPage->addressText($this->getParam('addressAlias')))->contains($this->getParam('addressNewCity'));
        })
        ->it('delete the address', function () use ($frontOfficeAddressesPage) {
            $frontOfficeAddressesPage->deleteAddress($this->getParam('addressAlias'));
            $frontOfficeAddressesPage->goToPage($frontOfficeAddressesPage);

            Expect::that($frontOfficeAddressesPage->hasAddress($this->getParam('addressAlias')))->equals(false);
        })
        ->it('the other addresses are untouched', function () use ($frontOfficeAddressesPage) {
            Expect::that($frontOfficeAddressesPage->hasAddressIds($this->retrieve('otherAddressIds')))->equals(true);
        });

        return $testSuite;
    }
}
