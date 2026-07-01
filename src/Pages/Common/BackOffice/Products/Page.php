<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Products;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Products';
    public string $menuSelector = '#subtab-AdminProducts';
    public string $parentMenuSelector = '#subtab-AdminCatalog';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newProductButton' => '#page-header-desc-configuration-add',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
