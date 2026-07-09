<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Orders;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Orders';
    public string $menuSelector = '#subtab-AdminOrders';
    public string $parentMenuSelector = '#subtab-AdminParentOrders';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newOrderButton' => '#page-header-desc-order-new_order',
            // Best-effort PS 9 orders grid — corrected live.
            'filterReferenceInput' => '#order_grid_table th input[name="order[reference]"]',
            'searchButton' => '#order_grid_search_form button.grid-search-button',
            'listRowReference' => '#order_grid_table tbody tr:nth-child(${row}) .column-reference',
            // The row's order-view link (scoped to /orders/ so it doesn't match
            // the customer link, which also ends in /view).
            'listRowLink' => '#order_grid_table tbody tr:nth-child(${row}) a[href*="/orders/"][href*="/view"]',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    public function filterByReference(string $reference): void
    {
        $this->setValue($this->getSelector('filterReferenceInput'), $reference);
        $this->click($this->getSelector('searchButton'));
        $this->waitForPageReload();
    }

    public function getOrderReferenceInList(int $row = 1): string
    {
        return trim($this->getTextContent($this->getSelector('listRowReference', ['row' => $row])));
    }

    public function openOrder(int $row = 1): void
    {
        $this->click($this->getSelector('listRowLink', ['row' => $row]));
        $this->waitForPageReload();
    }
}
