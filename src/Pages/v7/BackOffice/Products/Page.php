<?php

namespace PrestaFlow\Library\Pages\v7\BackOffice\Products;

use PrestaFlow\Library\Pages\v9\BackOffice\Products\Page as V9Page;

/**
 * PrestaShop 1.7 keeps the legacy catalog (table.product, filter_column_name)
 * and the legacy product page (form_step1_*, saved over AJAX). Validated live
 * against 1.7.8.11.
 */
class Page extends V9Page
{
    public function defineSelectors()
    {
        return array_merge(parent::defineSelectors(), [
            'filterNameInput' => 'input[name="filter_column_name"]',
            'searchButton' => 'button[name="products_filter_submit"]',
            'resetButton' => 'button[name="products_filter_reset"]',
            'listRows' => 'table.product tbody tr[data-product-id]',
            'listRow' => 'table.product tbody tr[data-product-id]:nth-child(${row})',
            'listRowName' => 'table.product tbody tr[data-product-id]:nth-child(${row}) td:nth-child(4)',
            'listRowLink' => 'table.product tbody tr[data-product-id]:nth-child(${row}) td:nth-child(4) a',
            'rowActionsToggle' => 'table.product tbody tr[data-product-id]:nth-child(${row}) .dropdown-toggle',
            'rowDeleteLink' => 'table.product tbody tr[data-product-id]:nth-child(${row}) a[onclick*="\'delete\'"]',
            'deleteConfirmButton' => '#catalog_deletion_modal button[value="confirm"]',
            'formNameInput' => 'input[id^="form_step1_name_"]',
            'formNameInputFallback' => '#form_step1_name_1',
            'formPriceInput' => '#form_step1_price_shortcut',
            'formQuantityInput' => '#form_step1_qty_0_shortcut',
            'formSaveButton' => '#submit',
            'productOnlineToggle' => '#form_step1_active',
            'formProductId' => '#form_id_product',
            'successAlert' => '#growls .growl-notice, .alert-success',
            'productPreviewLink' => '#product_form_preview_btn',
        ]);
    }

    /** The legacy catalog has no result counter: count the product rows. */
    public function getListCount(): int
    {
        return (int) $this->getPage()->evaluate(sprintf(
            'document.querySelectorAll(%s).length',
            json_encode($this->getSelector('listRows'))
        ))->getReturnValue();
    }

    /** "Add new product" opens the form straight away: there is no type modal. */
    public function goToNewProduct(): void
    {
        try {
            $this->getPage()->waitUntilContainsElement($this->getSelector('newProductButton'), 10000);
        } catch (\Throwable $e) {
        }
        $this->click($this->getSelector('newProductButton'));
        $this->waitForPageReload();
        $this->waitForJsCondition(sprintf(
            '!!document.querySelector(%s)',
            json_encode($this->getSelector('formPriceInput'))
        ), 15000);
    }

    public function createProduct(string $name, float $price = 0, int $quantity = 0): void
    {
        $this->goToNewProduct();
        $this->fillProductName($name);
        $this->setValueByJs($this->getSelector('formPriceInput'), (string) $price);
        $this->setValueByJs($this->getSelector('formQuantityInput'), (string) $quantity);
        $this->enableProduct();
        $this->saveProductForm();
    }

    /** The switch hides its checkbox: tick it by JS, once, so it stays on. */
    public function enableProduct(): void
    {
        $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);if(e&&!e.checked){e.click();}})()',
            json_encode($this->getSelector('productOnlineToggle'))
        ));
    }

    public function updatePrice(float $price): void
    {
        $this->setValueByJs($this->getSelector('formPriceInput'), (string) $price);
        $this->saveProductForm();
    }

    /**
     * The legacy form is saved over AJAX and the page stays where it is: wait
     * for the save button, disabled while the request runs, to come back.
     */
    private function saveProductForm(): void
    {
        $this->click($this->getSelector('formSaveButton'));
        $button = json_encode($this->getSelector('formSaveButton'));
        $this->waitForJsCondition(sprintf(
            '(function(){var b=document.querySelector(%s);return !!b&&b.hasAttribute("disabled");})()',
            $button
        ), 2000, 100);
        $this->waitForJsCondition(sprintf(
            '(function(){var b=document.querySelector(%s);return !!b&&!b.hasAttribute("disabled");})()',
            $button
        ), 20000);
    }

    public function getCreatedProductId(): int
    {
        $id = (int) $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return e?e.value:0;})()',
            json_encode($this->getSelector('formProductId'))
        ))->getReturnValue();

        return $id > 0 ? $id : parent::getCreatedProductId();
    }

    /**
     * The legacy "Preview" button has no href until it is clicked. The front
     * office redirects index.php?controller=product to the product's canonical
     * URL, so the id is enough.
     */
    public function getCreatedProductUrl(): string
    {
        $id = $this->getCreatedProductId();

        return $id > 0 ? 'index.php?controller=product&id_product=' . $id : '';
    }
}
