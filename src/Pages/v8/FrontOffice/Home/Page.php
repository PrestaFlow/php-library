<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\Home;

use PrestaFlow\Library\Pages\v8\FrontOffice\BasePage;

class Page extends BasePage
{
    public function __construct()
    {
        $selectors = [
            'maintenanceBlock' => '#content.page-maintenance',
            'desktopLogo' => '#_desktop_logo',
        ];

        $this->selectors = $selectors;
    }
}
