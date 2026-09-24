<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Categories;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Categories';
    public string $menuSelector = '#subtab-AdminCategories';
    public string $parentMenuSelector = '#subtab-AdminCatalog';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newCategoryButton' => '#page-header-desc-configuration-add',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
