<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Modules extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Modules');

        extract($this->pages);

        $this
        ->describe('Reach the Modules page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Modules via the menu', function () use ($backOfficeModulesPage) {
            $backOfficeModulesPage->goTo();

            Expect::that($backOfficeModulesPage->getPageTitle())->contains($backOfficeModulesPage->pageTitle());
        });
    }
}
