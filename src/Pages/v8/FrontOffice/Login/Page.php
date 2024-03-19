<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\Login;

use PrestaFlow\Library\Pages\v8\FrontOffice\BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Login';
    public string $url = 'login';

    public function __construct()
    {
        $this->pageTitle = 'Identifiant';

        parent::__construct();
    }

    public function defineSelectors()
    {
        return [
            'homePageSection' => '#content.page-home',
            'emailInput' => '#login-form input[name=\'email\']',
            'passwordInput' => '#login-form input[name=\'password\']',
            'submitLoginButton' => '#login-form button#submit-login',
            'alertDangerTextBlock' => '#content section.login-form div.help-block li.alert-danger',
        ];
    }

    /**
     * Enter credentials and submit login form
     */
    public function login($email = null, $password = null, $waitForNavigation = false)
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
            //await this.clickAndWaitForNavigation(page, this.submitLoginButton);
        } else {
            $this->click($this->getSelector('submitLoginButton'));
        }
    }
}
