<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Modules;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    // The admin page title is "Module manager"; 'Module' is a locale-tolerant substring.
    public string $pageTitle = 'Module';
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
