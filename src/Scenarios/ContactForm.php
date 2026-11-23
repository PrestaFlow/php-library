<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Send a message through the contact form and find it in the back office.
 *
 * On a real shop the message lands in the merchant's Customer Service: it
 * says so ("PrestaFlow test message ... automated test, please ignore") and
 * comes from an address unique to the run (pf-contact-...@example.com) unless
 * the shop prefills a logged-in customer's one.
 *
 * The success is read from the contact form's own success alert, never from
 * its wording. The back-office check needs the project's back-office account:
 * without one, that last step is skipped.
 */
class ContactForm extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => an address unique to the run.
        'email' => null,
        // null => 'PrestaFlow test message <unique suffix>: automated test, please ignore.'
        'message' => null,
        // null => the first subject (id_contact) the form offers.
        'contactId' => null,
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $this->params['email'] = ($this->params['email'] ?? null) ?: self::pfUniqueEmail('pf-contact');
        $this->params['message'] = ($this->params['message'] ?? null)
            ?: 'PrestaFlow test message ' . self::pfUniqueSuffix() . ': automated test, please ignore.';

        $testSuite->importPage('FrontOffice\Contact');
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\CustomerService');

        extract($testSuite->pages);

        $testSuite
        ->it('open the contact form', function () use ($frontOfficeContactPage) {
            $frontOfficeContactPage->goToPage($frontOfficeContactPage);

            // Skips the suite when the shop has no contact form.
            $frontOfficeContactPage->requireContactForm();

            Expect::that($frontOfficeContactPage->getDocumentStatus())->isBetween(200, 399);
        })
        ->it('send a message', function () use ($frontOfficeContactPage) {
            $email = $frontOfficeContactPage->sendMessage($this->getParam('email'), $this->getParam('message'), $this->getParam('contactId'));

            Expect::that($frontOfficeContactPage->hasSentConfirmation())->equals(true);

            $this->store('contactEmail', $email);
        })
        ->it('find the message in the BackOffice customer service', function () use ($backOfficeLoginPage, $backOfficeCustomerServicePage) {
            // Skips this step when no back-office account is configured.
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);

            $backOfficeCustomerServicePage->goTo();

            Expect::that($backOfficeCustomerServicePage->hasThreadFrom($this->retrieve('contactEmail')))->equals(true);
        });

        return $testSuite;
    }
}
