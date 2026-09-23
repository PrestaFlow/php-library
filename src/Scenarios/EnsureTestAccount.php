<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Provision a known customer account, so scenarios that need a logged-in
 * customer have credentials they can rely on.
 *
 * This is a PROVISIONER, not a test of account creation — Registration is that
 * test. The distinction matters: on a shop where the account already exists this
 * scenario deliberately does not create anything, which would make it a
 * meaningless test but is exactly the right behaviour for a fixture.
 *
 * It stays honest by asserting the END STATE rather than the path: whichever
 * branch it took, the run only passes if the known credentials actually open a
 * session. Credentials default to the FO_EMAIL / FO_PASSWD globals
 * (PRESTAFLOW_FO_EMAIL / PRESTAFLOW_FO_PASSWD) so no password is hardcoded here.
 */
class EnsureTestAccount extends Scenario
{
    public $params = [
        'locale' => 'en',
        // null => fall back to the FO_EMAIL / FO_PASSWD globals.
        'email' => null,
        'password' => null,
        'firstName' => 'PrestaFlow',
        'lastName' => 'Fixture',
    ];

    public function steps($testSuite)
    {
        $testSuite->params['locale'] = $this->params['locale'] ?? 'en';

        $testSuite->importPage('FrontOffice\Login');
        $testSuite->importPage('FrontOffice\Registration');

        extract($testSuite->pages);

        $testSuite
        ->it('make sure the test account exists and its credentials work', function () use ($frontOfficeLoginPage, $frontOfficeRegistrationPage) {
            $email = $this->getParam('email');
            $password = $this->getParam('password');

            // Try the credentials first: if the account is already provisioned
            // there is nothing to create, and registering again would fail on a
            // duplicate e-mail.
            $frontOfficeLoginPage->goToPage('login');
            $frontOfficeLoginPage->login($email, $password);

            if (!$frontOfficeLoginPage->isLoggedIn()) {
                $frontOfficeRegistrationPage->goToRegistration();
                $frontOfficeRegistrationPage->register([
                    'firstName' => $this->getParam('firstName'),
                    'lastName' => $this->getParam('lastName'),
                    // login() resolved null to the globals; register() needs the
                    // values themselves, so read them the same way it did.
                    'email' => $email ?? $frontOfficeLoginPage->getGlobal('FO_EMAIL'),
                    'password' => $password ?? $frontOfficeLoginPage->getGlobal('FO_PASSWD'),
                ]);
            }

            // Asserted whichever branch ran: the account exists AND its
            // credentials open a session.
            Expect::that($frontOfficeLoginPage->isLoggedIn())->equals(true);
        });

        return $testSuite;
    }
}
