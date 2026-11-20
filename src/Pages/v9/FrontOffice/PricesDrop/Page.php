<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\PricesDrop;

use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Promotions';
    // Kept as it always was: the app writes a project's URL overrides under
    // this alias. The shop's own URL (prestashop.urls.pages) is used first.
    public string $url = 'promotions';
    public $shopPage = 'prices-drop';
}
