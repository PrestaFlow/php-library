<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Addresses;

use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

/**
 * The customer's address book ("Your addresses").
 *
 * Each address is an <article data-id-address> in Classic (1.7 to 9, alias in
 * .address-body h4) and in hummingbird (.address-card, alias in
 * .address-card__alias). An address is found by its alias, never by its
 * position: the account may hold addresses other scenarios rely on, and only
 * the one asked for is ever opened or deleted. The links are the theme's own
 * data-link-action ones, whatever the language.
 */
class Page extends BasePage
{
    public string $url = 'addresses';
    public $shopPage = 'addresses';

    /** Skip reason of requireExistingAddress(). */
    public const NO_EXISTING_ADDRESS = 'The customer account has no address yet: PrestaShop would give the new address'
        . ' to the customer\'s cart, then refuse to delete it. Give the account an address first (a checkout scenario adds one).';

    public function defineSelectors()
    {
        return [
            'addressBlock' => 'article[data-id-address]',
            'addressAlias' => '.address-body h4, .address-card__alias',
            'addressContent' => 'address',
            'addAddressLink' => 'a[data-link-action="add-address"]',
            'editAddressLink' => 'article[data-id-address="${idAddress}"] a[data-link-action="edit-address"]',
            'deleteAddressLink' => 'article[data-id-address="${idAddress}"] a[data-link-action="delete-address"]',
        ];
    }

    /**
     * Every address of the list, as id_address => ['alias' => ..., 'text' => ...]
     * (text: the formatted address, whitespace collapsed).
     */
    public function readAddresses(): array
    {
        // The "add" link closes the list in both themes: once it is there, the
        // list is rendered.
        $this->elementIsVisible($this->getSelector('addAddressLink'), 5000);

        $json = $this->getPage()->evaluate(sprintf(
            '(function(){var out={};[].slice.call(document.querySelectorAll(%s)).forEach(function(b){'
            . 'var a=b.querySelector(%s),t=b.querySelector(%s);'
            . 'out[b.getAttribute("data-id-address")]={alias:a?a.textContent.trim():"",'
            . 'text:t?(t.innerText||t.textContent).replace(/\s+/g," ").trim():""};});return JSON.stringify(out);})()',
            json_encode($this->getSelector('addressBlock')),
            json_encode($this->getSelector('addressAlias')),
            json_encode($this->getSelector('addressContent'))
        ))->getReturnValue();

        $addresses = is_string($json) ? json_decode($json, true) : null;

        return is_array($addresses) ? $addresses : [];
    }

    /** The id_address of the address named $alias, null when the list has none. */
    public function addressIdByAlias(mixed $alias): ?string
    {
        $alias = is_scalar($alias) ? trim((string) $alias) : '';

        foreach ($this->readAddresses() as $id => $address) {
            if ($alias !== '' && $address['alias'] === $alias) {
                return (string) $id;
            }
        }

        return null;
    }

    /** Whether the list shows an address named $alias. */
    public function hasAddress(mixed $alias): bool
    {
        return $this->addressIdByAlias($alias) !== null;
    }

    /** The ids of the addresses listed (id_address, as strings). */
    public function addressIds(): array
    {
        return array_map('strval', array_keys($this->readAddresses()));
    }

    /**
     * Skip the test and the rest of the suite when the account has no address.
     *
     * The first address of an account becomes the address of the customer's
     * open cart (FrontController fills a cart that has none), and PrestaShop
     * refuses to delete an address the cart uses: a scenario that adds then
     * deletes an address needs the account to hold another one already.
     */
    public function requireExistingAddress(): void
    {
        if ($this->addressIds() === []) {
            throw new SkipTestException(self::NO_EXISTING_ADDRESS, skipRemaining: true);
        }
    }

    /** Whether every id of $ids is still listed (others may have been added). */
    public function hasAddressIds(mixed $ids): bool
    {
        $listed = $this->addressIds();

        return array_diff(array_map('strval', (array) $ids), $listed) === [];
    }

    /** The formatted address named $alias (whitespace collapsed), '' when not listed. */
    public function addressText(mixed $alias): string
    {
        $id = $this->addressIdByAlias($alias);

        return $id === null ? '' : (string) ($this->readAddresses()[$id]['text'] ?? '');
    }

    /** Open the new address form through the list's own "add" link. */
    public function openNewAddressForm(): void
    {
        $this->pfFollowLink($this->getSelector('addAddressLink'));
    }

    /** Open the edit form of the address named $alias, and only that one. */
    public function editAddress(mixed $alias): void
    {
        $id = $this->requireAddressId($alias);

        $this->pfFollowLink($this->getSelector('editAddressLink', ['idAddress' => $id]));
    }

    /**
     * Delete the address named $alias, and only that one, through its own
     * delete link (it carries the shop's token).
     */
    public function deleteAddress(mixed $alias): void
    {
        $id = $this->requireAddressId($alias);

        $this->pfFollowLink($this->getSelector('deleteAddressLink', ['idAddress' => $id]));
    }

    /**
     * Navigate to the href of a link, in the same session. Clicking it could
     * miss on a page still settling (an alert shifting the list), and then
     * nothing happened while the step went on: following the URL is what the
     * click does, without the race.
     */
    protected function pfFollowLink(string $selector): void
    {
        $this->elementIsVisible($selector, 5000);

        $href = $this->getPage()->evaluate(sprintf(
            '(function(){var a=document.querySelector(%s);return a?a.href:null;})()',
            json_encode($selector)
        ))->getReturnValue();

        Expect::that($href)->isNotNull('No link matches "' . $selector . '"');

        $this->goToUrlInPlace((string) $href);
    }

    private function requireAddressId(mixed $alias): string
    {
        $id = $this->addressIdByAlias($alias);
        if ($id === null) {
            Expect::that($id)->isNotNull('No address named "' . (is_scalar($alias) ? $alias : '') . '" in the address book');
        }

        return (string) $id;
    }
}
