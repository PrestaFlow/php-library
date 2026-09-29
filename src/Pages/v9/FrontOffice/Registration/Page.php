<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Registration;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

/**
 * Customer account creation form.
 *
 * Selectors verified against a live PrestaShop 9.2.0 shop running hummingbird,
 * PS 9's default theme. The field ids (#field-<name>) come from the theme's
 * form-field component and are shared with classic.
 *
 * Like the One Page Checkout's contact section, this form gates submission on
 * required consent checkboxes (customer_privacy, psgdpr on the reference shop).
 * Their exact set depends on which GDPR-ish modules are installed, so they are
 * matched by the required attribute rather than listed by name — and the
 * optional ones (optin, newsletter) are deliberately left untouched, since a
 * test must not silently opt a fixture customer into marketing.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Registration';
    public string $url = 'registration';

    public function defineSelectors()
    {
        return [
            'registrationForm' => 'body#registration',
            'firstNameInput' => '#field-firstname',
            'lastNameInput' => '#field-lastname',
            'emailInput' => '#field-email',
            'passwordInput' => '#field-password',
            'birthdayInput' => '#field-birthday',
            // Scoped to the registration page so the footer newsletter block can
            // never be caught by it.
            'requiredConsentCheckbox' => 'body#registration input[type="checkbox"][required]',
            // Hummingbird's password policy: the wrapper around the password
            // input, and the strength feedback it un-hides once the score is in.
            // Classic has neither, which is what makes the wait below a no-op there.
            'passwordPolicyField' => '[data-ps-ref="password-field"]',
            'passwordFeedbackContainer' => '[data-ps-ref="password-feedback-container"]',
            'submitButton' => '.form-control-submit, [data-link-action="save-customer"]',
            'errorAlert' => '.alert-danger, .help-block .alert-danger',
        ];
    }

    public function goToRegistration(): void
    {
        $this->goToPage('registration');
    }

    /**
     * Whether the account creation form is actually on screen.
     *
     * Worth asserting before filling anything: a shop that already has a session
     * open redirects /registration to the account page, and the failure would
     * otherwise surface as a missing field rather than as "we were already
     * logged in".
     */
    public function isRegistrationFormVisible(): bool
    {
        return $this->elementIsVisible($this->getSelector('emailInput'), 5000);
    }

    /**
     * Fill the form and submit it.
     *
     * Does not assert the outcome — the caller decides what success means
     * (landing logged in, or an expected validation error).
     *
     * @param array{firstName?:string,lastName?:string,email:string,password:string,birthday?:string} $customer
     */
    public function register(array $customer): void
    {
        $this->setValueByJs($this->getSelector('firstNameInput'), $customer['firstName'] ?? 'PrestaFlow');
        $this->setValueByJs($this->getSelector('lastNameInput'), $customer['lastName'] ?? 'Test');
        $this->setValueByJs($this->getSelector('emailInput'), $customer['email']);
        $this->setValueByJs($this->getSelector('passwordInput'), $customer['password']);

        if (!empty($customer['birthday'])) {
            $this->setValueByJs($this->getSelector('birthdayInput'), $customer['birthday']);
        }

        $this->waitForPasswordVerdict();
        $this->acceptRequiredConsents();

        $this->click($this->getSelector('submitButton'));
        $this->waitForPageReload();
    }

    /**
     * Wait until the theme has rendered its verdict on the password, so the
     * form stops moving before the submit is clicked.
     *
     * On hummingbird the password's input event starts an async score check;
     * when it resolves, the strength feedback under the field is un-hidden and
     * pushes everything below it — the consent boxes and the submit button —
     * down by about 100px. click() is a real mouse click at coordinates read
     * just before it, so when the feedback lands between that read and the
     * press/release, the release hits a consent label instead: the click never
     * reaches the button, the label unticks a required consent, and nothing is
     * submitted. On a cold browser that race was lost in 5 runs out of 10.
     *
     * Returns whether the verdict showed up. A timeout is not an error here —
     * register() leaves the outcome to the caller — and on a theme without the
     * policy markup (classic renders its feedback up front) this returns at once.
     */
    public function waitForPasswordVerdict(int $timeout = 10000): bool
    {
        $condition = sprintf(
            '(function(){var p=document.querySelector(%s);if(!p||p.value===""){return true;}'
            . 'var f=p.closest(%s);if(!f){return true;}'
            . 'var c=f.querySelector(%s);return !!c&&!c.classList.contains("d-none");})()',
            json_encode($this->getSelector('passwordInput')),
            json_encode($this->getSelector('passwordPolicyField')),
            json_encode($this->getSelector('passwordFeedbackContainer'))
        );

        return $this->waitForJsCondition($condition, $timeout);
    }

    /**
     * Tick every required consent box, and only those.
     *
     * Without them the form refuses to submit; with the optional ones ticked the
     * fixture customer would be subscribed to marketing it never asked for.
     */
    public function acceptRequiredConsents(): void
    {
        $selector = json_encode($this->getSelector('requiredConsentCheckbox'));

        $this->getPage()->evaluate(
            '(function(){[].slice.call(document.querySelectorAll(' . $selector . '))'
            . '.forEach(function(c){if(!c.checked){c.click();}});})()'
        );
    }

    /**
     * The form's error text, empty when the submission was accepted. Lets a
     * caller tell "this e-mail is already registered" apart from a broken run.
     */
    public function getErrorMessage(): string
    {
        if (!$this->elementIsVisible($this->getSelector('errorAlert'), 2000)) {
            return '';
        }

        return trim($this->getTextContent($this->getSelector('errorAlert')));
    }
}
