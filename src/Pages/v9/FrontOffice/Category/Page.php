<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Category;

use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as BasePage;

class Page extends BasePage
{
    public function __construct(string $locale, string $patchVersion, array $globals)
    {
        $this->url = '{index}-category';

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals);
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
