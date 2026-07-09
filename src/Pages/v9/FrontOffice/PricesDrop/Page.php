<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\PricesDrop;

use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as BasePage;

class Page extends BasePage
{
    // PS9: page renamed from "Prices drop" to "Promotions"; URL slug changed accordingly.
    public string $pageTitle = 'Promotions';
    public string $url = 'promotions';
}
