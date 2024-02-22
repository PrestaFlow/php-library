<?php

namespace PrestaFlow\Library\Pages\v8\BackOffice\Login;

use PrestaFlow\Library\Pages\v8\BackOffice\BasePage;

class Page extends BasePage
{
    public function __construct()
    {
        $this->pageTitle = 'PrestaShop';

        $this->loginErrorText = 'The employee does not exist, or the password provided is incorrect.';

        // Login header selectors
        $this->loginHeaderBlock = '#login-header';
        $this->psVersionBlock = $this->loginHeaderBlock . ' div.text-center';

        // Login Form selectors
        $this->emailInput = '#email';
        $this->passwordInput = '#passwd';
        $this->submitLoginButton = '#submit_login';
        $this->alertDangerDiv = '#error';
        $this->alertDangerTextBlock = $this->alertDangerDiv . ' li';
    }

    /**
     * Enter credentials and submit login form
     */
    public function login($page, $email = null, $password = null, $waitForNavigation = true)
    {
        $this->setValue($page, $this->emailInput, $email);
        $this->setValue($page, $this->passwordInput, $password);

        // Wait for navigation if login is successful
        if ($waitForNavigation) {
            //await this.clickAndWaitForNavigation(page, this.submitLoginButton);
        } else {
            $this->click($page, $this->submitLoginButton);
        }
    }

    /**
     * Get login error
     */
    public function getLoginError($page)
    {
        return $this->getTextContent($page, $this->alertDangerTextBlock);
    }

    public function getPrestashopVersion($page)
    {
        return $this->getTextContent($page, $this->psVersionBlock);
    }
}
