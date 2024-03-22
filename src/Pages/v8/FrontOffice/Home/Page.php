<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\Home;

use PrestaFlow\Library\Pages\v8\FrontOffice\BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'homePageSection' => '#content.page-home',
            'maintenanceBlock' => '#content.page-maintenance',
            'desktopLogo' => '#_desktop_logo',
            'allProductsLink' => '#content section.featured-products:nth-of-type(1) .all-product-link',
        ];
    }

    public function goToAllProducts()
    {
        $this->goToPage('home');

        $elem = $this->getPage()->dom()->querySelector($this->selector('allProductsLink'));
        $url = $elem->getAttribute('href');
        $this->getPage()->navigate($url)->waitForNavigation();
    }
}
