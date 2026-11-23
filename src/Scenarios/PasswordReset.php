<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Ask for a password reset link from the "forgot your password?" page.
 *
 * The request is made for the project's customer account (FO_EMAIL, or the
 * email param); without one, the suite is skipped. It only ASKS for the link:
 * the password itself never changes, since the link goes to the account's
 * mailbox, which a test cannot read. Other suites can keep signing in with
 * the account.
 *
 * The answer is read from the page structure, never from its wording:
 *  - the confirmation page (the form gives way to the success list) passes.
 *    It does not prove the account exists: PrestaShop answers the same for
 *    an unknown address, so as not to reveal its customers;
 *  - the throttle error passes too. PrestaShop refuses a new link less than
 *    PS_PASSWD_TIME_FRONT minutes (360 by default) after the password was
 *    last CHANGED through a link; the shop did process the form, and asking
 *    again for a link is not something a customer can be blamed for;
 *  - any other error fails with the shop's own message (an inactive account,
 *    an e-mail the shop could not send...).
 */
class PasswordReset extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => the project's customer account (FO_EMAIL).
        'email' => null,
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $testSuite->importPage('FrontOffice\Password');

        extract($testSuite->pages);

        $testSuite
        ->it('open the forgotten password page', function () use ($frontOfficePasswordPage) {
            // Skips the suite when no customer account is configured.
            $frontOfficePasswordPage->requireAccountEmail($this->getParam('email'));

            $frontOfficePasswordPage->goToPage($frontOfficePasswordPage);

            // Skips the suite when the shop shows no forgotten-password form.
            $frontOfficePasswordPage->requirePasswordForm();

            Expect::that($frontOfficePasswordPage->getDocumentStatus())->isBetween(200, 399);
        })
        ->it('ask for a reset link for the customer account', function () use ($frontOfficePasswordPage) {
            $frontOfficePasswordPage->requestResetLink($this->getParam('email'));

            // Empty unless the shop refused the request: then its own message.
            Expect::that($frontOfficePasswordPage->getUnexpectedResetError())->equals('');
            Expect::that($frontOfficePasswordPage->hasResetConfirmation())->equals(true);
        });

        return $testSuite;
    }
}
