<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Contact;

use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

/**
 * The contact page and the contactform module's form on it.
 *
 * Selectors use the field names the module posts (id_contact, from, message,
 * submitMessage), the same in the Classic templates of 1.7 to 9 and in
 * hummingbird's; the alerts are the module's own .alert-success /
 * .alert-danger, whatever the language.
 */
class Page extends BasePage
{
    public string $url = 'contact';
    public $shopPage = 'contact';

    /** Skip reason of requireContactForm(). */
    public const NO_CONTACT_FORM = 'This shop has no contact form on its contact page'
        . ' (the contactform module is missing or not shown).';

    public function defineSelectors()
    {
        return [
            'contactForm' => '.contact-form form',
            'subjectSelect' => '.contact-form select[name="id_contact"]',
            'emailInput' => '.contact-form input[name="from"]',
            'messageTextarea' => '.contact-form textarea[name="message"]',
            'requiredCheckbox' => '.contact-form input[type="checkbox"][required]',
            'submitButton' => '.contact-form [name="submitMessage"]',
            'successAlert' => '.contact-form .alert-success',
            'errorAlert' => '.contact-form .alert-danger',
        ];
    }

    /** Whether the contact page shows the contactform module's form. */
    public function hasContactForm(): bool
    {
        return $this->elementIsVisible($this->getSelector('messageTextarea'), 5000);
    }

    /** Skip the test and the rest of the suite when the page has no contact form. */
    public function requireContactForm(): void
    {
        if (!$this->hasContactForm()) {
            throw new SkipTestException(self::NO_CONTACT_FORM, skipRemaining: true);
        }
    }

    /**
     * Fill and send the contact form; answers the e-mail address it was sent
     * from.
     *
     * The e-mail field keeps what the shop prefilled (a logged-in customer's
     * address); otherwise $email is typed. $contactId picks the subject
     * (id_contact, as a suite passes it); without it, the first one. Required
     * consent boxes (GDPR) are ticked, optional ones left alone.
     */
    public function sendMessage(mixed $email, mixed $message, mixed $contactId = null): string
    {
        $subject = $this->getSelector('subjectSelect');
        $this->getPage()->evaluate(sprintf(
            '(function(){var s=document.querySelector(%s);if(!s)return;var want=%s;'
            . 'var o=[].slice.call(s.options).find(function(x){return want!==""?x.value===want:x.value!=="";});'
            . 'if(o){s.value=o.value;s.dispatchEvent(new Event("change",{bubbles:true}));}})()',
            json_encode($subject),
            json_encode(is_scalar($contactId) ? (string) $contactId : '')
        ));

        $prefilled = (string) $this->getInputValue($this->getSelector('emailInput'));
        $from = $prefilled !== '' ? $prefilled : (string) $email;
        if ($prefilled === '') {
            $this->setValueByJs($this->getSelector('emailInput'), $from);
        }

        $this->setValueByJs($this->getSelector('messageTextarea'), (string) $message);

        $this->getPage()->evaluate(sprintf(
            '(function(){[].slice.call(document.querySelectorAll(%s)).forEach(function(c){if(!c.checked){c.click();}});})()',
            json_encode($this->getSelector('requiredCheckbox'))
        ));

        $this->click($this->getSelector('submitButton'));
        $this->waitForPageReload();

        return $from;
    }

    /** Whether the module answered with its success alert, and no error. */
    public function hasSentConfirmation(): bool
    {
        return $this->elementIsVisible($this->getSelector('successAlert'), 5000)
            && !$this->elementIsVisible($this->getSelector('errorAlert'), 500);
    }
}
