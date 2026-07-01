<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Login;

use PrestaFlow\Library\Pages\Common\BackOffice\Login\Page as BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'loginHeaderBlock' => '#login-header',
            'psVersionBlock' => '#login_form h4',
            'emailInput' => '#email',
            'passwordInput' => '#passwd',
            'submitLoginButton' => '#submit_login',
            'alertDangerDiv' => '.alert-danger',
            'alertDangerTextBlock' => '.alert-danger .alert-text',
            'employeeInfosDropDown' => '#employee_infos a',
            'headerEmployeeContainer' => '#header-employee-container',
            'logoutLink' => '#header_logout',
        ];
    }

    public function logout()
    {
        if ($this->isVisible($this->selector('employeeInfosDropDown')) !== false) {
            $this->leftClick($this->getSelector('employeeInfosDropDown'));
        } else if ($this->isVisible($this->selector('headerEmployeeContainer')) !== false) {
            $this->leftClick($this->getSelector('headerEmployeeContainer'));
        }

        $this->click($this->getSelector('logoutLink'));
    }
}
