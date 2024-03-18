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
            'LOCALE' => 'en',
            'FO' => [
                'URL' => 'https://8.0.4.test',
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
        $page = $this->page;

        $homePage = new \PrestaFlow\Library\Pages\v8\FrontOffice\Home\Page();
        $homePage->setPage($page);
        $homePage->setGlobals($globals);
        $homePage->setUserAgent('PrestaFlow');

        $loginPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Login\Page();
        $loginPage->setPage($page);
        $loginPage->setGlobals($globals);
        $loginPage->setUserAgent('PrestaFlow');

        $dashboardPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Dashboard\Page();
        $dashboardPage->setPage($page);
        $dashboardPage->setGlobals($globals);
        $dashboardPage->setUserAgent('PrestaFlow');

        $this->describe(
            'Wishlist module - Statistics tab settings',
            [
                $this->it('should login in BO', function () use (&$loginPage, &$dashboardPage) {
                    $loginPage->goToPage('login');

                    Expect::that($loginPage->getPageTitle())->with($loginPage->getPage())->contains($loginPage->pageTitle());

                    $loginPage->login();

                    Expect::that($dashboardPage->getPageTitle())->with($dashboardPage->getPage())->contains($dashboardPage->pageTitle());

                }),
                $this->skip('should go to \'Modules > Module Manager\' page', function () use (&$homePage) {
                }),
                $this->skip('should search the module ${Modules.blockwishlist.name}', function () use (&$homePage) {
                }),
                $this->skip('should go to the configuration page of the module ${Modules.blockwishlist.name}', function () use (&$homePage) {
                }),
                $this->skip('should go on Statistics Tab', function () use (&$homePage) {
                }),
                $this->skip('should go to the FO', function () use (&$homePage) {
                }),
                $this->skip('should go to login page', function () use (&$homePage) {
                }),
                $this->skip('should sign in with default customer', function () use (&$homePage) {
                }),
                $this->skip('should go to all products page', function () use (&$homePage) {
                }),
                $this->skip('should add product #${idxProduct} to wishlist', function () use (&$homePage) {
                }),
                $this->skip('should add product #${idxProduct} to wishlist', function () use (&$homePage) {
                }),
                $this->skip('should add product #${idxProduct} to wishlist', function () use (&$homePage) {
                }),
                $this->skip('should logout', function () use (&$homePage) {
                }),
                $this->skip('should go to BO', function () use (&$homePage) {
                }),
                $this->skip('should click on the refresh button', function () use (&$homePage) {
                }),
            ]
        );
    }
}
