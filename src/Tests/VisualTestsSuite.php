<?php

namespace PrestaFlow\Library\Tests;

use HeadlessChromium\Exception\CommunicationException\ResponseHasError;
use HeadlessChromium\Exception\OperationTimedOut;
use PrestaFlow\Library\Exceptions\BackOfficeTimeoutException;
use PrestaFlow\Library\Exceptions\TimeoutException;
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
 *
 * Sélecteur visuel de l'app : openBackOfficeCheckpoint() ouvre la page d'un
 * checkpoint BO avec ce même code (connexion, menu), sous des plafonds courts ;
 * closeBackOfficeSession() referme la session employé.
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

    /** Cause de l'échec de connexion, gardée comme `previous` de l'erreur relevée (délai ≠ refus). */
    protected ?\Throwable $boLoginCause = null;

    /** Chemin et contrôleur de la page courante : jamais le jeton de l'URL. */
    private const LOCATION_JS = '(function(){var c=new URLSearchParams(location.search).get("controller");return location.pathname+(c?"?controller="+c:"");})()';

    /**
     * Plafond fixe du rechargement attendu par Login\Page::login() avant l'issue
     * de la connexion (CommonPage::waitForPageReload(), 10 s au plus). N'entre
     * plus dans le calcul du budget : l'issue attend jusqu'à son échéance
     * (Login\Page::$loginOutcomeDeadline), si bien qu'un rechargement court lui
     * laisse le reste. Il borne le dépassement du pire cas de
     * openBackOfficeCheckpoint().
     */
    private const PAGE_RELOAD_MS = 10000;

    /**
     * Constat de la session après l'issue de la connexion
     * (Login\Page::isLoggedIn(), lien de déconnexion attendu 5 s au plus) :
     * réservé lui aussi dans le budget de openBackOfficeCheckpoint().
     */
    private const LOGIN_CHECK_MS = 5000;

    /** Plus petit plafond d'étape accordé par openBackOfficeCheckpoint(). */
    private const MIN_STEP_MS = 1000;

    /**
     * Appelé par ensureBackOfficeLogin() juste avant l'envoi du formulaire :
     * openBackOfficeCheckpoint() y pose l'échéance de l'issue
     * (Login\Page::$loginOutcomeDeadline) selon le budget (null pendant un run).
     */
    private ?\Closure $boBeforeLoginSubmit = null;

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
        $this->boLoginCause = null;

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
                if ($this->boBeforeLoginSubmit !== null) {
                    ($this->boBeforeLoginSubmit)($login);
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
                $this->boLoginCause = $e;
            }
        }

        throw new \RuntimeException('Connexion au back-office impossible : '.$this->boLoginError, 0, $this->boLoginCause);
    }

    /**
     * Ouvre, dans le navigateur courant (TestsSuite::getPage()), la page d'un
     * checkpoint BO avec le code du run : connexion (ensureBackOfficeLogin()),
     * racine du BO, puis entrée du menu (goToMenu()) ; `auth => false` : racine
     * du BO sans session (page de connexion). Pour le sélecteur visuel de l'app,
     * qui capture ensuite la page avec PageSnapshot::captureCurrent().
     *
     * Une ouverture par instance de suite : l'état de connexion (boLoggedIn,
     * erreur, cause) est remis à zéro à chaque appel ; une session encore ouverte
     * dans le navigateur est reprise telle quelle (appeler closeBackOfficeSession()
     * avant un checkpoint auth: false).
     *
     * Budget : échéance globale = début + $loginTimeoutMs + $menuTimeoutMs
     * (40 s par défaut). Chaque étape plafonnée reçoit min(son plafond, reste
     * avant l'échéance), et n'est pas lancée s'il reste moins de 1 s
     * (BackOfficeTimeoutException) :
     * - navigation de la page de connexion, du tableau de bord, du menu :
     *   min($menuTimeoutMs, reste) (CommonPage::$navigationTimeout) ;
     * - envoi du formulaire : seulement s'il reste au moins 6 s
     *   (LOGIN_CHECK_MS + 1 s). Login\Page::login() attend alors le rechargement
     *   (CommonPage::waitForPageReload(), 10 s au plus), puis l'issue de la
     *   connexion jusqu'à l'échéance Login\Page::$loginOutcomeDeadline =
     *   min(début + $loginTimeoutMs, échéance globale) - 5 s (1 s au moins après
     *   le rechargement), et Login\Page::isLoggedIn() jusqu'à 5 s après l'issue.
     *   Un rechargement court laisse donc le reste du plafond de connexion à
     *   l'issue. Si $loginTimeoutMs + $menuTimeoutMs < 6 s, le formulaire n'est
     *   jamais envoyé.
     * Pire cas : échéance + 10 s (formulaire envoyé à 6 s de l'échéance,
     * rechargement au plafond PAGE_RELOAD_MS, issue 1 s, constat 5 s), plus les
     * lectures JS et le remplissage du formulaire (≤ 5 s chacun), non plafonnés
     * par ce budget.
     * La capture (PageSnapshot::captureCurrent()) et la déconnexion
     * (closeBackOfficeSession()) sont hors de ce budget. Les plafonds d'avant
     * l'appel (ceux du run) sont rétablis avant de rendre la main, même en cas
     * d'erreur.
     *
     * Les messages relayés ne portent ni jeton (`token`, `_token`) ni
     * identifiants d'URL (`user:pass@`) ; la cause d'origine reste en `previous`.
     *
     * @return string chemin et contrôleur de la page ouverte, sans jeton ('' si illisible)
     *
     * @throws \LogicException            hors zone 'bo' ; \InvalidArgumentException pour auth => false avec un menu
     * @throws BackOfficeTimeoutException délai imparti dépassé (message lisible)
     * @throws \RuntimeException          erreur du run, message déjà formulé (identifiants refusés, page inattendue, menu introuvable)
     */
    public function openBackOfficeCheckpoint(array $checkpoint, int $loginTimeoutMs = 25000, int $menuTimeoutMs = 15000): string
    {
        if ($this->area !== 'bo') {
            throw new \LogicException(sprintf('%s : openBackOfficeCheckpoint() exige la zone « bo » (zone « %s »)', static::class, $this->area));
        }
        $cp = self::normalize($checkpoint);
        if ($cp['auth'] === false && $cp['menu'] !== null) {
            throw new \InvalidArgumentException(sprintf('%s : checkpoint « %s » : auth => false capture la page de connexion, menu interdit', static::class, (string) ($cp['name'] ?? '')));
        }

        // init() n'est pas appelé (il enregistrerait les étapes du run) : pages importées ici.
        if (!isset($this->pages['backOfficePage'])) {
            $this->importVisualPage();
        }
        $page = $this->pages['backOfficePage'] ?? null;
        $login = $this->pages['backOfficeLoginPage'] ?? null;
        if ($page === null) {
            throw new \RuntimeException('page BackOffice absente');
        }

        // Une ouverture = une tentative de connexion, comme une exécution (init()).
        $this->boLoggedIn = false;
        $this->boLoginError = null;
        $this->boLoginCause = null;
        $this->lastVisualUrl = null;

        $budgetMs = $loginTimeoutMs + $menuTimeoutMs;
        $start = $this->nowMs();
        $deadline = $start + $budgetMs;
        // Reste avant l'échéance, plafonné à $stepMs ; délai si moins de $neededMs.
        $left = function (int $stepMs, int $neededMs = self::MIN_STEP_MS) use ($deadline, $budgetMs): int {
            $left = $deadline - $this->nowMs();
            if ($left < $neededMs) {
                throw new BackOfficeTimeoutException(sprintf("Le back-office n'a pas répondu dans le délai imparti (%d s au total).", intdiv($budgetMs, 1000)));
            }

            return min($stepMs, $left);
        };

        $restore = $this->capBackOfficeWaits($page, $login);
        try {
            if ($cp['auth']) {
                if ($login !== null) {
                    $login->navigationTimeout = $left($menuTimeoutMs);
                    $this->boBeforeLoginSubmit = static function (object $login) use ($left, $start, $deadline, $loginTimeoutMs): void {
                        // Délai s'il ne reste pas de quoi attendre l'issue (1 s) puis la constater (5 s).
                        $left(PHP_INT_MAX, self::LOGIN_CHECK_MS + self::MIN_STEP_MS);
                        $login->loginOutcomeDeadline = min($start + $loginTimeoutMs, $deadline) - self::LOGIN_CHECK_MS;
                    };
                }
                try {
                    $this->ensureBackOfficeLogin($login);
                } catch (\RuntimeException $e) {
                    $deadlineHit = self::findInChain($e, BackOfficeTimeoutException::class);
                    if ($deadlineHit !== null) {
                        throw $deadlineHit;
                    }
                    if ($login !== null && ($login->loginOutcomeSeen ?? null) === false) {
                        throw new BackOfficeTimeoutException(sprintf("Le back-office n'a pas répondu à la connexion dans le délai imparti (%d s).", intdiv($loginTimeoutMs, 1000)), 0, $e);
                    }
                    throw $e;
                }
            }
            $page->navigationTimeout = $left($menuTimeoutMs);
            $page->goToPage('index');
            if ($cp['menu'] !== null) {
                $page->navigationTimeout = $left($menuTimeoutMs);
                $page->goToMenu($cp['menu']);
            }
            if (!$cp['auth'] && !$this->loginFormPresent($page, $login)) {
                throw new \RuntimeException('Session back-office déjà ouverte : la page de connexion ne peut pas être capturée');
            }

            return $this->locationOf($page);
        } catch (BackOfficeTimeoutException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if (self::isTimeout($e)) {
                throw new BackOfficeTimeoutException(sprintf("Le back-office n'a pas répondu dans le délai imparti (chargement d'une page, %d s au plus).", intdiv($menuTimeoutMs, 1000)), 0, $e);
            }
            if (!$e instanceof \Exception) {
                throw $e; // un \Error (défaut de code) n'est pas masqué
            }
            $message = self::redactUrls($e->getMessage());
            if ($e instanceof \RuntimeException && $message === $e->getMessage()) {
                throw $e; // erreur du run telle quelle
            }
            // exceptions chrome-php (ResponseHasError…) ou message portant une URL
            throw new \RuntimeException($message, 0, $e);
        } finally {
            $this->boBeforeLoginSubmit = null;
            $restore();
        }
    }

    /**
     * Déconnexion par le lien #header_logout de la page courante
     * (BackOffice\Login\Page::logout()) si une session est ouverte. Ne lève
     * jamais : un échec de déconnexion ne doit pas faire échouer la capture
     * (le navigateur de l'app est fermé juste après). $timeoutMs borne la
     * navigation de déconnexion.
     *
     * Seule une connexion constatée (boLoggedIn) est fermée : une connexion
     * validée par le serveur après le délai imparti (openBackOfficeCheckpoint()
     * a levé BackOfficeTimeoutException) reste ouverte, et la session serveur
     * expirera d'elle-même.
     */
    public function closeBackOfficeSession(int $timeoutMs = 5000): void
    {
        if (!$this->boLoggedIn) {
            return;
        }
        $this->boLoggedIn = false;
        $login = $this->pages['backOfficeLoginPage'] ?? null;
        if ($login === null) {
            return;
        }

        $before = $login->navigationTimeout ?? null;
        try {
            $login->navigationTimeout = $timeoutMs;
            $login->logout();
        } catch (\Throwable) {
            // session laissée ouverte : rien d'autre à faire
        } finally {
            try {
                $login->navigationTimeout = $before;
            } catch (\Throwable) {
            }
        }
    }

    /** Horloge monotone en millisecondes (surchargée en test unitaire). */
    protected function nowMs(): int
    {
        return intdiv(hrtime(true), 1_000_000);
    }

    /**
     * Note les plafonds d'avant openBackOfficeCheckpoint() (ceux du run :
     * navigations, échéance de l'issue de connexion) et renvoie de quoi les
     * rétablir. Les plafonds sont posés étape par étape.
     */
    private function capBackOfficeWaits(object $page, ?object $login): \Closure
    {
        $pageBefore = $page->navigationTimeout ?? null;
        $loginBefore = $login?->navigationTimeout ?? null;
        $deadlineBefore = $login?->loginOutcomeDeadline ?? null;
        if ($login !== null) {
            $login->loginOutcomeSeen = null;
        }

        return static function () use ($page, $login, $pageBefore, $loginBefore, $deadlineBefore): void {
            $page->navigationTimeout = $pageBefore;
            if ($login !== null) {
                $login->navigationTimeout = $loginBefore;
                $login->loginOutcomeDeadline = $deadlineBefore;
            }
        };
    }

    /**
     * Masque jetons (`token=`, `_token=`, encodés ou en entité HTML) et identifiants d'URL
     * (`scheme://user:pass@`) d'un message relayé à l'utilisateur.
     */
    private static function redactUrls(string $message): string
    {
        // Jeton en clair, encodé (%26token%3D, %3F_token%3D) ou en entité HTML (&amp;token=).
        $message = preg_replace('~((?:[?&;]|%26|%3F)_?token(?:=|%3D))[^&"\'\s%<>]+~i', '$1…', $message) ?? $message;

        // Identifiants avant l'hôte seulement : un « @ » de requête (?email=a@b.com) reste.
        return preg_replace('~\b([a-z][a-z0-9+.\-]*://)[^\s/?#"\'@]+@~i', '$1…@', $message) ?? $message;
    }

    /**
     * @template T of \Throwable
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private static function findInChain(\Throwable $e, string $class): ?\Throwable
    {
        for ($t = $e; $t !== null; $t = $t->getPrevious()) {
            if ($t instanceof $class) {
                return $t;
            }
        }

        return null;
    }

    /**
     * Délai de chrome-php ou de la lib quelque part dans la chaîne des causes,
     * y compris un chargement de page en échec net::ERR_TIMED_OUT.
     */
    private static function isTimeout(\Throwable $e): bool
    {
        for ($t = $e; $t !== null; $t = $t->getPrevious()) {
            if ($t instanceof OperationTimedOut || $t instanceof TimeoutException) {
                return true;
            }
            if ($t instanceof ResponseHasError && str_contains($t->getMessage(), 'TIMED_OUT')) {
                return true;
            }
        }

        return false;
    }

    /** Chemin et contrôleur de la page courante, sans jeton ; '' si illisible. */
    protected function locationOf(object $page): string
    {
        try {
            $where = $this->readNow($page, self::LOCATION_JS);
        } catch (\Throwable) {
            return '';
        }

        return is_string($where) ? $where : '';
    }

    /**
     * Sans alerte, la connexion n'a pas été refusée : l'onglet est ailleurs. On
     * donne le chemin et le contrôleur, jamais le jeton de l'URL.
     */
    protected function whereIs(object $page): string
    {
        $where = $this->readNow($page, self::LOCATION_JS);

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
