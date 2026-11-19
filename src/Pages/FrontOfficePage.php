<?php

namespace PrestaFlow\Library\Pages;

use Exception;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page as DomPage;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Resolvers\Translations;
use PrestaFlow\Library\Resolvers\Urls;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Traits\Locale;

class FrontOfficePage extends CommonPage
{
    use Translations;
    use Urls;
    use Locale;

    // Redeclared here, not only through the trait: before PHP 8.3 a trait's
    // static property used again in a child class stays shared with the
    // parent (CommonPage), so the back office and the front office would
    // overwrite each other's language (BO_LOCALE).
    protected static $localeInit = null;
    protected static string $locale = 'en';

    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->initVersion(patchVersion: $patchVersion, globals: $globals);
        $this->globals = $globals;
        $this->customs = array_merge($this->customs, $customs);
        $this->initLocale(locale: $locale);

        $selectors = [
            'pageTitle' => 'h1',
            'maintenanceBlock' => '#content.page-maintenance',
            'desktopLogo' => '#_desktop_logo',
            'userInfoLink' => '#_desktop_user_info',
            'accountLink' => '#_desktop_user_info .user-info a[href*=\'/my-account\']',
        ];

        $this->selectors = $this->getSelectors(selectors: $selectors);

        $this->messages = $this->getMessages();

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals, customs: $customs);
    }

    public function goToPage($page = null, $params = null)
    {
        if ($page === null) {
            $page = $this;
        }

        $url = $this->getPageURL($page, $params);

        // close()+createPage() peut floter au démarrage à froid : l'énumération des
        // targets pendant qu'une page se ferme/crée lève « Call to a member function
        // getTargetInfo() on null ». On réessaie la séquence quelques fois.
        $attempts = 3;
        for ($try = 1; ; $try++) {
            try {
                TestsSuite::recreatePageIfContextChanged('FO');
                $this->getPage()->navigate($url)->waitForNavigation(DomPage::DOM_CONTENT_LOADED);
                break;
            } catch (\Throwable $e) {
                if ($try >= $attempts) {
                    throw $e;
                }
                usleep(800000);
            }
        }

        // Check anti-« [Debug] This page has moved » : évalue une expression JS
        // au lieu de faire un snapshot DOM complet via getTextContent('body') —
        // qui coûtait ~2s par navigation sur une home PS chargée.
        try {
            $hasDebugMoved = $this->getPage()
                ->evaluate("!!(document.body && document.body.innerText && document.body.innerText.indexOf('[Debug] This page has moved') !== -1)")
                ->getReturnValue();
            if ($hasDebugMoved) {
                Expect::setWarning('debug-mode');
                $this->click('a');
                $this->waitForPageLoaded();
            }
        } catch (OperationTimedOut | Exception $e) {
            Expect::setWarning('debug-mode');
            $this->click('a');
            $this->waitForPageLoaded();
        }
    }

    public function getPageURL($page, $params = null): string
    {
        $url = $this->getGlobals()['FO']['URL'];
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }
        $useIsoCode = $this->getGlobals()['PREFIX_LOCALE'] ?? false;
        if ($useIsoCode) {
            $url .= $this->getLocale() . '/';
        }
        if (is_string($page)) {
            $pages = ['home', 'index', 'login', 'authentification', 'prices-drop', 'category', 'product'];
            $pageUrl = $this->url($page);
            if ($pageUrl !== '' && $pageUrl !== null && !in_array($pageUrl, $pages)) {
                $url .= $pageUrl;
            } else {
                $url .= match ($page) {
                    'home', 'index' => '',
                    'login', 'authentification' => 'login',
                    'prices-drop' => 'prices-drop',
                    'category' => '{index}-category',
                    'product' => '{index}-product.html',
                    default => ''
                };
            }
        } else if (is_object($page)) {
            $pageUrl = $this->url($page->url);
            if ($pageUrl !== '' && $pageUrl !== null) {
                $url .= $pageUrl;
            } else {
                $url .= $page->url;
            }
        }

        // A scalar $params is the common call shape — goToPage('category', 3),
        // goToPage('product', $productId) — and means the single placeholder the
        // default templates carry: {index}. Substitution used to be gated on
        // is_array(), so every scalar call left '{index}' in the URL and it got
        // percent-encoded into %7Bindex%7D. Normalise to the array form first.
        if (is_scalar($params) && $params !== '') {
            $params = ['index' => $params];
        }

        if (is_array($params) && count($params) > 0) {
            foreach ($params as $key => $value) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        }

        return $url;
    }

    public function goToUrl(string $url)
    {
        // Override intentionnel de CommonPage::goToUrl (simple navigate) : recrée
        // la page pour partir sur une session FrontOffice propre (utile pour ne
        // pas hériter des cookies BackOffice) avant d'atteindre une URL absolue.
        // recreatePage() se charge de réappliquer les en-têtes persistants.
        TestsSuite::recreatePageIfContextChanged('FO');
        $this->getPage()->navigate($url)->waitForNavigation(DomPage::DOM_CONTENT_LOADED);
    }

    public function goToUrlInPlace(string $url)
    {
        // Navigation sans recréation de page : conserve la session (cookies, JS
        // state) de la page courante. À utiliser pour un scénario multi-étapes
        // qui reste en FrontOffice (ex. home → fiche produit dont l'URL est lue
        // depuis la home). Préférer goToUrl() quand on veut repartir d'une
        // session propre (ex. après du BackOffice).
        parent::goToUrl($url);
    }

    public function getTitle()
    {
        return $this->getTextContent($this->selector('pageTitle'));
    }

    public function getPageTitle()
    {
        return $this->getTitle();
    }
}
