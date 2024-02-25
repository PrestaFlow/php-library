<?php

namespace PrestaFlow\Library\Tests\Suites\Login;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class LoginTest extends TestsSuite
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

        $this->before();
        $page = $this->page;

        $dashboardPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Dashboard\Page;
        $loginPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Login\Page;

        $this->describe(
            'Check PS version {$PS_VERSION} with {$LOCALE} language, and login and log out from BO',
            [
                $this->it('should go to login page', function () use ($page, $globals, $loginPage) {
                    $loginPage->goTo($globals['BO']['URL'], $page);
                    Expect::that($loginPage->getPageTitle($page))->with($page)->contains($loginPage->pageTitle());
                }),
                $this->it('should check PS version', function () use ($page, $globals, $loginPage) {
                    $psVersion = $loginPage->getPrestaShopVersion($page);
                    Expect::that($psVersion)->with($page)->contains($globals['PS_VERSION']);
                }),
                $this->it('should try to login with wrong email and password', function () use ($page, $globals, $loginPage) {
                    $loginPage->login($page, 'wrongEmail@prestashop.com', 'wrongPass', false);

                    // Get error displayed
                    $errorMessage = $loginPage->getLoginError($page);
                    Expect::that($errorMessage)->with($page)->contains($loginPage->loginErrorText);
                }),
                $this->skip('should login into BO with default user', function () use ($page, $globals, $loginPage, $dashboardPage) {
                    /*

                    await loginPage.login(page, global.BO.EMAIL, global.BO.PASSWD);
                    await dashboardPage.closeOnboardingModal(page);

                    const pageTitle = await dashboardPage.getPageTitle(page);
                    await expect(pageTitle).to.contains(dashboardPage.pageTitle);
                    */
                }),
                $this->skip('should log out from BO', function () use ($page, $globals, $loginPage, $dashboardPage) {
                    /*
                    await dashboardPage.logoutBO(page);

                    const pageTitle = await loginPage.getPageTitle(page);
                    await expect(pageTitle).to.contains(loginPage.pageTitle);
                    */
                }),
            ]
        );
    }
}
