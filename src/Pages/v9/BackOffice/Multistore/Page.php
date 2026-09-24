<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Multistore;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

/**
 * Advanced Parameters > Multistore.
 *
 * INCOMPLETE, on purpose and said out loud: everything up to and including
 * filling the add-shop form works and is verified — the menu is reached, the
 * form opens, and every field is set (name, shop group, root category, all
 * associated categories, theme, "import data", source shop, and all 26 import
 * checkboxes). What does NOT work yet is the save: neither a coordinate click
 * nor a DOM click on button[name=submitAddshop] creates the shop, and the page
 * comes back with no alert to read. Diagnosing that is left for later.
 *
 * So: use it to reach and inspect the Multistore screens. Do not rely on
 * createShopImportingEverything() to actually create a shop until the
 * submission is understood — it will fill the form and leave you on it.
 *
 * Worth knowing about the screens themselves: the Multistore menu only exists
 * when the AdminShopGroup and AdminShopUrl tabs are active in ps_tab. A shop
 * where multistore was switched on by writing PS_MULTISHOP_FEATURE_ACTIVE in
 * SQL has the feature enabled and no way to manage it, which is how this
 * container was found.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Multistore';
    public string $menuSelector = '#subtab-AdminShopGroup';
    public string $parentMenuSelector = '#subtab-AdminAdvancedParameters';

    public function defineSelectors()
    {
        return [
            'addShopButton' => '#page-header-desc-shop_group-new_2',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    /**
     * Create a shop through the real Multistore form, importing everything the
     * form offers from $sourceShopId.
     */
    public function createShopImportingEverything(string $name, int $sourceShopId = 1, string $theme = 'classic'): void
    {
        $this->click($this->getSelector('addShopButton'));
        $this->waitForPageReload();

        $this->getPage()->evaluate(sprintf(
            '(function(){'
            . 'document.querySelector("#name").value=%s;'
            . 'var g=document.querySelector("#id_shop_group"); if(g){g.value="1";g.dispatchEvent(new Event("change",{bubbles:true}));}'
            . '[].slice.call(document.querySelectorAll("input[name=\'categoryBox[]\']")).forEach(function(c){c.checked=true;});'
            . '[].slice.call(document.querySelectorAll("input[name=theme_name]")).forEach(function(r){if(r.value===%s){r.checked=true;r.dispatchEvent(new Event("change",{bubbles:true}));}});'
            . 'var on=document.querySelector("#useImportData_on"); if(on){on.checked=true;on.dispatchEvent(new Event("change",{bubbles:true}));}'
            . 'var src=document.querySelector("#importFromShop"); if(src){src.value=%d;src.dispatchEvent(new Event("change",{bubbles:true}));}'
            . '[].slice.call(document.querySelectorAll("input[type=checkbox][name^=importData]")).forEach(function(c){c.checked=true;});'
            . '})()',
            json_encode($name),
            json_encode($theme),
            $sourceShopId
        ));

        // A real DOM click on the named submit, not a coordinate click: the
        // page has two Save buttons and the one that carries submitAddshop sits
        // below the fold. PrestaShop needs that name in the POST, so
        // form.submit() would not do either.
        $this->getPage()->evaluate(
            '(function(){var b=document.querySelector("button[name=submitAddshop],input[name=submitAddshop]");'
            . 'if(b){b.click();return true;}return false;})()'
        );
        $this->waitForPageReload();
    }

    /** Any validation errors the save came back with, as plain text. */
    public function formErrors(): string
    {
        return (string) $this->getPage()->evaluate(
            '(function(){var e=[].slice.call(document.querySelectorAll(".alert-danger,.alert-warning,.has-error"));'
            . 'return e.map(function(x){return (x.textContent||"").trim().replace(/\\s+/g," ").slice(0,220);}).join(" || ").slice(0,700);})()'
        )->getReturnValue();
    }

    /** True when the save left the add-shop form, i.e. it was accepted. */
    public function leftTheAddForm(): bool
    {
        return !str_contains($this->currentUrl(), 'addshop');
    }

    public function currentUrl(): string
    {
        return (string) $this->getPage()->evaluate('location.href')->getReturnValue();
    }
}
