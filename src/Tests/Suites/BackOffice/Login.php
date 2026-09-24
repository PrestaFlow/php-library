<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Login extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Dashboard');
        $this->importPage('BackOffice\Login');

        extract($this->pages);

        $this
        ->describe('Check PS version {$PS_VERSION} with {$LOCALE} language, and login and log out from BO')
        ->it('should go to login page', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');

            // The email field, not the version: the login page of 8 and 9 does
            // not state a version anywhere, and the block this used to read
            // ("* PrestaShop", or the shop name) never did either. Asserting on
            // what is actually there is the point of the whole change.
            Expect::that($backOfficeLoginPage->elementIsVisible(
                $backOfficeLoginPage->getSelector('emailInput'),
                5000
            ))->equals(true);
        })
        ->it('should not claim a version the login page does not state', function () use ($backOfficeLoginPage) {
            // Meaningful precisely because it is the negative case: the getter
            // used to answer with a label here and pass an isNotEmpty() guard
            // while stating nothing about the version.
            Expect::that($backOfficeLoginPage->getPrestaShopVersion())->isNull();
        })
        ->it('should try to login with wrong email and password', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->login('wrongEmail@prestashop.com', 'wrongPass', false);

            // A login error is shown. Assert it appeared rather than matching a
            // translated string (the login page follows the shop's default locale).
            $errorMessage = $backOfficeLoginPage->getLoginError();
            Expect::that($errorMessage)->isNotEmpty();
        })
        ->it('should login into BO with default user', function () use ($backOfficeLoginPage, $backOfficeDashboardPage) {
            // Avoid this line : only there cause the DELETE raw key doesn't work as now
            $backOfficeLoginPage->goToPage('index');
            /*

            await loginPage.login(page, global.BO.EMAIL, global.BO.PASSWD);
            await dashboardPage.closeOnboardingModal(page);

            const pageTitle = await dashboardPage.getPageTitle(page);
            await expect(pageTitle).to.contains(dashboardPage.pageTitle);
            */
            $backOfficeLoginPage->login();

            Expect::that($backOfficeDashboardPage->getPageTitle())->contains($backOfficeDashboardPage->pageTitle());
        })
        ->it('should check PS version', function () use ($backOfficeLoginPage) {
            // Only reachable once authenticated: #shop_version is written by the
            // back-office header, which the login page has none of.
            $psVersion = $backOfficeLoginPage->getPrestaShopVersion();
            Expect::that($psVersion)->contains($backOfficeLoginPage->getGlobal('PS_VERSION'));
        })
        ->it('should log out from BO', function () use ($backOfficeLoginPage, $backOfficeDashboardPage) {
            /*
            await dashboardPage.logoutBO(page);

            const pageTitle = await loginPage.getPageTitle(page);
            await expect(pageTitle).to.contains(loginPage.pageTitle);
            */
            $backOfficeLoginPage->logout();

            // Two assertions because logging out has two halves, and the old
            // suite checked neither: the session is gone (no logout link left,
            // and the version the header used to state is gone with it), and
            // the login form is back.
            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(false);
            Expect::that($backOfficeLoginPage->getPrestaShopVersion())->isNull();
            Expect::that($backOfficeLoginPage->elementIsVisible(
                $backOfficeLoginPage->getSelector('emailInput'),
                5000
            ))->equals(true);
        });
    }
}
