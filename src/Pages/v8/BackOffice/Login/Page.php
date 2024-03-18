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
    public function login($email = null, $password = null, $waitForNavigation = false)
    {
        if ($email === null) {
            $email = $this->getGlobal('BO_EMAIL');
        }
        if ($password === null) {
            $password = $this->getGlobal('BO_PASSWD');
        }

        $this->setValue($this->emailInput, $email);
        $this->setValue($this->passwordInput, $password);

        // Wait for navigation if login is successful
        if ($waitForNavigation) {
            //await this.clickAndWaitForNavigation(page, this.submitLoginButton);
        } else {
            $this->click($this->submitLoginButton);
        }
    }

    /**
     * Get login error
     */
    public function getLoginError()
    {
        return $this->getTextContent($this->alertDangerTextBlock);
    }

    public function getPrestashopVersion()
    {
        return $this->getTextContent($this->psVersionBlock);
    }
}
