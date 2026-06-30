<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Dashboard;

use PrestaFlow\Library\Pages\Common\BackOffice\Dashboard\Page as BasePage;

class Page extends BasePage
{
    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->pageTitle = 'Tableau de bord';

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals, customs: $customs);
    }
}
