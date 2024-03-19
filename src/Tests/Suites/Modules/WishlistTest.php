<?php

namespace PrestaFlow\Library\Tests\Suites\Modules;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class WishlistTest extends TestsSuite
{
    public function __construct()
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
                'EMAIL' => 'j.danse@prestaedit.com',
                'PASSWD' => 'w9Djrekg#',
            ],
        ];
        // END

        $headless = true;
        $this->before($headless);

        $homePage = new \PrestaFlow\Library\Pages\v8\FrontOffice\Home\Page();
        $homePage->setGlobals($globals);
        //$homePage->setUserAgent('PrestaFlow');

        $frontOfficeLoginPage = new \PrestaFlow\Library\Pages\v8\FrontOffice\Login\Page();
        $frontOfficeLoginPage->setGlobals($globals);

        $loginPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Login\Page();
        $loginPage->setGlobals($globals);

        $dashboardPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Dashboard\Page();
        $dashboardPage->setGlobals($globals);

        $this->describe(
            'Wishlist module - Statistics tab settings',
            [
                $this->it('should login in BO', function () use ($loginPage, $dashboardPage) {
                    $loginPage->goToPage('login');

                    Expect::that($loginPage->getPageTitle())->contains($loginPage->pageTitle());

                    $loginPage->login();

                    Expect::that($loginPage->getPageTitle())->contains($loginPage->pageTitle());
                }),
                $this->skip('should go to \'Modules > Module Manager\' page', function () use ($homePage) {
                }),
                $this->skip('should search the module ${Modules.blockwishlist.name}', function () use ($homePage) {
                }),
                $this->skip('should go to the configuration page of the module ${Modules.blockwishlist.name}', function () use ($homePage) {
                }),
                $this->skip('should go on Statistics Tab', function () use ($homePage) {
                }),
                $this->it('should go to the FO', function () use ($homePage) {
                    $homePage->goToPage('home');

                    Expect::that()->elementIsVisible($homePage->selector('homePageSection'), 1000);
                }),
                $this->it('should go to login page', function () use ($frontOfficeLoginPage) {
                    $frontOfficeLoginPage->goToPage('login');

                    Expect::that($frontOfficeLoginPage->getPageTitle())->contains($frontOfficeLoginPage->pageTitle());
                }),
                $this->it('should sign in with default customer', function () use ($frontOfficeLoginPage) {
                    $frontOfficeLoginPage->login('pub@prestashop.com', '123456789');

                    // Expect that customer is connected
                    Expect::that()->elementIsVisible($frontOfficeLoginPage->selector('logoutLink'), 1000);
                }),
                $this->skip('should go to all products page', function () use ($homePage) {
                }),
                $this->skip('should add product #${idxProduct} to wishlist', function () use ($homePage) {
                }),
                $this->skip('should add product #${idxProduct} to wishlist', function () use ($homePage) {
                }),
                $this->skip('should add product #${idxProduct} to wishlist', function () use ($homePage) {
                }),
                $this->skip('should logout', function () use ($homePage) {
                }),
                $this->skip('should go to BO', function () use ($homePage) {
                }),
                $this->skip('should click on the refresh button', function () use ($homePage) {
                }),
            ]
        );
    }
}
