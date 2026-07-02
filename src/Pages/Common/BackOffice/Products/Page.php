<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Products;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Products';
    public string $menuSelector = '#subtab-AdminProducts';
    public string $parentMenuSelector = '#subtab-AdminCatalog';

    /**
     * PrestaShop 9 admin selectors. The product-create/edit flow and its
     * selectors are validated live (2026-07-02) against PS 9.0.0-rc.1. The list
     * grid selectors are validated via the ProductsCrud suite. v7/v8
     * defineSelectors() overrides remain deferred.
     */
    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newProductButton' => '#page-header-desc-configuration-add',
            'createProductButton' => '#create_product_create',
            'filterNameInput' => '#product_grid_table th input[name="product[name]"]',
            'searchButton' => '#product_grid_search_form button.grid-search-button',
            'resetButton' => '#product_grid_search_form .grid-reset-button',
            'listRow' => '#product_grid_table tbody tr:nth-child(${row})',
            'listRowName' => '#product_grid_table tbody tr:nth-child(${row}) .column-name',
            'resultCount' => '.pagination-total, #product_grid_panel .card-header .badge',
            'rowActionsToggle' => '#product_grid_table tbody tr:nth-child(${row}) .dropdown-toggle',
            'rowDeleteLink' => '#product_grid_table tbody tr:nth-child(${row}) a.grid-delete-row-link',
            'deleteConfirmButton' => '.modal.show .btn-confirm-submit',
            'formNameInput' => '#product_header_name_1',
            'formPriceInput' => '#product_pricing_retail_price_price_tax_excluded',
            'formQuantityInput' => '#product_stock_quantities_delta_quantity_delta',
            'formSaveButton' => '#product_footer_save',
            'productOnlineToggle' => '#product_header_active_1',
            // The visible flash toast carries role="alert"; a hidden empty
            // `.alert-success` template precedes it in the DOM, so scope by role.
            'successAlert' => '.alert-success[role="alert"]',
            'productPreviewLink' => '#product_footer_actions_preview',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    public function filterByName(string $name): void
    {
        $this->setValue($this->getSelector('filterNameInput'), $name);
        $this->click($this->getSelector('searchButton'));
        $this->waitForPageReload();
    }

    public function resetFilter(): void
    {
        $this->click($this->getSelector('resetButton'));
        $this->waitForPageReload();
    }

    public function getListCount(): int
    {
        return (int) preg_replace('/\D+/', '', $this->getTextContent($this->getSelector('resultCount')));
    }

    public function getProductNameInList(int $row = 1): string
    {
        return trim($this->getTextContent($this->getSelector('listRowName', ['row' => $row])));
    }

    public function goToNewProduct(): void
    {
        // The list's "Add new product" link is intercepted by JS to open a
        // type-choice modal. Read its real href and navigate directly to the
        // full-page create flow instead (bypasses the modal), mirroring how
        // goToSubMenu resolves menu links.
        try {
            $this->getPage()->waitUntilContainsElement($this->getSelector('newProductButton'), 10000);
        } catch (\Throwable $e) {
            // fall through; the evaluate below reports a null href if absent
        }
        $sel = json_encode($this->getSelector('newProductButton'));
        $href = $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return e&&e.href?e.href:null;})()',
            $sel
        ))->getReturnValue();
        if (is_string($href) && $href !== '') {
            $this->getPage()->navigate($href)->waitForNavigation();
        }
        $this->waitForPageReload();
        // "Standard product" is pre-selected. Clicking "Add new product" submits
        // the create form and redirects to /products/{id}/edit, rendering the
        // full editable product form.
        $this->click($this->getSelector('createProductButton'));
        $this->waitForPageReload();
    }

    public function createProduct(string $name, float $price = 0, int $quantity = 0): void
    {
        $this->goToNewProduct();
        $this->setValue($this->getSelector('formNameInput'), $name);
        $this->setValue($this->getSelector('formPriceInput'), (string) $price);
        $this->setValue($this->getSelector('formQuantityInput'), (string) $quantity);
        // Enable the product BEFORE saving so the online state persists.
        $this->enableProduct();
        $this->click($this->getSelector('formSaveButton'));
        $this->waitForPageReload();
    }

    public function deleteProduct(int $row = 1): void
    {
        $this->click($this->getSelector('rowActionsToggle', ['row' => $row]));
        $this->click($this->getSelector('rowDeleteLink', ['row' => $row]));
        // The confirm modal fades in; click() does not wait, so wait for the
        // confirm button (scoped to the shown modal) before clicking it.
        try {
            $this->getPage()->waitUntilContainsElement($this->getSelector('deleteConfirmButton'), 10000);
        } catch (\Throwable $e) {
        }
        $this->click($this->getSelector('deleteConfirmButton'));
        $this->waitForPageReload();
    }

    public function getSuccessMessage(): string
    {
        return trim($this->getTextContent($this->getSelector('successAlert')));
    }

    public function enableProduct(): void
    {
        $this->click($this->getSelector('productOnlineToggle'));
    }

    public function getCreatedProductId(): int
    {
        if (preg_match('#/products/(\d+)#', $this->getPage()->getCurrentUrl(), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * The product edit page's "Preview" action carries the canonical FrontOffice
     * URL (correct id, link_rewrite and language prefix). Reading it avoids
     * guessing the friendly URL from the product name.
     */
    public function getCreatedProductUrl(): string
    {
        $sel = json_encode($this->getSelector('productPreviewLink'));

        return (string) $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return e&&e.href?e.href:"";})()',
            $sel
        ))->getReturnValue();
    }
}
