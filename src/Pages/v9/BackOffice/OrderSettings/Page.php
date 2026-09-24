<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\OrderSettings;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

/**
 * Shop Parameters > Order Settings (AdminOrderPreferences).
 *
 * This page currently carries only what the One Page Checkout scenarios
 * need: toggling the "Enable guest checkout" switch (PS_GUEST_CHECKOUT_ENABLED),
 * which lives here rather than in the module's own configuration page. Extend
 * it with more of the "General" tab (or other Order Settings tabs) as needed.
 *
 * PrestaShop renders this switch as a PAIR OF RADIO INPUTS (SwitchType), not
 * a checkbox, named "...[enable_guest_checkout]" with values "1" and "0". The
 * selector below matches on that field-name suffix rather than on a specific
 * generated id, so it survives a form block-prefix change.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Order settings';
    // In 9.2 there is no AdminOrderPreferences child entry: the sidebar item
    // that opens Order Settings IS AdminParentOrderPreferences, whose href
    // points straight at /configure/shop/order-preferences/. Verified against a
    // live 9.2 dashboard — a probe of every [id^="subtab-"] returns no
    // subtab-AdminOrderPreferences at all.
    public string $menuSelector = '#subtab-AdminParentOrderPreferences';
    public string $parentMenuSelector = '#subtab-ShopParameters';

    public function defineSelectors()
    {
        return [
            'guestCheckoutRadio' => '#configuration_general_form input[type="radio"][name$="[enable_guest_checkout]"][value="${value}"]',
            'saveButton' => '#form-general-save-button',
        ];
    }

    /**
     * @param int $idShop the shop the setting is written for
     *
     * Configuration in PrestaShop resolves shop, then shop group, then global,
     * so a value saved while the back office is on "all shops" does not land
     * where a single-shop read will look for it. Pinning the context makes the
     * write land on a named shop instead of wherever the session happened to be.
     */
    public function goTo(int $idShop = 1): void
    {
        $this->setSingleShopContext($idShop);

        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    /**
     * Whether guest checkout is currently enabled, read from the "1" radio's
     * live `checked` property (not the `checked` attribute, which only
     * reflects the initial server-rendered state).
     */
    public function isGuestCheckoutEnabled(): bool
    {
        $enabledSelector = json_encode($this->getSelector('guestCheckoutRadio', ['value' => '1']));

        $checked = $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return e?e.checked:false;})()',
            $enabledSelector
        ))->getReturnValue();

        return $checked === true;
    }

    /**
     * Turns guest checkout on or off. No-op when it already is in the wanted
     * state, otherwise selects the matching radio, saves the form, and waits
     * for the resulting reload.
     */
    public function setGuestCheckout(bool $enabled): void
    {
        if ($this->isGuestCheckoutEnabled() === $enabled) {
            return;
        }

        $radioSelector = $this->getSelector('guestCheckoutRadio', ['value' => $enabled ? '1' : '0']);
        $this->click($radioSelector);

        $this->click($this->getSelector('saveButton'));
        $this->waitForPageReload();
    }
}
