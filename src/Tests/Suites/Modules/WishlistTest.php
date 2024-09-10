<?php

namespace PrestaFlow\Library\Tests\Suites\Modules;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class WishlistTest extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Dashboard');
        $this->importPage('BackOffice\Login');
        $this->importPage('FrontOffice');
        $this->importPage('FrontOffice\Home');
        $this->importPage('FrontOffice\Login');
        $this->importPage('FrontOffice\Listing');

        extract($this->pages);

        $this
        ->describe('Wishlist module - Statistics tab settings')
        ->it('should login into BO', function () use ($backOfficeLoginPage, $backOfficeDashboardPage) {
            $backOfficeLoginPage->goToPage('index');

            $backOfficeLoginPage->login();

            Expect::that($backOfficeDashboardPage->getPageTitle())->contains($backOfficeDashboardPage->pageTitle());
        })
        ->todo('should go to \'Modules > Module Manager\' page', function () use ($frontOfficeHomePage) {
        })
        ->todo('should search the module ${Modules.blockwishlist.name}', function () use ($frontOfficeHomePage) {
        })
        ->todo('should go to the configuration page of the module ${Modules.blockwishlist.name}', function () use ($frontOfficeHomePage) {
        })
        ->todo('should go on Statistics Tab', function () use ($frontOfficeHomePage) {
        })
        ->it('should go to the FO', function () use ($frontOfficeHomePage) {
            $frontOfficeHomePage->goToPage('home');

            Expect::that()->elementIsVisible($frontOfficeHomePage->selector('homePageSection'), 1000);
        })
        ->it('should go to login page', function () use ($frontOfficeLoginPage) {
            $frontOfficeLoginPage->goToPage('login');

            Expect::that($frontOfficeLoginPage->getPageTitle())->contains($frontOfficeLoginPage->pageTitle());
        })
        ->it('should sign in with default customer', function () use ($frontOfficeLoginPage) {
            $frontOfficeLoginPage->login();

            var_dump($frontOfficeLoginPage->selector('logoutLink'));

            Expect::that()->customerIsLogged($frontOfficeLoginPage->selector('logoutLink'), 1000);
        })
        ->it('should go to all products page', function () use ($frontOfficeHomePage, $frontOfficeListingPage) {
            $frontOfficeHomePage->goToAllProducts();

            Expect::that($frontOfficeListingPage->getListingTitle())->contains('Accueil');
        });

        for ($i = 1; $i <= 3; $i++) {
            $this->skip("should add product #{$i} to wishlist", function () use ($frontOfficeListingPage, $i) {
                $textResult = $frontOfficeListingPage->addToWishList($i);
                Expect::that($textResult)->equals($frontOfficeListingPage->message('addedToWishlist'));

                $isAddedToWishlist = $frontOfficeListingPage->isAddedToWishlist($i);
                Expect::that($isAddedToWishlist)->equals(true);
            });
        };

        $this->it('should logout', function () use ($frontOfficeLoginPage) {
            $frontOfficeLoginPage->logout();

            Expect::that()->customerIsNotLogged($frontOfficeLoginPage->selector('logoutLink'), 1000);
        })
        ->todo('should go to BO', function () use ($frontOfficeHomePage) {
        })
        ->todo('should click on the refresh button', function () use ($frontOfficeHomePage) {
        });
    }
}
