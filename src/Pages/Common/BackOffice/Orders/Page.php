<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Orders;

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
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
