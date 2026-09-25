<?php

namespace PrestaFlow\Library\Pages\v7\FrontOffice\Registration;

use PrestaFlow\Library\Pages\v9\FrontOffice\Registration\Page as V9Page;

/**
 * 1.7 has no /registration route: it was introduced in 8.0, and asking a
 * 1.7.8 shop for it lands on pagenotfound. Account creation lives behind the
 * login page's create_account flag, and the resulting page is body#authentication,
 * not body#registration -- so the two selectors anchored on that id have to move
 * with the route.
 *
 * Everything else the form needs is already identical: #field-firstname,
 * #field-lastname, #field-email, #field-password, #field-birthday,
 * [data-link-action="save-customer"] and the two required consent boxes are all
 * present on 1.7.8.11, which is why only these three things are overridden.
 */
class Page extends V9Page
{
    public function defineSelectors()
    {
        return [
            ...parent::defineSelectors(),
            'registrationForm' => 'body#authentication',
            'requiredConsentCheckbox' => 'body#authentication input[type="checkbox"][required]',
        ];
    }

    public function goToRegistration(): void
    {
        // Built from the login URL rather than hardcoded, so a project that
        // remapped "login" in its own Urls catalogue keeps its route. Passing
        // the flag through goToPage()'s $params would drop it: substitution
        // only replaces {placeholders} the template already carries, and the
        // login template has none.
        $this->goToUrl($this->getPageURL('login') . '?create_account=1');
    }
}
