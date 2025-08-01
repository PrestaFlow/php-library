<?php

namespace PrestaFlow\Library\Pages\v8\BackOffice\Login;

use PrestaFlow\Library\Pages\v8\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'PrestaShop';

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
            //
            'employeeInfosDropDown' => '#employee_infos a',
            'logoutLink' => '#header_logout',
        ];
    }

    public function defineMessages()
    {
        return [
            'loginErrorText' => $this->translate('The employee does not exist, or the password provided is incorrect.'),
        ];
    }

    /**
     * Enter credentials and submit login form
     */
    public function login($email = null, $password = null, $waitForNavigation = true)
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
            $this->click($this->getSelector('submitLoginButton'));
            $this->waitForPageReload();
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

    public function logout()
    {
        $this->click($this->getSelector('employeeInfosDropDown'));
        $this->click($this->getSelector('logoutLink'));
    }

    public function getPrestashopVersion()
    {
        return $this->getTextContent($this->getSelector('psVersionBlock'));
    }
}
