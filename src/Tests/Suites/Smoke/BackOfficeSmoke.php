<?php

namespace PrestaFlow\Library\Tests\Suites\Smoke;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * The back-office walk every supported version has to survive.
 *
 * The back office was redesigned far more heavily than the front office
 * between 1.7 and 9, so this is where selectors inherited from v9 by the v7
 * and v8 stubs are most likely to miss. Steps are ordered so a failure names
 * what diverged: the login form, the rejection of bad credentials, the
 * logged-in header, the dashboard, or the logout link.
 *
 * The version cross-check runs LAST on purpose. It is a check on the RUNNER —
 * that the shop answering this port is the version we were configured for —
 * not a precondition of logging in, and putting it first would skip every
 * login step on a version whose login page does not print its version at all.
 *
 * Every step asserts: click() answers false on a missing selector instead of
 * raising, so an unasserted step would report success having done nothing.
 */
class BackOfficeSmoke extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Dashboard');

        extract($this->pages);

        $this
        // Every step runs even after a red one. This suite is an INVENTORY of
        // what differs per version, not a pass/fail gate, and the steps below
        // are independent enough for that to stay honest: each one navigates
        // for itself rather than inheriting the previous step's page. Stopping
        // at the first red would have hidden two of the three divergences
        // 1.7 and 8.2 actually have.
        ->skipWhenFailed(false)
        ->describe('Back office smoke')
        // Assert on the field login() actually needs, not on a decorative
        // block: a login page whose form markup moved has to fail HERE.
        ->it('the login form renders', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');

            Expect::that($backOfficeLoginPage->elementIsVisible(
                $backOfficeLoginPage->getSelector('emailInput'),
                5000
            ))->equals(true);
        })
        // A wrong password has to be REJECTED VISIBLY. Without this step, a
        // form whose submit selector missed would look exactly like one that
        // authenticated.
        ->it('wrong credentials are rejected with an error', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->login('wrong@prestashop.com', 'wrongPassword', false);

            Expect::that($backOfficeLoginPage->getLoginError())->isNotEmpty();
        })
        ->it('log in with the configured employee', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);
        })
        ->it('the dashboard is the page we land on', function () use ($backOfficeDashboardPage) {
            Expect::that($backOfficeDashboardPage->getPageTitle())
                ->contains($backOfficeDashboardPage->pageTitle());
        })
        // Assert we are back on the login FORM, not merely that some block is
        // present: a logout that silently did nothing would still leave a
        // rendered back office behind.
        ->it('log out again', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->logout();

            Expect::that($backOfficeLoginPage->elementIsVisible(
                $backOfficeLoginPage->getSelector('emailInput'),
                5000
            ))->equals(true);
        })
        // Cheap cross-check that the runner is pointed where it thinks it is.
        // Navigates for itself rather than trusting the previous step to have
        // left the login page on screen.
        ->it('the shop reports the version the runner was pointed at', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');

            Expect::that($backOfficeLoginPage->getPrestashopVersion())
                ->contains($backOfficeLoginPage->getGlobal('PS_VERSION'));
        });
    }
}
