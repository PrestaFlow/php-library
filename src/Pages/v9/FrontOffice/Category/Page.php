<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Category;

use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as BasePage;

class Page extends BasePage
{
    public function __construct(string $locale, string $patchVersion)
    {
        $this->url = '{index}-category';

        parent::__construct($locale, $patchVersion);
    }

    public function defineSelectors()
    {
        $selectors = parent::defineSelectors();

        $pageSelectors = [
            'pageTitle' => '#js-product-list-header h1',
        ];

        return [...$selectors, ...$pageSelectors];
    }
}
