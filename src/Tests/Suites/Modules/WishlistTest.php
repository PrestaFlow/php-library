<?php

namespace PrestaFlow\Library\Tests\Suites\Modules;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class WishlistTest extends TestsSuite
{
    public function init()
    {
        // TEMP
        $globals = [
            'PS_VERSION' => '8.0.4',
            'LOCALE' => 'fr',
            'FO' => [
                'URL' => 'https://8.0.4.test/fr/',
            ],
            'BO' => [
                'URL' => 'https://8.0.4.test/admin-dev',
            ],
        ];
        // END

        $headless = false;
        $this->before($headless);

        $basePage = new \PrestaFlow\Library\Pages\v8\FrontOffice\BasePage();
        $basePage->setGlobals($globals);

        $listingPage = new \PrestaFlow\Library\Pages\v8\FrontOffice\Listing\Page();
        $listingPage->setGlobals($globals);

        $homePage = new \PrestaFlow\Library\Pages\v8\FrontOffice\Home\Page();
        $homePage->setGlobals($globals);
        //$homePage->setUserAgent('PrestaFlow');

        $frontOfficeLoginPage = new \PrestaFlow\Library\Pages\v8\FrontOffice\Login\Page();
        $frontOfficeLoginPage->setGlobals($globals);

        $loginPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Login\Page();
        $loginPage->setGlobals($globals);

        $dashboardPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Dashboard\Page();
        $dashboardPage->setGlobals($globals);

        $this
        ->describe('Wishlist module - Statistics tab settings')
        ->it('should login in BO', function () use ($loginPage, $dashboardPage) {
            $loginPage->goToPage('login');

            Expect::that($loginPage->getPageTitle())->contains($loginPage->pageTitle());

            $loginPage->login();

            Expect::that($loginPage->getPageTitle())->contains($loginPage->pageTitle());
        })
        ->skip('should go to \'Modules > Module Manager\' page', function () use ($homePage) {
        })
        ->skip('should search the module ${Modules.blockwishlist.name}', function () use ($homePage) {
        })
        ->skip('should go to the configuration page of the module ${Modules.blockwishlist.name}', function () use ($homePage) {
        })
        ->skip('should go on Statistics Tab', function () use ($homePage) {
        })
        ->it('should go to the FO', function () use ($homePage) {
            $homePage->goToPage('home');

            Expect::that()->elementIsVisible($homePage->selector('homePageSection'), 1000);
        })
        ->it('should go to login page', function () use ($frontOfficeLoginPage) {
            $frontOfficeLoginPage->goToPage('login');

            Expect::that($frontOfficeLoginPage->getPageTitle())->contains($frontOfficeLoginPage->pageTitle());
        })
        ->it('should sign in with default customer', function () use ($frontOfficeLoginPage) {
            $frontOfficeLoginPage->login('pub@prestashop.com', '123456789');

            Expect::that()->customerIsLogged($frontOfficeLoginPage->selector('logoutLink'), 1000);
        })
        ->it('should go to all products page', function () use ($homePage, $listingPage) {
            $homePage->goToAllProducts();

            Expect::that($listingPage->getListingTitle())->contains('Accueil');
        });

        for ($i = 1; $i <= 3; $i++) {
            $this->skip("should add product #{$i} to wishlist", function () use ($listingPage, $i) {
                $textResult = $listingPage->addToWishList($i);
                Expect::that($textResult)->equals($listingPage->message('addedToWishlist'));

                $isAddedToWishlist = $listingPage->isAddedToWishlist($i);
                Expect::that($isAddedToWishlist)->equals(true);
            });
        };

        $this->it('should logout', function () use ($basePage) {
            $basePage->logout();

            Expect::that()->customerIsNotLogged($basePage->selector('logoutLink'), 1000);
        })
        ->skip('should go to BO', function () use ($homePage) {
        })
        ->skip('should click on the refresh button', function () use ($homePage) {
        });
    }
}
