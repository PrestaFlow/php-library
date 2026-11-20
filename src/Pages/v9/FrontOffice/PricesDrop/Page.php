<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\PricesDrop;

use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Promotions';
    // English friendly URL; the shop's own one (prestashop.urls.pages) wins.
    public string $url = 'prices-drop';
    public $shopPage = 'prices-drop';
}
