<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Carriers;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Carriers';
    public string $menuSelector = '#subtab-AdminCarriers';
    public string $parentMenuSelector = '#subtab-AdminParentShipping';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newCarrierButton' => '#page-header-desc-configuration-add',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
