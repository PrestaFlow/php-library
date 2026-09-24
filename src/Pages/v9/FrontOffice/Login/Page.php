<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Login;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;
use PrestaFlow\Library\Tests\TestsSuite;

class Page extends BasePage
{
    public string $pageTitle = 'Login';
    public string $url = 'login';

    public function defineSelectors()
    {
        return [
            'emailInput' => '#login-form input[name=\'email\']',
            'passwordInput' => '#login-form input[name=\'password\']',
            'submitLoginButton' => '#login-form button#submit-login',
            'alertDangerTextBlock' => '#content section.login-form div.help-block li.alert-danger',
            'logoutLink' => '#_desktop_user_info .user-info a[href*=\'mylogout\']',
        ];
    }

    /**
     * Enter credentials and submit login form
     */
    public function login($email = null, $password = null, $waitForNavigation = true)
    {
        if ($email === null) {
            $email = $this->getGlobal('FO_EMAIL');
        }
        if ($password === null) {
            $password = $this->getGlobal('FO_PASSWD');
        }

        $this->setValue($this->getSelector('emailInput'), $email);
        $this->setValue($this->getSelector('passwordInput'), $password);

        // Wait for navigation if login is successful
        if ($waitForNavigation) {
            $this->click($this->getSelector('submitLoginButton'));
            $this->waitForPageReload();
            //TestsSuite::getPage()->waitForReload();
        } else {
            $this->click($this->getSelector('submitLoginButton'));
        }
    }

    /**
     * Whether a customer session is actually open.
     *
     * login() fills the form and clicks without asserting anything, so bad
     * credentials complete as quietly as good ones. The logout link is only
     * rendered for an authenticated customer, which makes it the signal to
     * assert on.
     */
    public function isLoggedIn(): bool
    {
        return $this->elementIsVisible($this->getSelector('logoutLink'), 5000);
    }

    public function logout()
    {
        $this->click($this->getSelector('logoutLink'));
        $this->waitForReload();
    }
}
