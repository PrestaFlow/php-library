<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Customers extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Customers');

        extract($this->pages);

        $this
        ->describe('Reach the Customers page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Customers via the menu', function () use ($backOfficeCustomersPage) {
            $backOfficeCustomersPage->goTo();

            Expect::that($backOfficeCustomersPage->getPageTitle())->contains($backOfficeCustomersPage->pageTitle());
        });
    }
}
