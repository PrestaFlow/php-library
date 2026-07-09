<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Login;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'PrestaShop';

    public function defineSelectors()
    {
        return [
            // Login header selectors
            'loginHeaderBlock' => '#login-header',
            // PS9: version label moved to an h4 inside the login form.
            'psVersionBlock' => '#login_form h4',
            // Login Form selectors
            'emailInput' => '#email',
            'passwordInput' => '#passwd',
            'submitLoginButton' => '#submit_login',
            // PS9: bootstrap-style .alert-danger replaces the legacy #error block.
            'alertDangerDiv' => '.alert-danger',
            'alertDangerTextBlock' => '.alert-danger',
            //
            'employeeInfosDropDown' => '#employee_infos a',
            // PS9: employee dropdown lives inside this container (collapsed by default).
            'headerEmployeeContainer' => '#header-employee-container',
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
        // PS9: the logout link lives inside a collapsed dropdown (hidden until opened),
        // which coordinate-based clicks can't reach reliably. Navigate to the stable
        // logout route instead; it clears the session and redirects to the login page.
        $this->goToPage('logout');
    }

    public function getPrestashopVersion()
    {
        return $this->getTextContent($this->getSelector('psVersionBlock'));
    }
}
