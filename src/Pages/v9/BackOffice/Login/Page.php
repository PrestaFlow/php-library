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
     * Whether an employee session is actually open.
     *
     * login() fills the form and clicks, but asserts nothing: with wrong
     * credentials it completes just as quietly as with good ones, and every
     * later back-office step then silently does nothing while still reporting
     * success. Callers should assert on this instead of trusting login().
     *
     * The logout link is the marker: it only exists for an authenticated
     * employee. Deliberately NOT headerEmployeeContainer — that id belongs to
     * the 9.0-era header and is absent from 9.2, where a probe of a live
     * dashboard returns #header-employee-container: 0 but #header_logout: 1.
     */
    public function isLoggedIn(): bool
    {
        return $this->elementIsVisible($this->getSelector('logoutLink'), 5000);
    }

    /**
     * The error the login form shows, once it actually shows one.
     *
     * The wait is on CONTENT, not on presence, because presence proves nothing
     * on half the supported versions. 1.7 and 8 submit the admin login over
     * AJAX (`<form action="#">`, driven by js/admin/login.js) and ship the
     * container empty in the initial HTML:
     *
     *     <div id="error" class="hide alert alert-danger"></div>
     *
     * A presence wait finds that node on the first poll and reads "" whenever
     * the XHR has not landed yet — which, measured across repeated runs, hit
     * both outcomes on both versions. 9 does a real POST and only renders
     * .alert-danger on the error response, so presence was sufficient there
     * and a content wait is equally correct.
     *
     * Falling through on timeout rather than throwing keeps the failure where
     * it belongs: the caller asserts on the message and fails with its own
     * message, instead of a timeout thrown from inside a getter.
     */
    public function getLoginError(int $timeout = 10000, int $interval = 100)
    {
        $selector = $this->getSelector('alertDangerTextBlock');

        $this->waitForJsCondition(
            sprintf(
                '(function(){var e=document.querySelector(%s);return !!e && e.textContent.trim() !== "";})()',
                json_encode($selector)
            ),
            $timeout,
            $interval
        );

        return $this->getTextContent($selector);
    }

    /**
     * Log the employee out by following the back office's own logout link.
     *
     * This used to navigate to {BO_URL}logout, which is a Symfony route on 9
     * and nothing at all on 1.7 and 8: their legacy dispatcher ignores the
     * path and serves the dashboard, so logout() returned having done nothing
     * and the employee stayed authenticated. Unauthenticated, that same URL
     * 302s to the login page, which is why probing it with curl never showed
     * the defect — only a browser holding a live session does.
     *
     * The fix deliberately does NOT branch on the version, in either of the
     * two places it could have. Overriding logout() in the v7 and v8 page
     * objects would spread one behaviour across three classes, and branching
     * on getMinorVersion() here would hardcode URL shapes we cannot build
     * anyway: 1.7 and 8 need a live `token`, 9 a live `_token`, and both would
     * have to be read off #header_logout regardless. So we use the href the
     * shop itself put there. #header_logout is the same selector, carrying a
     * correct version-specific href, on 1.7.8, 8.2 and 9.2 alike: each version
     * resolves its own difference and the page object stays version-agnostic.
     *
     * Not being logged in is an error, not a no-op — the previous silence is
     * precisely what hid this.
     */
    public function logout()
    {
        $selector = $this->getSelector('logoutLink');

        $href = $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return e && e.href ? e.href : null;})()',
            json_encode($selector)
        ))->getReturnValue();

        if (!is_string($href) || $href === '') {
            throw new \RuntimeException(
                'Cannot log out: no logout link found at "' . $selector . '". '
                . 'The back office is most likely not authenticated.'
            );
        }

        $this->goToUrl($href);
    }
}
