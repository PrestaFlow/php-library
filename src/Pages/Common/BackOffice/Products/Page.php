<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Products;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Products';
    public string $menuSelector = '#subtab-AdminProducts';
    public string $parentMenuSelector = '#subtab-AdminCatalog';

    /**
     * @unverified — best-effort PrestaShop 9 admin selectors. The list grid and
     * product-form selectors have NOT been validated against a live shop and
     * differ on 1.7/8 (v7/v8 defineSelectors() overrides are deferred).
     */
    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newProductButton' => '#page-header-desc-configuration-add',
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
            'formPriceInput' => '#product_pricing_price_tax_excluded',
            'formQuantityInput' => '#product_stock_quantities_delta_quantity',
            'formSaveButton' => '#product_footer_save',
            'successAlert' => '.alert-success, .growl-success',
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
        $this->click($this->getSelector('newProductButton'));
        $this->waitForPageReload();
    }

    public function createProduct(string $name, float $price = 0, int $quantity = 0): void
    {
        $this->goToNewProduct();
        $this->setValue($this->getSelector('formNameInput'), $name);
        $this->setValue($this->getSelector('formPriceInput'), (string) $price);
        $this->setValue($this->getSelector('formQuantityInput'), (string) $quantity);
        $this->click($this->getSelector('formSaveButton'));
        $this->waitForPageReload();
    }

    public function deleteProduct(int $row = 1): void
    {
        $this->click($this->getSelector('rowActionsToggle', ['row' => $row]));
        $this->click($this->getSelector('rowDeleteLink', ['row' => $row]));
        $this->click($this->getSelector('deleteConfirmButton'));
        $this->waitForPageReload();
    }

    public function getSuccessMessage(): string
    {
        return trim($this->getTextContent($this->getSelector('successAlert')));
    }
}
