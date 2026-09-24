<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Theme;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

/**
 * Design > Theme & Logo — switch the shop's front-office theme.
 *
 * Exists so the theme can be changed through the flow a merchant actually uses,
 * which replays the theme's hooks and module registrations. Writing
 * ps_shop.theme_name straight into the database changes the name and nothing
 * else, so a suite validated that way proves less than it looks like it does.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Theme & Logo';
    public string $menuSelector = '#subtab-AdminThemesParent';
    public string $parentMenuSelector = '#subtab-AdminParentThemes';

    public function defineSelectors()
    {
        return [
            // ${theme} is the theme's directory name (classic, hummingbird, ...),
            // which is what the card carries in data-role.
            'themeCard' => '.theme-card-container[data-role="${theme}"]',
            'useThemeButton' => '.theme-card-container[data-role="${theme}"] .js-display-use-theme-modal',
            'currentThemeMarker' => '.theme-card-container[data-role="${theme}"] .actions-container.active',
            // Rendered once, above the theme cards; its presence is what says
            // the page came back after the container-cache rebuild.
            'themeListMarker' => '[data-role="theme-shop"]',
            'confirmationModal' => '#use_theme_modal',
            'confirmButton' => '#use_theme_modal .js-submit-use-theme',
        ];
    }

    public function goTo(int $idShop = 1): void
    {
        // "Use this theme" carries a hard `disabled` attribute outside a single
        // shop context — index.html.twig renders it from isSingleShopContext.
        // On a multistore the page therefore looks perfectly normal and the
        // button simply does nothing, which is the same silent dead end the
        // One Page Checkout configuration page has.
        $this->setSingleShopContext($idShop);

        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    /**
     * Whether $theme is the one the shop currently uses.
     *
     * Reads the active marker on the card rather than the page title: the
     * current theme's card is the only one rendered with an active
     * actions-container, and that is true whichever theme it is.
     */
    public function isCurrentTheme(string $theme): bool
    {
        return $this->elementIsVisible(
            $this->getSelector('currentThemeMarker', ['theme' => $theme]),
            3000
        );
    }

    /**
     * Re-open the page until it renders again, and say whether it did.
     *
     * Absorbs the container-cache rebuild described on useTheme(): the request
     * right after a theme switch can answer HTTP 500, and a caller that asserts
     * straight away would fail on a switch that actually worked. Re-navigating
     * is what fixes it — a reload of the exception page would just show the
     * exception again.
     *
     * Returns false rather than throwing, so the caller decides whether a shop
     * whose back office never came back is a failure or a skip.
     */
    public function reopenUntilReady(int $attempts = 3, int $idShop = 1): bool
    {
        for ($try = 1; $try <= $attempts; ++$try) {
            $this->goTo($idShop);

            if ($this->elementIsVisible($this->getSelector('themeListMarker'), 5000)) {
                return true;
            }
        }

        return false;
    }

    public function isThemeAvailable(string $theme): bool
    {
        return $this->elementIsVisible($this->getSelector('themeCard', ['theme' => $theme]), 3000);
    }

    /**
     * Apply $theme, unless it is already the current one.
     *
     * Idempotent on purpose: callers assert the end state, so a shop that is
     * already on the right theme costs nothing and still proves the same thing.
     *
     * CAUTION — applying a theme invalidates PrestaShop's Symfony container
     * cache, and the very next back-office request can answer HTTP 500 while
     * that cache is being rebuilt:
     *
     *     require(var/cache/dev/admin/Container…/getAccessDeniedListenerService.php):
     *     Failed to open stream: No such file or directory
     *
     * Observed on 9.2.0 and reproduced: the switch itself lands in ps_shop, but
     * the page that follows is an exception page, so an assertion right after
     * this call can fail on a switch that actually worked. It is a real
     * PrestaShop fragility, not something this page can paper over — a caller
     * that chains straight into another back-office page should expect it, and
     * a suite that switches themes is better off warming the back office again
     * before carrying on.
     */
    public function useTheme(string $theme): void
    {
        if ($this->isCurrentTheme($theme)) {
            return;
        }

        $this->click($this->getSelector('useThemeButton', ['theme' => $theme]));

        $modalSelector = json_encode($this->getSelector('confirmationModal'));
        $this->waitForJsCondition(
            'document.querySelector(' . $modalSelector . ')'
            . '&&document.querySelector(' . $modalSelector . ').classList.contains("show")'
        );

        $this->click($this->getSelector('confirmButton'));
        $this->waitForPageReload();
    }
}
