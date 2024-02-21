<?php

namespace PrestaFlow\Library\Tests\Suites\Login;

use PrestaFlow\Library\Tests\TestsSuite;

class LoginTest extends TestsSuite
{
    public function __construct()
    {
        $this->describe(
            'Check PS version ${global.PS_VERSION} with ${global.LOCALE} language, and login and log out from BO',
            [
                $this->it('should go to login page', function () use ($page) {

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
   */
}
