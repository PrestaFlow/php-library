<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Categories extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Categories');

        extract($this->pages);

        $this
        ->describe('Reach the Categories page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Categories via the menu', function () use ($backOfficeCategoriesPage) {
            $backOfficeCategoriesPage->goTo();

            Expect::that($backOfficeCategoriesPage->getPageTitle())->contains($backOfficeCategoriesPage->pageTitle());
        });
    }
}
