<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Customers;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Customers';
    public string $menuSelector = '#subtab-AdminCustomers';
    public string $parentMenuSelector = '#subtab-AdminParentCustomer';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newCustomerButton' => '#page-header-desc-configuration-add',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
