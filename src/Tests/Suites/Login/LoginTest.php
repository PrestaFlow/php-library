<?php

namespace PrestaFlow\Library\Tests\Suites\Login;

use PrestaFlow\Library\Excepts\Except;
use PrestaFlow\Library\Tests\TestsSuite;

class LoginTest extends TestsSuite
{
    public function __construct()
    {
        $page = $this->page;
        $this->describe(
            'Check PS version ${global.PS_VERSION} with ${global.LOCALE} language, and login and log out from BO',
            [
                $this->it('should go to login page', function () use ($page) {
                    Expect::that("foo")->equals("foo"); //"foo" == "foo"

                }),
                $this->it('should check PS version', function () use ($page) {

                }),
                $this->it('should try to login with wrong email and password', function () use ($page) {

                }),
                $this->it('should login into BO with default user', function () use ($page) {

                }),
                $this->it('should log out from BO', function () use ($page) {

                }),
            ]
        );
    }

    /*
    it('should go to login page', async () => {
        await loginPage.goTo(page, global.BO.URL);
        const pageTitle = await loginPage.getPageTitle(page);
        await expect(pageTitle).to.contains(loginPage.pageTitle);
    });
    it('should check PS version', async () => {
        const psVersion = await loginPage.getPrestashopVersion(page);
        await expect(psVersion).to.contains(global.PS_VERSION);
    });
    it('should try to login with wrong email and password', async () => {
        await loginPage.login(page, 'wrongEmail@prestashop.com', 'wrongPass', false);

        // Get error displayed
        const errorMessage = await loginPage.getLoginError(page);
        await expect(errorMessage).to.contain(loginPage.loginErrorText);
    });
    it('should login into BO with default user', async () => {
        await loginPage.login(page, global.BO.EMAIL, global.BO.PASSWD);
        await dashboardPage.closeOnboardingModal(page);

        const pageTitle = await dashboardPage.getPageTitle(page);
        await expect(pageTitle).to.contains(dashboardPage.pageTitle);
    });
    it('should log out from BO', async () => {
        await dashboardPage.logoutBO(page);

        const pageTitle = await loginPage.getPageTitle(page);
        await expect(pageTitle).to.contains(loginPage.pageTitle);
    });
    */
}
