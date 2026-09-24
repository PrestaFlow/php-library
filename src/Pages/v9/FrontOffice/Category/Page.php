<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Category;

use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as BasePage;

class Page extends BasePage
{
    public string $url = '{index}-category';

    // No selector overrides: Category used to re-declare `pageTitle` as
    // `#js-product-list-header h1` because Listing pointed at the surrounding
    // container. Listing now declares the heading itself, so repeating it here
    // would only be a second copy to keep in sync.
}
