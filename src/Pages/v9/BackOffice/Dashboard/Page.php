<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Dashboard;

use PrestaFlow\Library\Pages\v9\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public function __construct(string $locale, string $patchVersion, array $globals)
    {
        //$this->pageTitle = 'Dashboard';
        $this->pageTitle = 'Tableau de bord';

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals);
    }
}
