<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice\Category;

use PrestaFlow\Library\Pages\Common\FrontOffice\Listing\Page as BasePage;

class Page extends BasePage
{
    public string $url = '{index}-category';

    public function defineSelectors()
    {
        $selectors = parent::defineSelectors();

        $pageSelectors = [
            'pageTitle' => '#js-product-list-header h1',
        ];

        return [...$selectors, ...$pageSelectors];
    }
}
