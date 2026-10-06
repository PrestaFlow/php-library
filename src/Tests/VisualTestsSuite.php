<?php

namespace PrestaFlow\Library\Tests;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Utils\Env;
use PrestaFlow\Library\Visual\VisualDevices;

/**
 * Suite de régression visuelle pilotée par données. Les sous-classes ne
 * déclarent que $devices, $locales et $checkpoints (littéraux, édités par l'app).
 * Une exécution couvre UNE combinaison device × locale (globals DEVICE / LOCALE,
 * ou env PRESTAFLOW_DEVICE / PRESTAFLOW_LOCALE en CLI).
 *
 * Zone 'fo' (défaut) : chaque checkpoint vise un chemin du front-office
 * (`path` / `paths`). Zone 'bo' : connexion automatique au back-office ; les
 * checkpoints `auth => false` (page de connexion) passent d'abord, les autres
 * sont résolus par le menu latéral (`menu` : sélecteurs séparés par des
 * virgules, null = racine du BO). `hide` (display:none pendant la capture)
 * vaut pour les deux zones. `path` / `paths` sont ignorés en 'bo', `menu` /
 * `auth` en 'fo' ; en 'bo', `auth => false` avec un `menu` est refusé.
 */
abstract class VisualTestsSuite extends TestsSuite
{
    protected array $devices = ['desktop'];
    protected array $locales = [];
    protected array $checkpoints = [];

    /**
     * Préfixe des noms de checkpoint (`{scope}.{name}`) : les noms ne sont uniques
     * qu'au sein d'une suite, le scope évite que deux suites avec un checkpoint
     * `header` écrasent mutuellement captures et références. Vide = kebab-case du
     * nom court de la classe (NouvelleScene → nouvelle-scene). Doit matcher [a-z0-9-]+.
     */
    protected string $visualScope = '';

    /**
     * Zone capturée : 'fo' (front-office, chemins d'URL) ou 'bo' (back-office :
     * connexion automatique, checkpoints résolus par le menu latéral, car toute
     * URL d'admin porte un jeton).
     */
    protected string $area = 'fo';

    /** Gel des transitions CSS pendant la capture ; null = actif en 'bo' seulement (références 'fo' inchangées). */
    protected ?bool $freezeTransitions = null;

    /**
     * État de la connexion BO (une seule tentative par exécution). Protégés et
     * non privés : run() lie les étapes à la sous-classe concrète.
     */
    protected ?string $boLoginError = null;
    protected bool $boLoggedIn = false;

    /** Avertissement posé sur le test quand waitForStable() expire (la capture est prise quand même). */
    /** Budget par défaut de pixels changés (cf. CommonPage::visualCheckpoint). */
    public const DEFAULT_MAX_DIFF_PIXELS = \PrestaFlow\Library\Pages\CommonPage::DEFAULT_MAX_DIFF_PIXELS;

    public const UNSTABLE_WARNING = 'Page non stabilisée après 5 s (images/polices encore en chargement) : capture prise quand même.';

    /**
     * URL sur laquelle le dernier checkpoint exécuté (et réussi) a capturé :
     * le suivant sur la même URL (header / footer / home = '') ne recharge pas
     * la page. Remis à null dès qu'un checkpoint lève.
     */
    protected ?string $lastVisualUrl = null;

    /**
     * Les checkpoints sont des captures indépendantes : un écart visuel ne doit
     * pas faire sauter les suivants (TestsSuite saute tout après un échec).
     */
    protected $skipWhenFailed = false;

    /**
     * Dernier override posé par applyDevicePreset(). Tant que les overrides
     * courants lui sont identiques, ils « nous appartiennent » et peuvent être
     * remplacés (suite visuelle suivante, autre device). Si l'app a posé les
     * siens entre-temps, ils diffèrent → on n'y touche jamais.
     */
    private static ?array $appliedPreset = null;

    public function __construct(bool $loadGlobals = true, bool $getBrowser = true)
    {
        // CLI : preset du device (env ou 1er déclaré), sauf si l'app a posé ses options.
        if ($getBrowser) {
            static::applyDevicePreset($this->deviceFromEnv() ?? ($this->devices[0] ?? 'desktop'));
        }

        parent::__construct(loadGlobals: $loadGlobals, getBrowser: $getBrowser);

        // loadGlobals() pose toujours LOCALE (défaut 'en') : sans PRESTAFLOW_LOCALE
        // explicite, la locale par défaut d'une suite visuelle est sa 1re déclarée.
        if ($loadGlobals && $this->locales !== [] && (string) Env::get('PRESTAFLOW_LOCALE', '') === '') {
            $this->setGlobals(['LOCALE' => $this->locales[0]]);
        }
    }

    /**
     * Applique le preset du device si les options navigateur ne sont pas déjà
     * pilotées par l'app. Retourne true si le navigateur partagé a été réinitialisé
     * (options différentes → un navigateur keepAlive réutilisé garderait sinon
     * l'ancienne taille / UA).
     */
    protected static function applyDevicePreset(string $device): bool
    {
        $overrides = self::$browserOptionOverrides;
        if ($overrides !== null && $overrides !== self::$appliedPreset) {
            return false; // options posées par l'app : intouchables
        }

        $preset = VisualDevices::get($device);
        $wanted = ['windowSize' => [$preset['width'], $preset['height']], 'userAgent' => $preset['userAgent']];
        if (TestsSuite::browserOptions() === $wanted) {
            return false;
        }

        self::useBrowserOptions($preset['width'], $preset['height'], $preset['userAgent']);
        self::$appliedPreset = self::$browserOptionOverrides;
        TestsSuite::resetBrowser();

        return true;
    }

    /**
     * Remplace la définition littérale de la classe par des données fraîchement
     * parsées : un worker PHP long-vivant qui a déjà chargé la classe ne voit pas
     * les éditions ultérieures du fichier. À appeler avant init().
     */
    public function useDefinition(array $devices, array $locales, array $checkpoints): static
    {
        $this->devices = array_values($devices);
        $this->locales = array_values($locales);
        $this->checkpoints = array_values($checkpoints);

        return $this;
    }

    public function visualScope(): string
    {
        if ($this->visualScope !== '') {
            if (preg_match('/^[a-z0-9-]+$/', $this->visualScope) !== 1) {
                throw new \InvalidArgumentException(sprintf('%s : visualScope « %s » doit matcher [a-z0-9-]+', static::class, $this->visualScope));
            }

            return $this->visualScope;
        }

        $class = static::class;
        if (str_contains($class, '@anonymous')) {
            $class = get_parent_class($this) ?: 'Visual';
        }

        return self::slugify(substr(strrchr('\\'.$class, '\\'), 1)) ?: 'visual';
    }

    /** Kebab-case filename-safe : NouvelleScene → nouvelle-scene, ProdVisual2 → prod-visual2. */
    public static function slugify(string $name): string
    {
        $kebab = preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $name);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $kebab)), '-');
    }

    public function devices(): array { return $this->devices; }
    public function locales(): array { return $this->locales; }
    public function checkpoints(): array { return array_map([self::class, 'normalize'], $this->checkpoints); }
    public function area(): string { return $this->area; }
    public function freezesTransitions(): bool { return $this->freezeTransitions ?? ($this->area === 'bo'); }

    /** Ordre d'exécution : en 'bo', les captures sans connexion (page de connexion) passent avant la connexion. */
    public function orderedCheckpoints(): array
    {
        $checkpoints = $this->checkpoints();
        if ($this->area !== 'bo') {
            return $checkpoints;
        }

        return [
            ...array_values(array_filter($checkpoints, static fn (array $cp) => $cp['auth'] === false)),
            ...array_values(array_filter($checkpoints, static fn (array $cp) => $cp['auth'] !== false)),
        ];
    }

    /**
     * Valeurs par défaut d'un checkpoint. Règle de passage : budget de pixels
     * changés `maxDiffPixels` (défaut 100). `threshold` (ratio 0.5–1) n'est
     * qu'un mode historique : présent SANS `maxDiffPixels` dans la définition,
     * il s'applique seul (maxDiffPixels normalisé à null) ; sinon il est null.
     */
    public static function normalize(array $cp): array
    {
        $hasThreshold = array_key_exists('threshold', $cp) && $cp['threshold'] !== null;
        $hasBudget = array_key_exists('maxDiffPixels', $cp) && $cp['maxDiffPixels'] !== null;

        $cp = array_merge([
            'path' => '', 'paths' => [], 'zone' => 'viewport', 'selector' => null,
            'waitFor' => null, 'scrollBelow' => null, 'threshold' => null,
            'maxDiffPixels' => self::DEFAULT_MAX_DIFF_PIXELS,
            'excludeDevices' => [], 'masks' => [],
            'menu' => null, 'auth' => true, 'hide' => [],
        ], $cp);
        $cp['auth'] = (bool) $cp['auth'];
        $cp['hide'] = array_values(array_filter(array_map('trim', array_filter((array) $cp['hide'], 'is_string')), static fn (string $s) => $s !== ''));
        $cp['threshold'] = $hasThreshold ? max(0.5, min(1.0, (float) $cp['threshold'])) : null;
        $cp['maxDiffPixels'] = $hasBudget
            ? max(0, (int) $cp['maxDiffPixels'])
            : ($hasThreshold ? null : self::DEFAULT_MAX_DIFF_PIXELS);

        return $cp;
    }

    public function currentDevice(): string
    {
        try {
            $globals = $this->getGlobals();
        } catch (\Throwable) {
            $globals = [];
        }

        return $globals['DEVICE'] ?? $this->deviceFromEnv() ?? ($this->devices[0] ?? 'desktop');
    }

    public function currentLocale(): string
    {
        return (string) ($this->getGlobals()['LOCALE'] ?? ($this->locales[0] ?? 'fr'));
    }

    public function resolvePath(array $cp, string $locale): ?string
    {
        $override = $cp['paths'][$locale] ?? null;
        if ($override === false) {
            return null;
        }

        return is_string($override) && $override !== '' ? $override : (string) ($cp['path'] ?? '');
    }

    public function resolveUrl(string $path, string $locale): string
    {
        $globals = $this->getGlobals();
        $base = rtrim((string) ($globals['FO']['URL'] ?? ''), '/').'/';
        $prefix = !empty($globals['PREFIX_LOCALE']) ? $locale.'/' : '';

        return $base.$prefix.ltrim($path, '/');
    }

    public function init()
    {
        parent::init();
        if (!in_array($this->area, ['fo', 'bo'], true)) {
            throw new \InvalidArgumentException(sprintf('%s : area « %s » inconnue (fo, bo)', static::class, $this->area));
        }
        $this->importVisualPage();
        $backOffice = $this->area === 'bo';
        $page = $this->pages[$backOffice ? 'backOfficePage' : 'frontOfficePage'] ?? null;
        $login = $this->pages['backOfficeLoginPage'] ?? null;
        if ($backOffice) {
            foreach ($this->checkpoints() as $cp) {
                if ($cp['auth'] === false && $cp['menu'] !== null) {
                    throw new \InvalidArgumentException(sprintf('%s : checkpoint « %s » : auth => false capture la page de connexion, menu interdit', static::class, (string) ($cp['name'] ?? '')));
                }
            }
        }
        $device = $this->currentDevice();
        $locale = $this->currentLocale();

        if (!in_array($device, $this->devices, true)) {
            throw new \InvalidArgumentException(sprintf('%s : device « %s » non déclaré (%s)', static::class, $device, implode(', ', $this->devices)));
        }
        if ($this->locales !== [] && !in_array($locale, $this->locales, true)) {
            throw new \InvalidArgumentException(sprintf('%s : locale « %s » non déclarée (%s)', static::class, $locale, implode(', ', $this->locales)));
        }

        $this->describe($this->title ?: substr(strrchr('\\'.static::class, '\\'), 1));

        $scope = $this->visualScope();
        $this->lastVisualUrl = null;
        $this->boLoggedIn = false;
        $this->boLoginError = null;

        $checkpoints = $this->orderedCheckpoints();
        $only = self::onlyFromEnv();
        if ($only !== []) {
            $checkpoints = array_values(array_filter($checkpoints, static fn (array $cp) => in_array((string) ($cp['name'] ?? ''), $only, true)));
            if ($checkpoints === []) {
                $message = sprintf(
                    'PRESTAFLOW_VISUAL_ONLY : aucun checkpoint ne correspond (%s) ; checkpoints déclarés : %s',
                    implode(', ', $only),
                    implode(', ', array_map(static fn (array $cp) => (string) ($cp['name'] ?? ''), $this->checkpoints())) ?: 'aucun'
                );
                $this->it('capture visuelle : filtre PRESTAFLOW_VISUAL_ONLY', function () use ($message) {
                    throw new \InvalidArgumentException($message);
                });

                return $this;
            }
        }

        foreach ($checkpoints as $cp) {
            $title = 'capture visuelle : '.$cp['name'];

            if ($backOffice) {
                // path / paths ignorés : seul excludeDevices saute un checkpoint
                if (in_array($device, $cp['excludeDevices'], true)) {
                    $this->skip($title, function () {});
                    continue;
                }

                // Les jetons changent les URL : « même page » = même couple (auth, menu).
                // Les cookies sont vidés à la construction de la suite (TestsSuite,
                // Network.clearBrowserCookies) : les checkpoints non connectés, joués
                // d'abord, partent bien sans session (racine du BO = page de connexion).
                $target = ($cp['auth'] ? 'in' : 'out').'|'.($cp['menu'] ?? '');
                $this->it($title, function () use ($page, $login, $cp, $target, $scope) {
                    if (Expect::$latestWarning === self::UNSTABLE_WARNING) {
                        Expect::setWarning('');
                    }

                    $sameTarget = $this->lastVisualUrl === $target;
                    $this->lastVisualUrl = null; // invalidé tant que ce checkpoint n'a pas abouti

                    if ($cp['auth']) {
                        $this->ensureBackOfficeLogin($login);
                    }
                    if (!$sameTarget) {
                        $page->goToPage('index');
                        if ($cp['menu'] !== null) {
                            $page->goToMenu($cp['menu']);
                        }
                    }
                    if (!$cp['auth'] && !$this->loginFormPresent($page, $login)) {
                        // pas de référence enregistrée sur le tableau de bord à la place
                        throw new \RuntimeException('Session back-office déjà ouverte : la page de connexion ne peut pas être capturée');
                    }
                    $this->captureCheckpoint($page, $cp, $scope, $sameTarget);

                    $this->lastVisualUrl = $target;
                });
                continue;
            }

            $path = $this->resolvePath($cp, $locale);

            if ($path === null || in_array($device, $cp['excludeDevices'], true)) {
                $this->skip($title, function () {});
                continue;
            }

            $url = $this->resolveUrl($path, $locale);
            $this->it($title, function () use ($page, $cp, $url, $scope) {
                // Un avertissement de stabilisation ne concerne que son propre checkpoint
                // (Expect::$latestWarning est global et persistant).
                if (Expect::$latestWarning === self::UNSTABLE_WARNING) {
                    Expect::setWarning('');
                }

                $sameUrl = $this->lastVisualUrl === $url;
                $this->lastVisualUrl = null; // invalidé tant que ce checkpoint n'a pas abouti

                if (!$sameUrl) {
                    $page->goToUrl($url);
                }
                $this->captureCheckpoint($page, $cp, $scope, $sameUrl);

                $this->lastVisualUrl = $url;
            });
        }

        return $this;
    }

    /**
     * Déroulé commun aux deux zones, une fois sur la page : stabilité, attente,
     * scroll, capture. Protégé (et non privé) : appelé depuis les étapes, que
     * run() lie à la sous-classe concrète.
     */
    protected function captureCheckpoint(object $page, array $cp, string $scope, bool $samePage): void
    {
        if ($page->waitForStable() === false) {
            Expect::setWarning(self::UNSTABLE_WARNING);
        }
        if ($cp['waitFor']) {
            $page->waitVisible($cp['waitFor']);
        }
        if ($cp['scrollBelow'] && $cp['zone'] === 'viewport') {
            $page->scrollBelow($cp['scrollBelow']); // remet d'abord le scroll en haut
        } elseif ($samePage) {
            $page->scrollToTop(); // ne pas hériter du scroll du checkpoint précédent
        }
        $page->visualCheckpoint(
            $scope.'.'.$cp['name'],
            $cp['zone'] === 'element' ? $cp['selector'] : null,
            $cp['threshold'],
            $cp['zone'] === 'full',
            'auto',
            $cp['masks'],
            $cp['maxDiffPixels'],
            $cp['hide'],
            $this->freezesTransitions(),
        );
    }

    /** Connexion unique au back-office avant le premier checkpoint connecté ; une erreur est rejouée sans nouvel essai. */
    protected function ensureBackOfficeLogin(?object $login): void
    {
        if ($this->boLoggedIn) {
            return;
        }
        if ($this->boLoginError === null) {
            try {
                if ($login === null) {
                    throw new \RuntimeException('page BackOffice\\Login absente');
                }
                $login->goToPage('index');
                if (!$this->loginFormPresent($login, $login)) {
                    $this->boLoggedIn = true; // session déjà ouverte : racine = tableau de bord

                    return;
                }
                $login->login(); // identifiants des globals BO_EMAIL / BO_PASSWD
                if (!$login->isLoggedIn()) {
                    // 9.2 : .alert-text, sans le bouton de fermeture (« close ») ; 1.7 et 8 : l'alerte entière.
                    $error = $this->readNow($login, sprintf('(function(){var e=document.querySelector(%s);if(!e)return "";return (e.querySelector(".alert-text")||e).textContent;})()', json_encode((string) $login->getSelector('alertDangerDiv'))));
                    $error = trim(preg_replace('/\s+/', ' ', is_string($error) ? $error : ''));
                    throw new \RuntimeException('identifiants refusés ou page inattendue'.($error !== '' ? ' : '.$error : $this->whereIs($login)));
                }
                $this->boLoggedIn = true;

                return;
            } catch (\Throwable $e) {
                $this->boLoginError = $e->getMessage();
            }
        }

        throw new \RuntimeException('Connexion au back-office impossible : '.$this->boLoginError);
    }

    /**
     * Sans alerte, la connexion n'a pas été refusée : l'onglet est ailleurs. On
     * donne le chemin et le contrôleur, jamais le jeton de l'URL.
     */
    protected function whereIs(object $page): string
    {
        $where = $this->readNow($page, '(function(){var c=new URLSearchParams(location.search).get("controller");return location.pathname+(c?"?controller="+c:"");})()');

        return is_string($where) && $where !== '' ? ' (page : '.$where.')' : '';
    }

    /** Formulaire de connexion affiché sur la page courante (lu sans attendre, sélecteur de la page Login). */
    protected function loginFormPresent(object $page, ?object $login): bool
    {
        $selector = $login !== null ? (string) $login->getSelector('emailInput') : '#email';

        return (bool) $this->readNow($page, sprintf('!!document.querySelector(%s)', json_encode($selector)));
    }

    /** Évalue une expression JS dans l'onglet de la page, sans attente. */
    protected function readNow(object $page, string $js): mixed
    {
        return $page->getPage()->evaluate($js)->getReturnValue();
    }

    /** Surchargé en test unitaire (pas de navigateur). */
    protected function importVisualPage(): void
    {
        if ($this->area === 'bo') {
            $this->importPage('BackOffice');
            $this->importPage('BackOffice\Login');

            return;
        }
        $this->importPage('FrontOffice');
    }

    /**
     * Noms de checkpoints (non préfixés du scope) retenus via
     * PRESTAFLOW_VISUAL_ONLY=home,footer ; [] = pas de filtre.
     *
     * @return list<string>
     */
    public static function onlyFromEnv(): array
    {
        $raw = Env::get('PRESTAFLOW_VISUAL_ONLY');
        if (!is_string($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)), static fn (string $n) => $n !== '')));
    }

    private function deviceFromEnv(): ?string
    {
        $env = Env::get('PRESTAFLOW_DEVICE');

        return is_string($env) && $env !== '' ? $env : null;
    }
}
