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
            'alertDangerTextBlock' => '.alert-danger',
            'employeeInfosDropDown' => '#employee_infos a',
            'headerEmployeeContainer' => '#header-employee-container',
            'logoutLink' => '#header_logout',
        ];
    }

    public function logout()
    {
        // The logout link lives inside a collapsed dropdown (hidden until opened),
        // which coordinate-based clicks can't reach reliably. Navigate to the stable
        // logout route instead; it clears the session and redirects to the login page.
        $this->goToPage('logout');
    }
}
