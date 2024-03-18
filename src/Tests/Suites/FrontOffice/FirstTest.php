<?php

namespace PrestaFlow\Library\Tests\Suites\FrontOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class FirstTest extends TestsSuite
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
                'EMAIL' => '',
                'PASSWD' => '',
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

        $pricesDropPage = new \PrestaFlow\Library\Pages\v8\FrontOffice\PricesDrop\Page();
        $pricesDropPage->setPage($page);
        $pricesDropPage->setGlobals($globals);
        $pricesDropPage->setUserAgent('PrestaFlow');

        $this->describe(
            'Test',
            [
                $this->it('should go to home page', function () use (&$homePage) {
                    $homePage->goTo($globals['FO']['URL']);
                }),
                $this->it('check that is not in maintenance', function () use (&$homePage) {
                    $homePage->setUserAgent('PrestaFlow');
                    $maintenanceMode = $homePage->elementIsVisible($homePage->selector('maintenanceBlock'), 1000);

                    Expect::that($maintenanceMode)->with($homePage->getPage())->notVisible($homePage->selector('maintenanceBlock'));
                }),
                $this->it('seems not broken', function () use (&$homePage) {
                    $logo = $homePage->elementIsVisible($homePage->selector('desktopLogo'), 1000);

                    Expect::that($logo)->with($homePage->getPage())->visible($homePage->selector('desktopLogo'));
                }),
                $this->it('go to prices drop', function () use (&$homePage, &$pricesDropPage) {
                    $homePage->goToPage($pricesDropPage, 1000);

                    Expect::that($pricesDropPage->getListingTitle())->with($homePage->getPage())->contains($pricesDropPage->pageTitle());
                }),
            ]
        );
    }
}
