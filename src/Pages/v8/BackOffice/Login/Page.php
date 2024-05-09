<?php

namespace PrestaFlow\Library\Pages\v8\BackOffice\Login;

use PrestaFlow\Library\Pages\v8\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'PrestaShop';

    public function __construct()
    {
        $this->pageTitle = 'PrestaShop';

        parent::__construct();
    }

    public function defineSelectors()
    {
        return [
            // Login header selectors
            'loginHeaderBlock' => '#login-header',
            'psVersionBlock' => '#login-header div.text-center',
            // Login Form selectors
            'emailInput' => '#email',
            'passwordInput' => '#passwd',
            'submitLoginButton' => '#submit_login',
            'alertDangerDiv' => '#error',
            'alertDangerTextBlock' => '#error li',
        ];
    }

    public function defineMessages()
    {
        return [
            //'loginErrorText' => 'The employee does not exist, or the password provided is incorrect.',
            'loginErrorText' => 'Ce compte employé n\'existe pas, ou le mot de passe est erroné.',
        ];
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

        $this->setValue($this->getSelector('emailInput'), $email);
        $this->setValue($this->getSelector('passwordInput'), $password);

        // Wait for navigation if login is successful
        if ($waitForNavigation) {
            //await this.clickAndWaitForNavigation(page, this.submitLoginButton);
        } else {
            $this->click($this->getSelector('submitLoginButton'));
        }
    }

    /**
     * Get login error
     */
    public function getLoginError()
    {
        return $this->getTextContent($this->getSelector('alertDangerTextBlock'));
    }

    public function getPrestashopVersion()
    {
        return $this->getTextContent($this->getSelector('psVersionBlock'));
    }
}
