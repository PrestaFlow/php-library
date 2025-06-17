<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\PricesDrop;

use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as ListingPage;

class Page extends ListingPage
{
    public function __construct()
    {
        $this->url = 'promotions';
        $this->pageTitle = 'Promotions';

        parent::__construct();
    }
}
