<?php

namespace PrestaFlow\Library\Tests\Suites\Login;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class LoginTest extends TestsSuite
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
        })
        ->it('should try to login with wrong email and password', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->login('wrongEmail@prestashop.com', 'wrongPass', false);

            // Get error displayed
            $errorMessage = $backOfficeLoginPage->getLoginError();
            Expect::that($errorMessage)->contains($backOfficeLoginPage->getMessage('loginErrorText'));
        })
        ->skip('should login into BO with default user', function () use ($backOfficeLoginPage, $backOfficeDashboardPage) {
            /*

            await loginPage.login(page, global.BO.EMAIL, global.BO.PASSWD);
            await dashboardPage.closeOnboardingModal(page);

            const pageTitle = await dashboardPage.getPageTitle(page);
            await expect(pageTitle).to.contains(dashboardPage.pageTitle);
            */
        })
        ->skip('should log out from BO', function () use ($backOfficeLoginPage, $backOfficeDashboardPage) {
            /*
            await dashboardPage.logoutBO(page);

            const pageTitle = await loginPage.getPageTitle(page);
            await expect(pageTitle).to.contains(loginPage.pageTitle);
            */
        });
    }
}
