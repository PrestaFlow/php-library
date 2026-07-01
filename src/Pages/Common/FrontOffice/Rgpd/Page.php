<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice\Rgpd;

use HeadlessChromium\Cookies\Cookie;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

/**
 * Page « RGPD / bandeau de consentement cookies ».
 *
 * Cible en priorité le module Knowband « GDPR Cookie » (préfixe `kb-`, cookie
 * `___kbgdcc`), très répandu, avec un repli générique « tout accepter ».
 *
 * Sur un site avec bandeau de consentement, celui-ci se superpose au contenu et
 * peut fausser les assertions (ex. le `<h1>` du bandeau devient le premier de la
 * page). Deux stratégies :
 *  - `preAccept()` : pré-règle le cookie de consentement AVANT navigation
 *    (le plus fiable — le bandeau ne s'affiche pas) ;
 *  - `accept()` : clique le bouton d'acceptation si le bandeau est affiché.
 */
class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            // Module Knowband GDPR Cookie
            'knowbandModal'     => '#kb-cookie-setting-modal',
            'knowbandSavePrefs' => '#kb-cookie-setting-modal .kb-btn',
            // Repli générique
            'acceptAll'         => '#cookie-accept, .js-accept-all-cookies, [data-accept-all-cookies]',
        ];
    }

    /** true si un bandeau / modal de consentement est visible. */
    public function isDisplayed(): bool
    {
        return $this->isVisible($this->selector('knowbandModal'), 1500)
            || $this->isVisible($this->selector('acceptAll'), 500);
    }

    /**
     * Pré-règle le cookie de consentement Knowband (`___kbgdcc`) pour que le
     * bandeau ne s'affiche pas. À appeler AVANT la première navigation.
     *
     * Astuce : pour rester agnostique, on peut aussi passer par la variable
     * d'environnement PRESTAFLOW_COOKIES (gérée par TestsSuite).
     */
    public function preAccept(string $domain): void
    {
        try {
            $this->getPage()->setCookies([
                Cookie::create(
                    '___kbgdcc',
                    // {"1":{"active":"1","modules":""}} — consentement enregistré.
                    'eyIxIjp7ImFjdGl2ZSI6IjEiLCJtb2R1bGVzIjoiIn19',
                    ['domain' => $domain, 'path' => '/', 'secure' => true]
                ),
            ]);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /** Clique le bouton d'acceptation si le bandeau est affiché (no-op sinon). */
    public function accept(): void
    {
        foreach (['acceptAll', 'knowbandSavePrefs'] as $selector) {
            if ($this->isVisible($this->selector($selector), 1000)) {
                $this->click($this->selector($selector));

                return;
            }
        }
    }
}
