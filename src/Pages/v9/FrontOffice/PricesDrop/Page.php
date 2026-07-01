<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\PricesDrop;

use PrestaFlow\Library\Pages\Common\FrontOffice\PricesDrop\Page as BasePage;

class Page extends BasePage
{
    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->url = 'promotions';
        $this->pageTitle = 'Promotions';

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals, customs: $customs);
    }
}
