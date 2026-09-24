<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Create a customer account from the FrontOffice.
 *
 * The e-mail is unique per run by default, so the scenario really exercises
 * account creation every single time. A fixed e-mail would succeed once and
 * then fail — or, worse, quietly stop testing anything — on every later run
 * against the same shop.
 *
 * Pass an explicit `email` param to provision a known account instead; see
 * EnsureTestAccount, which wraps that use case.
 */
class Registration extends Scenario
{
    public $params = [
        'locale' => 'en',
        // null => a unique address is generated for this run.
        'email' => null,
        'password' => 'PrestaFlow2026!',
        'firstName' => 'PrestaFlow',
        'lastName' => 'Tester',
    ];

    public function steps($testSuite)
    {
        $testSuite->params['locale'] = $this->params['locale'] ?? 'en';

        $testSuite->importPage('FrontOffice\Registration');
        $testSuite->importPage('FrontOffice\Login');

        extract($testSuite->pages);

        // Read from $this->params, not getParam(): the latter lives on TestsSuite
        // and is only reachable from inside an ->it() closure, which is rebound
        // to the suite at execution time. $this->params already carries the
        // constructor overrides.
        $email = ($this->params['email'] ?? null) ?: $this->uniqueEmail();

        $testSuite
        ->it('open the account creation form', function () use ($frontOfficeRegistrationPage) {
            $frontOfficeRegistrationPage->goToRegistration();

            Expect::that($frontOfficeRegistrationPage->isRegistrationFormVisible())->equals(true);
        })
        ->it('create the account', function () use ($frontOfficeRegistrationPage, $frontOfficeLoginPage, $email) {
            $frontOfficeRegistrationPage->register([
                'firstName' => $this->getParam('firstName'),
                'lastName' => $this->getParam('lastName'),
                'email' => $email,
                'password' => $this->getParam('password'),
            ]);

            // PrestaShop logs the customer in straight after a successful
            // registration, so an open session is the proof the account was
            // created — and it fails loudly if the form came back with an error.
            Expect::that($frontOfficeLoginPage->isLoggedIn())->equals(true);

            $this->store('registeredEmail', $email);
        });

        return $testSuite;
    }

    /**
     * A per-run address. The shop rejects a duplicate e-mail, so reusing one
     * would turn the second run into a false failure.
     */
    private function uniqueEmail(): string
    {
        return sprintf('pf-register-%s@example.com', date('Ymd-His'));
    }
}
