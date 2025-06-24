<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\PricesDrop;

use PrestaFlow\Library\Pages\v8\FrontOffice\Listing\Page as ListingPage;

class Page extends ListingPage
{
    public function __construct(string $locale, string $patchVersion)
    {
        $this->url = 'promotions';
        $this->pageTitle = 'Promotions';

        parent::__construct($locale, $patchVersion);
    }
}
