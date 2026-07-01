<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Orders extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Orders');

        extract($this->pages);

        $this
        ->describe('Reach the Orders page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Orders via the menu', function () use ($backOfficeOrdersPage) {
            $backOfficeOrdersPage->goTo();

            Expect::that($backOfficeOrdersPage->getPageTitle())->contains($backOfficeOrdersPage->pageTitle());
        });
    }
}
