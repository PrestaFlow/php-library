<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Carriers extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Carriers');

        extract($this->pages);

        $this
        ->describe('Reach the Carriers page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Carriers via the menu', function () use ($backOfficeCarriersPage) {
            $backOfficeCarriersPage->goTo();

            Expect::that($backOfficeCarriersPage->getPageTitle())->contains($backOfficeCarriersPage->pageTitle());
        });
    }
}
