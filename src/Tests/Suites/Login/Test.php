<?php

namespace PrestaFlow\Library\Tests\Suites\Login;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Test extends TestsSuite
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
            Expect::that($backOfficeLoginPage->getPageTitle())->contains($backOfficeLoginPage->pageTitle());
        })
        ->it('should check PS version', function () use ($backOfficeLoginPage) {
            $psVersion = $backOfficeLoginPage->getPrestaShopVersion();
            Expect::that($psVersion)->contains($backOfficeLoginPage->getGlobal('PS_VERSION'));

            //Expect::that()->matchPrestaShopVersion();
        })
        ->it('should try to login with wrong email and password', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->login('wrongEmail@prestashop.com', 'wrongPass', false);

            // Get error displayed
            $errorMessage = $backOfficeLoginPage->getLoginError();
            Expect::that($errorMessage)->contains($backOfficeLoginPage->getMessage('loginErrorText'));
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
        ->it('should log out from BO', function () use ($backOfficeLoginPage, $backOfficeDashboardPage) {
            /*
            await dashboardPage.logoutBO(page);

            const pageTitle = await loginPage.getPageTitle(page);
            await expect(pageTitle).to.contains(loginPage.pageTitle);
            */
            $backOfficeLoginPage->logout();

            Expect::that($backOfficeLoginPage->getPageTitle())->contains($backOfficeLoginPage->pageTitle());
        });
    }
}
