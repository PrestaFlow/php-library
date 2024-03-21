<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\Listing;

use PrestaFlow\Library\Pages\v8\FrontOffice\BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'listTitle' => '#js-product-list-header',
        ];
    }

    public function defineMessages()
    {
        return [
            'addedToWishlist' => 'Product added',
        ];
    }

    public function getListingTitle()
    {
        return $this->getTextContent($this->selector('listTitle'));
    }

    public function addToWishList($index)
    {
        return '';
    }

    public function isAddedToWishlist($index)
    {
        return false;
    }
}
