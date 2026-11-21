<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Password;

use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

/**
 * The "forgot your password?" page (PasswordController, customer/password-email.tpl).
 *
 * The same in 1.7 to 9, Classic and hummingbird:
 *  - the form is form.forgotten-password, its field is input[name="email"]
 *    and its button #send-reset-link;
 *  - an accepted request switches to customer/password-infos.tpl: the form
 *    is gone and the confirmation is a success list (Classic's
 *    ul.ps-alert-success, hummingbird's .alert-success);
 *  - a refused one keeps the form with an error list inside it (Classic's
 *    ul.ps-alert-error, hummingbird's .alert-danger).
 *
 * Nothing here reads the shop's wording. The one error told apart is the
 * throttle (PS_PASSWD_TIME_FRONT): "You can regenerate your password only
 * every %d minute(s)" is the only error of the request with a number in it,
 * in every language.
 */
class Page extends BasePage
{
    public string $url = 'password-recovery';
    public $shopPage = 'password';

    /** Skip reason of requirePasswordForm(). */
    public const NO_PASSWORD_FORM = 'This shop has no forgotten-password form on its password recovery page.';

    /** Skip reason of requireAccountEmail(). */
    public const NO_ACCOUNT_EMAIL = 'No customer account configured: set PRESTAFLOW_FO_EMAIL'
        . ' (the project\'s customer account), or the email param.';

    /** getResetOutcome(): the shop accepted the request (confirmation page). */
    public const OUTCOME_SENT = 'sent';
    /** getResetOutcome(): the shop throttled the request (PS_PASSWD_TIME_FRONT). */
    public const OUTCOME_THROTTLED = 'throttled';
    /** getResetOutcome(): the shop refused the request with another error. */
    public const OUTCOME_ERROR = 'error';
    /** getResetOutcome(): neither a confirmation nor an error. */
    public const OUTCOME_NONE = 'none';

    public function defineSelectors()
    {
        return [
            'passwordForm' => 'form.forgotten-password',
            'emailInput' => 'form.forgotten-password input[name="email"]',
            'submitButton' => 'form.forgotten-password #send-reset-link',
            'successItem' => 'ul.ps-alert-success > li, .alert-success li',
            'errorItem' => 'form.forgotten-password ul.ps-alert-error > li, form.forgotten-password .alert-danger li',
        ];
    }

    /** Whether the page shows the forgotten-password form. */
    public function hasPasswordForm(): bool
    {
        return $this->elementIsVisible($this->getSelector('emailInput'), 5000);
    }

    /** Skip the test and the rest of the suite when the page has no forgotten-password form. */
    public function requirePasswordForm(): void
    {
        if (!$this->hasPasswordForm()) {
            throw new SkipTestException(self::NO_PASSWORD_FORM, skipRemaining: true);
        }
    }

    /**
     * The address a reset is requested for: $email, else the project's
     * customer account (FO_EMAIL). Empty when neither is set.
     */
    public function accountEmail(mixed $email = null): string
    {
        if (is_string($email) && trim($email) !== '') {
            return trim($email);
        }

        $global = $this->getGlobal('FO_EMAIL');

        return is_string($global) ? trim($global) : '';
    }

    /** Skip the test and the rest of the suite when no account address is known. */
    public function requireAccountEmail(mixed $email = null): void
    {
        if ($this->accountEmail($email) === '') {
            throw new SkipTestException(self::NO_ACCOUNT_EMAIL, skipRemaining: true);
        }
    }

    /**
     * Ask for a reset link for $email (else FO_EMAIL); answers the address
     * used. Only asks: the password itself never changes, the link goes to
     * the account's mailbox.
     */
    public function requestResetLink(mixed $email = null): string
    {
        $this->requireAccountEmail($email);
        $address = $this->accountEmail($email);

        $this->setValueByJs($this->getSelector('emailInput'), $address);
        $this->click($this->getSelector('submitButton'));
        $this->waitForPageReload();

        return $address;
    }

    /**
     * How the shop answered the request, read from the page structure only:
     * OUTCOME_SENT, OUTCOME_THROTTLED, OUTCOME_ERROR or OUTCOME_NONE.
     */
    public function getResetOutcome(): string
    {
        $state = $this->readAnswer();

        if ($state['errors'] !== []) {
            return count($state['errors']) === 1 && preg_match('/\d/', $state['errors'][0]) === 1
                ? self::OUTCOME_THROTTLED
                : self::OUTCOME_ERROR;
        }

        if ($state['successes'] > 0 && !$state['form']) {
            return self::OUTCOME_SENT;
        }

        return self::OUTCOME_NONE;
    }

    /**
     * Whether the shop handled the request: the confirmation, or the throttle
     * error (a link was asked for less than PS_PASSWD_TIME_FRONT minutes after
     * the last password change: the shop did process the form).
     */
    public function hasResetConfirmation(): bool
    {
        return in_array($this->getResetOutcome(), [self::OUTCOME_SENT, self::OUTCOME_THROTTLED], true);
    }

    /**
     * The shop's own error message when it refused the request for another
     * reason than the throttle (an inactive account, an e-mail it could not
     * send...); '' otherwise. Meant to be asserted empty, so that a failure
     * shows the shop's message.
     */
    public function getUnexpectedResetError(): string
    {
        $outcome = $this->getResetOutcome();
        if ($outcome === self::OUTCOME_ERROR) {
            return implode(' / ', $this->readAnswer()['errors']);
        }
        if ($outcome === self::OUTCOME_NONE) {
            return 'The shop answered neither a confirmation nor an error.';
        }

        return '';
    }

    /** @return array{form: bool, successes: int, errors: list<string>} */
    private function readAnswer(): array
    {
        // Wait for either answer (or the form back without one) before reading.
        $this->waitForJsCondition(sprintf(
            'document.readyState==="complete"&&(document.querySelector(%s)||document.querySelector(%s)||document.querySelector(%s))',
            json_encode($this->getSelector('successItem')),
            json_encode($this->getSelector('errorItem')),
            json_encode($this->getSelector('passwordForm'))
        ), 5000);

        $json = $this->getPage()->evaluate(sprintf(
            '(function(){var t=function(s){return [].slice.call(document.querySelectorAll(s))'
            . '.map(function(e){return (e.textContent||"").replace(/\s+/g," ").trim();})'
            . '.filter(function(x){return x!=="";});};'
            . 'return JSON.stringify({form:!!document.querySelector(%s),successes:t(%s).length,errors:t(%s)});})()',
            json_encode($this->getSelector('passwordForm')),
            json_encode($this->getSelector('successItem')),
            json_encode($this->getSelector('errorItem'))
        ))->getReturnValue();

        $state = is_string($json) ? json_decode($json, true) : null;

        return [
            'form' => (bool) ($state['form'] ?? false),
            'successes' => (int) ($state['successes'] ?? 0),
            'errors' => array_values(array_map('strval', (array) ($state['errors'] ?? []))),
        ];
    }
}
