<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\CartRules;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

/**
 * Catalog > Discounts > Cart rules (AdminCartRules), a legacy controller in
 * 1.7, 8 and 9 alike (9's "discount" feature flag, off by default, is not
 * covered).
 *
 * The form has three tabs (Information, Conditions, Actions) that are all in
 * the page at once, only hidden: the fields are named like the CartRule
 * properties (name_<id_lang>, code, quantity, apply_discount,
 * reduction_percent, reduction_amount) and are the same in every version.
 *
 * The list is a HelperList (#table-cart_rule). It is read as it is, never
 * filtered: a legacy list filter is kept in the employee's cookie and would
 * stay applied for whoever opens the list next.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Cart Rules';
    public string $menuSelector = '#subtab-AdminCartRules';
    public string $parentMenuSelector = '#subtab-AdminParentCartRules';

    public function defineSelectors()
    {
        return [
            'listTable' => '#table-cart_rule, #form-cart_rule',
            'listRows' => '#table-cart_rule tbody tr',
            // The toolbar's "Add new cart rule" button.
            'newCartRuleButton' => '#page-header-desc-cart_rule-new_cart_rule',
            'form' => '#cart_rule_form',
            'nameInputs' => '#cart_rule_informations input[name^="name_"]',
            'codeInput' => '#cart_rule_informations input[name="code"]',
            'conditionsTab' => '#cart_rule_link_conditions',
            'actionsTab' => '#cart_rule_link_actions',
            'quantityInput' => '#cart_rule_conditions input[name="quantity"]',
            'quantityPerUserInput' => '#cart_rule_conditions input[name="quantity_per_user"]',
            'applyDiscountRadio' => '#apply_discount_${type}',
            'reductionPercentInput' => '#reduction_percent',
            'reductionAmountInput' => '#reduction_amount',
            'applyToOrderRadio' => '#apply_discount_to_order',
            'saveButton' => '#cart_rule_form button[name="submitAddcart_rule"]',
            'errorAlert' => '#content .alert-danger',
        ];
    }

    /**
     * Open the list through the menu. From a front-office page (a scenario
     * that went to the shop in between), the dashboard is opened first: the
     * menu entry, which carries the token, only exists in the back office.
     */
    public function goTo(): void
    {
        if (!$this->elementIsVisible($this->menuSelector . ', ' . $this->parentMenuSelector, 1000)) {
            $this->goToPage('index');
        }

        // The child entry first; where the sidebar only has the parent entry
        // (9.2), that entry links to the list itself.
        $this->goToMenu($this->menuSelector . ', ' . $this->parentMenuSelector);
        $this->waitForJsCondition(sprintf('!!document.querySelector(%s)', json_encode($this->getSelector('listTable'))), 10000);
    }

    /**
     * Create a cart rule with a code, available to everyone, $quantity times
     * in all, that takes $percent % off the order, or $amount off it (in the
     * default currency, tax included) when $amount is given.
     *
     * The name is given in every language of the form, so the voucher line
     * of the cart carries it whatever the shop's language. Every other field
     * keeps its default: valid from now for a month, no restriction.
     */
    public function createCartRule(string $name, string $code, float $percent = 10, ?float $amount = null, int $quantity = 1): void
    {
        $this->goTo();
        $this->goToNewCartRule();

        $this->getPage()->evaluate(sprintf(
            '(function(sel,v){[].slice.call(document.querySelectorAll(sel)).forEach(function(e){'
            . 'if(e.type!=="text")return;e.value=v;e.dispatchEvent(new Event("change",{bubbles:true}));});})(%s,%s)',
            json_encode($this->getSelector('nameInputs')),
            json_encode($name)
        ));
        $this->setValueByJs($this->getSelector('codeInput'), $code);

        $this->click($this->getSelector('conditionsTab'));
        $this->setValueByJs($this->getSelector('quantityInput'), (string) $quantity);
        $this->setValueByJs($this->getSelector('quantityPerUserInput'), (string) $quantity);

        $this->click($this->getSelector('actionsTab'));
        if ($amount !== null) {
            $this->click($this->getSelector('applyDiscountRadio', ['type' => 'amount']));
            $this->setValueByJs($this->getSelector('reductionAmountInput'), (string) $amount);
        } else {
            $this->click($this->getSelector('applyDiscountRadio', ['type' => 'percent']));
            $this->setValueByJs($this->getSelector('reductionPercentInput'), (string) $percent);
        }
        $this->click($this->getSelector('applyToOrderRadio'));

        $this->click($this->getSelector('saveButton'));
        $this->waitForPageReload();
        $this->waitForJsCondition(sprintf(
            '!!document.querySelector(%s)||!!document.querySelector(%s)',
            json_encode($this->getSelector('listRows')),
            json_encode($this->getSelector('errorAlert'))
        ), 10000);
    }

    /** The error the last save left on the page, '' when there is none. */
    public function getErrorMessage(): string
    {
        $text = $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return e?e.textContent:"";})()',
            json_encode($this->getSelector('errorAlert'))
        ))->getReturnValue();

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    /** Number of cart rules of the list (first page, newest first) whose name contains $name. */
    public function countCartRulesNamed(string $name): int
    {
        $this->goTo();

        return count($this->pfDeleteLinksOf($name));
    }

    /**
     * Delete every cart rule whose name contains $name (at most $max), for a
     * scenario to remove its own rule or what an earlier run left behind when
     * it stopped before its cleanup. Answers how many were deleted.
     *
     * The row's delete URL is opened directly. The "Delete" link itself goes
     * nowhere (href="#"): in 1.7, 8 and 9 its onclick opens a confirmation
     * modal, confirm_link(..., url, '#'), which carries the URL.
     */
    public function deleteCartRulesNamed(string $name, int $max = 10): int
    {
        $deleted = 0;
        $this->goTo();

        while ($deleted < $max) {
            $links = $this->pfDeleteLinksOf($name);
            if ($links === []) {
                break;
            }
            $this->getPage()->navigate($links[0])->waitForNavigation();
            $deleted++;
        }

        return $deleted;
    }

    private function goToNewCartRule(): void
    {
        // The toolbar link carries the token. Without it (a theme that moved
        // the toolbar), the list's own URL plus addcart_rule is the same page.
        $href = $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);if(e&&e.href)return e.href;'
            . 'return location.href.split("#")[0]+"&addcart_rule";})()',
            json_encode($this->getSelector('newCartRuleButton'))
        ))->getReturnValue();

        $this->getPage()->navigate((string) $href)->waitForNavigation();
        $this->waitForJsCondition(sprintf('!!document.querySelector(%s)', json_encode($this->getSelector('form'))), 10000);
    }

    /** @return list<string> the delete links of the listed rules whose name contains $name */
    private function pfDeleteLinksOf(string $name): array
    {
        try {
            $this->getPage()->waitUntilContainsElement($this->getSelector('listRows'), 5000);
        } catch (\Throwable) {
            return [];
        }

        $links = $this->getPage()->evaluate(sprintf(
            '(function(){var n=%s;return [].slice.call(document.querySelectorAll(%s)).filter(function(r){'
            . 'return r.textContent.indexOf(n)!==-1;}).map(function(r){var h=null;'
            . '[].slice.call(r.querySelectorAll("a")).forEach(function(a){if(h)return;'
            . 'var u=a.getAttribute("href")||"";if(u.indexOf("deletecart_rule")!==-1){h=u;return;}'
            . 'var m=(a.getAttribute("onclick")||"").match(/\'([^\']*deletecart_rule[^\']*)\'/);if(m){h=m[1];}});'
            . 'return h?new URL(h,location.href).href:null;}).filter(function(h){return !!h;});})()',
            json_encode($name),
            json_encode($this->getSelector('listRows'))
        ))->getReturnValue();

        return is_array($links) ? array_values(array_filter($links, 'is_string')) : [];
    }
}
