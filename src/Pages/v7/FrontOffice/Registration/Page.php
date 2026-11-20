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
        // The shop publishes its account creation URL (prestashop.urls.pages
        // .register: authentication with create_account=1 on 1.7), in its
        // language and with or without friendly URLs.
        // A project that remapped "login" keeps its route.
        $url = null;
        if (empty($this->getOverrideUrls()['login'])) {
            $this->pfLoadShopUrls();
            $url = $this->pfShopUrl('register');
        }

        if ($url === null) {
            // Built from the login URL rather than hardcoded, so a project that
            // remapped "login" in its own Urls catalogue keeps its route. Passing
            // the flag through goToPage()'s $params would drop it: substitution
            // only replaces {placeholders} the template already carries, and the
            // login template has none. "&" when the login URL already has a
            // query string (index.php?controller=authentication).
            $login = $this->getPageURL('login');
            $url = $login . (str_contains($login, '?') ? '&' : '?') . 'create_account=1';
        }

        $this->goToUrl($url);
    }
}
