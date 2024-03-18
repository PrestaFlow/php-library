<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\ListingPage;

use PrestaFlow\Library\Pages\v8\FrontOffice\BasePage;

class Page extends BasePage
{
    public function __construct()
    {
        $this->selectors = [
            'listTitle' => '#js-product-list-header',
        ];
    }

    public function getListingTitle()
    {
        return $this->getTextContent($this->selector('listTitle'));
    }
}
