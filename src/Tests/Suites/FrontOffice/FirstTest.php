<?php

namespace PrestaFlow\Library\Tests\Suites\FrontOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class FirstTest extends TestsSuite
{
    public function init()
    {
        $headless = true;
        $this->before($headless);

        $this->importPage('FrontOffice\Home');
        $this->importPage('FrontOffice\PricesDrop');

        extract($this->pages);

        $this
        ->describe('First test')
        ->it('should go to home page', function () use ($frontOfficeHomePage) {
            $frontOfficeHomePage->goToPage('home');
        })
        ->it('check that is not in maintenance', function () use ($frontOfficeHomePage) {
            $frontOfficeHomePage->setUserAgent('PrestaFlow-custom');
            Expect::that()->elementIsNotVisible($frontOfficeHomePage->selector('maintenanceBlock'), 1000);
        })
        ->it('seems not broken', function () use ($frontOfficeHomePage) {
            Expect::that()->elementIsVisible($frontOfficeHomePage->selector('desktopLogo'), 1000);
        })
        ->it('will fail, obviously', function () use ($frontOfficeHomePage) {
            Expect::that(true)->equals(true);
            Expect::that(false)->equals(true);
        })
        ->skip('skipped test', function () use ($frontOfficeHomePage) {
            Expect::that(false)->equals(true);
        })
        ->todo('TODO test', function () use ($frontOfficeHomePage) {
        })
        ->it('go to prices drop', function () use ($frontOfficeHomePage, $frontOfficePricesDropPage) {
            $frontOfficePricesDropPage->goToPage($frontOfficePricesDropPage, 1000); // eq: $homePage->goToPage('prices-drop')

            Expect::that($frontOfficePricesDropPage->getListingTitle())->contains($frontOfficePricesDropPage->pageTitle());
        });
    }
}
