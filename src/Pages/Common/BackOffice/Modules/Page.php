<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Modules;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Modules';
    public string $menuSelector = '#subtab-AdminModulesSf';
    public string $parentMenuSelector = '#subtab-AdminParentModulesSf';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
