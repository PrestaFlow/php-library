<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\CheckoutLayout;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

/**
 * Back-office configuration screen of the ps_onepagecheckout module
 * ("Preferences > Themes > Checkout" tab), used to switch the shop between
 * the one-page and the classic four-page checkout before front-office
 * scenarios run.
 *
 * The page is reached through the sidebar rather than by building the admin
 * URL by hand: goToSubMenu() follows the anchor's resolved href, which
 * carries a valid security token. A hand-built URL to the legacy
 * AdminPsOnePageCheckout controller would be rejected with an "Invalid
 * security token" page instead.
 *
 * Applying a layout is a two-step save: clicking #psopc-save-btn does not
 * submit anything (it is a plain type="button"), it only opens the
 * confirmation modal. The form is only actually submitted when
 * #psopc-confirm-btn, inside that modal, is clicked. The modal also carries
 * a maintenance-mode checkbox (#psopc-maintenance-toggle) which this page
 * object deliberately never touches, since enabling it would close the
 * front office to the checkout steps that run right after this switch.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Checkout';
    public string $menuSelector = '#subtab-AdminPsOnePageCheckout';
    public string $parentMenuSelector = '#subtab-AdminParentThemes';

    public function defineSelectors()
    {
        return [
            'onePageChoice' => '#PS_ONE_PAGE_CHECKOUT_ENABLED_one_page',
            'fourPageChoice' => '#PS_ONE_PAGE_CHECKOUT_ENABLED_four_page',
            'saveButton' => '#psopc-save-btn',
            'confirmationModal' => '#psopc-confirmation-modal',
            'confirmButton' => '#psopc-confirm-btn',
        ];
    }

    /**
     * @param int $idShop the shop to configure; only matters on a multistore
     *                    installation, where this page renders nothing until
     *                    the back office is in a single shop context
     */
    public function goTo(int $idShop = 1): void
    {
        $this->setSingleShopContext($idShop);

        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    public function switchToOnePageCheckout(): void
    {
        $this->applyLayout($this->getSelector('onePageChoice'));
    }

    public function switchToFourPageCheckout(): void
    {
        $this->applyLayout($this->getSelector('fourPageChoice'));
    }

    /**
     * Select the radio for the target layout, open the confirmation modal,
     * wait for its fade-in transition to complete, then confirm.
     */
    /**
     * Whether the shop is currently configured for the one-page checkout.
     *
     * Read from the radio's live `checked` property after the page reloads, so
     * it reflects what was actually saved rather than what was clicked. Lets a
     * caller assert that the switch took effect — clicking through the modal
     * proves nothing on its own, and a back office that rejected the session
     * would leave the radio untouched.
     */
    public function isOnePageCheckoutSelected(): bool
    {
        return $this->isChoiceChecked($this->getSelector('onePageChoice'));
    }

    /**
     * The symmetric accessor exists so a scenario that needs the four-page
     * tunnel can assert the state it requires, instead of asserting the absence
     * of the other one. Reading the live `checked` property rather than the
     * `checked` attribute: the attribute reflects the markup as served, not what
     * the radio group holds after a click.
     */
    public function isFourPageCheckoutSelected(): bool
    {
        return $this->isChoiceChecked($this->getSelector('fourPageChoice'));
    }

    private function isChoiceChecked(string $selector): bool
    {
        $selector = json_encode($selector);

        return $this->getPage()->evaluate(
            '(function(){var e=document.querySelector(' . $selector . ');return !!(e&&e.checked);})()'
        )->getReturnValue() === true;
    }

    private function applyLayout(string $choiceSelector): void
    {
        $this->click($choiceSelector);
        $this->click($this->getSelector('saveButton'));

        // Bootstrap adds the "show" class once the modal's fade-in transition
        // is done; confirming before that can miss the click target.
        $modalSelector = json_encode($this->getSelector('confirmationModal'));
        $this->waitForCondition(
            'document.querySelector(' . $modalSelector . ')'
            . '&&document.querySelector(' . $modalSelector . ').classList.contains("show")'
        );

        $this->click($this->getSelector('confirmButton'));
        $this->waitForPageReload();
    }
}
