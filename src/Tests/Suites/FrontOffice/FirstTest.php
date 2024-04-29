<?php

namespace PrestaFlow\Library\Tests\Suites\FrontOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class FirstTest extends TestsSuite
{
    public function __construct()
    {
        $globals = [
            'PS_VERSION' => '8.0.4',
            'LOCALE' => 'en',
            'FO' => [
                'URL' => 'https://8.0.4.test',
            ],
        ];

        $headless = true;
        $this->before($headless);

        $homePage = new \PrestaFlow\Library\Pages\v8\FrontOffice\Home\Page();
        $homePage->setGlobals($globals);
        $homePage->setUserAgent('PrestaFlow');

        $pricesDropPage = new \PrestaFlow\Library\Pages\v8\FrontOffice\PricesDrop\Page();
        $pricesDropPage->setGlobals($globals);


        $this
        ->describe('First test')
        ->it('should go to home page', function () use ($homePage) {
            $homePage->goToPage('home');
        })
        ->it('check that is not in maintenance', function () use ($homePage) {
            $homePage->setUserAgent('PrestaFlow-custom');
            Expect::that()->elementIsNotVisible($homePage->selector('maintenanceBlock'), 1000);
        })
        ->it('seems not broken', function () use ($homePage) {
            Expect::that()->elementIsVisible($homePage->selector('desktopLogo'), 1000);
        })
        ->it('will fail, obviously', function () use ($homePage) {
            Expect::that(true)->equals(true);
            Expect::that(false)->equals(true);
        })
        ->skip('skipped test', function () use ($homePage) {
            Expect::that(false)->equals(true);
        })
        ->todo('TODO test', function () use ($homePage) {
        })
        ->it('go to prices drop', function () use ($homePage, $pricesDropPage) {
            $homePage->goToPage($pricesDropPage, 1000); // eq: $homePage->goToPage('prices-drop')

            Expect::that($pricesDropPage->getListingTitle())->contains($pricesDropPage->pageTitle());
        });
    }
}
