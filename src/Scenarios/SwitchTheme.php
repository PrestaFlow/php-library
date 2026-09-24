<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Put the shop on a given front-office theme, through the back office.
 *
 * A fixture more than a test, in the same family as EnsureTestAccount: what it
 * guarantees is the END STATE, so a shop already on the right theme is a
 * legitimate pass and costs one page load.
 *
 * It exists because theme-aware selectors are only worth anything if the shop
 * actually runs the theme the suite claims. Writing ps_shop.theme_name in SQL
 * changes the name and nothing else — no hooks replayed, no module
 * registrations — so a suite validated that way proves less than it appears to.
 * This goes through Design > Theme & Logo, which is the flow a merchant uses.
 *
 * Set PRESTAFLOW_THEME (or the suite's theme param) to the same value, or the
 * pages will be resolving selectors for a theme the shop is no longer running.
 */
class SwitchTheme extends Scenario
{
    public $params = [
        'locale' => 'en',
        // Directory name of the theme, as it appears under themes/.
        // null => the theme the suite is already configured for, i.e.
        // PRESTAFLOW_THEME. That is almost always what you want: the point of
        // this scenario is to make the shop match the selectors the suite will
        // use, so having to state the theme twice is a chance to state it
        // twice differently.
        'theme' => null,
        // Which shop to act on: the "Use this theme" button is rendered
        // disabled outside a single-shop context.
        'shopId' => 1,
    ];

    public function steps($testSuite)
    {
        $testSuite->params['locale'] = $this->params['locale'] ?? 'en';

        // Read from $this->params rather than getParam(): the latter lives on
        // TestsSuite and is only reachable from inside an ->it() closure.
        $theme = $this->params['theme'] ?: ($this->globals['THEME'] ?? 'classic');
        $this->params['theme'] = $theme;

        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Theme');

        extract($testSuite->pages);

        $testSuite
        ->it('log in on the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('login');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);
        })
        ->it('find the theme in the BackOffice', function () use ($backOfficeThemePage) {
            $backOfficeThemePage->goTo((int) $this->getParam('shopId'));

            // A theme that is not installed would otherwise surface as a click
            // on a missing selector, which does nothing and says nothing.
            Expect::that($backOfficeThemePage->isThemeAvailable($this->getParam('theme')))
                ->equals(true);
        })
        ->it('apply the theme', function () use ($backOfficeThemePage) {
            $backOfficeThemePage->useTheme($this->getParam('theme'));

            // Applying a theme invalidates the Symfony container cache, and the
            // next back-office request can answer HTTP 500 while it rebuilds.
            // Re-open until the page is back, so the assertion below judges the
            // theme rather than the cache.
            Expect::that($backOfficeThemePage->reopenUntilReady(3, (int) $this->getParam('shopId')))
                ->equals(true);

            Expect::that($backOfficeThemePage->isCurrentTheme($this->getParam('theme')))
                ->equals(true);
        });

        return $testSuite;
    }
}
