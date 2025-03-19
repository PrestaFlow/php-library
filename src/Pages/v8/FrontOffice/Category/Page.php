<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\Category;

use PrestaFlow\Library\Pages\v8\FrontOffice\Listing\Page as BasePage;

class Page extends BasePage
{
    public function __construct()
    {
        $this->url = '{index}-category';

        parent::__construct();
    }
}
