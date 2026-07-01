<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Products extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Products');

        extract($this->pages);

        $this
        ->describe('Reach the Products page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Products via the menu', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();

            Expect::that($backOfficeProductsPage->getPageTitle())->contains($backOfficeProductsPage->pageTitle());
        });
    }
}
