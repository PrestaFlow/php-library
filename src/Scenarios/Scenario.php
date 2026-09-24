<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Traits\ImportPage;
use PrestaFlow\Library\Traits\Locale;
use PrestaFlow\Library\Traits\Version;

class Scenario
{
    use Locale;
    use Version;
    use ImportPage;

    public $globals = [];
    public $params = [];
    public $pages = [];

    public function __construct($testSuite, $params = [])
    {
        $this->globals = $testSuite->getGlobals();
        $this->params = [...$this->params, ...$params];

        if (isset($this->params['locale']) && is_string($this->params['locale'])) {
            $this->globals['LOCALE'] = $this->params['locale'];
        }
        if (isset($this->params['useIsoCode'])) {
            $this->globals['PREFIX_LOCALE'] = (bool) $this->params['useIsoCode'];
        }

        $locale = $this->globals['LOCALE'] ?? $testSuite->getLocale();
        $versions = $this->globals['PATCH_VERSION'] ?? $testSuite->getVersions();

        $this->setVersions(versions: $versions);
        $this->setLocale(locale: $locale);
        $this->steps($testSuite);
    }

    public function steps($testSuite)
    {
        return $this;
    }

    /**
     * Put the shop on the four-page checkout tunnel, and prove it.
     *
     * A scenario that drives FrontOffice\Checkout needs the four-page tunnel.
     * Nothing used to say so, and the shop is global: the One Page Checkout
     * scenarios switch the layout on and deliberately never switch it back, so
     * running one of them first left the four-page scenarios walking a tunnel
     * that was no longer there. The failure was silent — the One Page Checkout
     * renders its own markup, our selectors stopped matching, and click()
     * returns false on a missing selector instead of raising.
     *
     * This is the missing half of "each scenario sets the state it needs": the
     * scenarios that turn the layout ON were written, the ones that need it OFF
     * were not. It lives on the base class so both callers share one definition
     * and cannot drift apart.
     *
     * The cost is real and worth stating: it pulls a back-office login into
     * scenarios that would otherwise be pure front office, so they now need
     * valid BO credentials. Asserting the layout without being able to set it
     * would only turn a silent failure into a loud one; setting it keeps the
     * scenarios independent of execution order, which is the point.
     */
    protected function requireFourPageCheckout($testSuite): void
    {
        // Before 9.2 there is no ps_onepagecheckout module, so no setting could
        // have moved the shop off the four-page tunnel. Nothing to do, and the
        // back-office page we would need does not exist.
        if (version_compare((string) $this->getMinorVersion(), '9.2', '<')) {
            return;
        }

        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\CheckoutLayout');

        $backOfficeLoginPage = $testSuite->pages['backOfficeLoginPage'];
        $backOfficeCheckoutLayoutPage = $testSuite->pages['backOfficeCheckoutLayoutPage'];

        $testSuite->it(
            'ensure the shop uses the four-page checkout',
            function () use ($backOfficeLoginPage, $backOfficeCheckoutLayoutPage) {
                $backOfficeLoginPage->goToPage('login');
                $backOfficeLoginPage->login();

                Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);

                $backOfficeCheckoutLayoutPage->goTo();

                // Only pay for the switch when the shop is actually on the wrong
                // layout; the assertion below covers both branches, so it states
                // the end state rather than what this run happened to do.
                if ($backOfficeCheckoutLayoutPage->isOnePageCheckoutSelected()) {
                    $backOfficeCheckoutLayoutPage->switchToFourPageCheckout();
                }

                Expect::that($backOfficeCheckoutLayoutPage->isFourPageCheckoutSelected())->equals(true);
            }
        );
    }

    public function it(string $description, $steps)
    {
        $this->suites[$this->getSuite()]['tests'][] = [
            'title' => $description,
            'steps' => $steps
        ];

        return $this;
    }
}
