<?php

namespace PrestaFlow\Library\Tests\Suites\Login;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class LoginTest extends TestsSuite
{
    public function init()
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

        $headless = false;
        $this->before($headless);

        $dashboardPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Dashboard\Page();
        $dashboardPage->setGlobals($globals);

        $loginPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Login\Page();
        $loginPage->setGlobals($globals);

        $this
        ->describe('Check PS version {$PS_VERSION} with {$LOCALE} language, and login and log out from BO')
        ->it('should go to login page', function () use ($loginPage) {
            $loginPage->goToPage('index');
            Expect::that($loginPage->getPageTitle())->contains($loginPage->pageTitle());
        })
        ->it('should check PS version', function () use ($loginPage) {
            $psVersion = $loginPage->getPrestaShopVersion();
            Expect::that($psVersion)->contains($loginPage->getGlobal('PS_VERSION'));
        })
        ->it('should try to login with wrong email and password', function () use ($loginPage) {
            $loginPage->login('wrongEmail@prestashop.com', 'wrongPass', false);

            // Get error displayed
            $errorMessage = $loginPage->getLoginError();
            Expect::that($errorMessage)->contains($loginPage->getMessage('loginErrorText'));
        })
        ->skip('should login into BO with default user', function () use ($loginPage, $dashboardPage) {
            /*

            await loginPage.login(page, global.BO.EMAIL, global.BO.PASSWD);
            await dashboardPage.closeOnboardingModal(page);

            const pageTitle = await dashboardPage.getPageTitle(page);
            await expect(pageTitle).to.contains(dashboardPage.pageTitle);
            */
        })
        ->skip('should log out from BO', function () use ($loginPage, $dashboardPage) {
            /*
            await dashboardPage.logoutBO(page);

            const pageTitle = await loginPage.getPageTitle(page);
            await expect(pageTitle).to.contains(loginPage.pageTitle);
            */
        });

        parent::init();
    }
}
