# Capture du sélecteur visuel en back-office (lib) : plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :**
- capturer la page déjà ouverte dans un onglet (`PageSnapshot::captureCurrent()`) ;
- ouvrir la page d'un checkpoint BO avec le code du run (`VisualTestsSuite::openBackOfficeCheckpoint()`), avec des plafonds courts pour le sélecteur visuel de l'app ;
- refermer la session employé (`VisualTestsSuite::closeBackOfficeSession()`) sans jamais faire échouer la capture.

**Architecture :**
- `PageSnapshot::take()` = navigateur + navigation + `captureCurrent()`. Le corps de la capture est déplacé, pas réécrit.
- Plafonds sans changer aucune signature du run : deux propriétés publiques, lues par le code existant.
  - `CommonPage::$navigationTimeout` (`?int`, `null` = défaut chrome-php 30 s) : `goToUrl()`, `BackOfficePage::goToPage()`, `BackOfficePage::goToMenu()`.
  - `v9\BackOffice\Login\Page::$loginOutcomeTimeout` (`int`, 60 000 par défaut) : lu par `login()`. `login()` note l'issue dans `$loginOutcomeSeen` (`?bool`).
- `openBackOfficeCheckpoint()` pose les plafonds, appelle `ensureBackOfficeLogin()` / `goToPage('index')` / `goToMenu()` (inchangés), puis rétablit les valeurs. Un dépassement devient `BackOfficeTimeoutException`.

**Tech Stack :** PHP ^8.1, PHPUnit 10.5, chrome-php.

**Spec :** `docs/superpowers/specs/2026-10-06-bo-picker-snapshot-design.md` (spec complète côté app : `docs/superpowers/specs/2026-10-06-visual-bo-picker-design.md` du dépôt PrestaFlow/app, worktree `app-claude-bo-picker`).

---

## Écarts et précisions vs spec

1. **`captureCurrent(object $page, int $timeoutMs = 15000)`** au lieu de `\HeadlessChromium\Page $page`. Les doubles de `PageSnapshotTest` sont des classes anonymes, pas des `\HeadlessChromium\Page`. `waitUntilStable(object $page)` est déjà typé ainsi. L'app passe `TestsSuite::getPage()`, un vrai `\HeadlessChromium\Page`. Le docblock le précise.
2. **Plafonds par propriétés, pas par paramètres.**
   - `login()` et `waitForLoginOutcome()` gardent leur signature et leur défaut de 60 s. `login()` lit `$this->loginOutcomeTimeout` (60 000 par défaut) et le passe à `waitForLoginOutcome()`.
   - Un paramètre de plus sur `login()` obligerait `ensureBackOfficeLogin()` à le transmettre, et casserait les surcharges `login($email, $password, $waitForNavigation)` (doubles de test, pages de projets).
   - Même raison pour la navigation : `goToPage($page, $params)` est surchargeable par les pages des projets. Un paramètre optionnel ajouté dans `BackOfficePage` rendrait ces surcharges incompatibles (erreur fatale). La propriété `CommonPage::$navigationTimeout` ne change aucune signature.
3. **Issue de connexion non vue = délai.** Sans issue (ni lien de déconnexion ni alerte) dans le plafond, `login()` n'échoue pas : le run conclut ensuite « identifiants refusés ou page inattendue (page : …) ». Pour distinguer ce cas, `login()` note `$loginOutcomeSeen` (`true` / `false`). `openBackOfficeCheckpoint()` transforme l'échec de connexion en `BackOfficeTimeoutException` quand `$loginOutcomeSeen === false`.
4. **`ensureBackOfficeLogin()` garde la cause.** Elle relève désormais `\RuntimeException('Connexion au back-office impossible : …', 0, $cause)`, et garde la cause dans `$boLoginCause` (remise à `null` par `init()`). Le message ne change pas. Sans la cause, un délai de chargement de la page de connexion (`OperationTimedOut`) serait indiscernable d'un refus. Aucun test existant ne regarde `getPrevious()`.
5. **Plafond de navigation = `$menuTimeoutMs`, appliqué à chaque navigation** de `openBackOfficeCheckpoint()` : racine du BO de la page Login, racine du BO, entrée du menu. La spec ne borne que « la navigation du menu ». Mais la racine et la page de connexion ont le même défaut de 30 s, qui ferait seul dépasser les 60 s de nginx.
6. **`openBackOfficeCheckpoint()` renvoie `string`, pas `void`.** Elle renvoie le chemin et le contrôleur de la page ouverte, sans jeton (même JS que `whereIs()`, mis en constante `LOCATION_JS`), ou `''` si illisible. L'app en fait le champ `url` du payload : `SnapshotResult` ne porte pas d'URL, et l'URL de l'onglet porte le jeton.
7. **`closeBackOfficeSession(int $timeoutMs = 5000): void`.** Le paramètre optionnel borne la navigation de déconnexion (`goToUrl()` du lien `#header_logout`). Il correspond aux « 5 s » du budget de la spec côté app.
8. **Exception de délai : `PrestaFlow\Library\Exceptions\BackOfficeTimeoutException`**, qui étend `TimeoutException` (donc `\RuntimeException`). Messages lisibles :
   - connexion : « Le back-office n'a pas répondu à la connexion en 25 s. » ;
   - navigation : « Le back-office n'a pas répondu en 15 s (chargement d'une page). »
9. **Refus.**
   - Hors zone `bo` : `\LogicException`.
   - Checkpoint `auth => false` avec un `menu` : `\InvalidArgumentException`, qui est une `\LogicException`, avec le message d'`init()`. L'app le refuse avant d'appeler.
   - Toute autre `\Exception` non `\RuntimeException` (exceptions chrome-php comme `ResponseHasError`) est relevée en `\RuntimeException` avec le même message. Les `\Error` (défaut de code) passent telles quelles.
10. **Pages importées à la demande.** `openBackOfficeCheckpoint()` appelle `importVisualPage()` si `backOfficePage` manque : l'app ne lance pas `init()`, qui enregistrerait les étapes du run. L'état de connexion (`boLoggedIn`, `boLoginError`, `boLoginCause`, `lastVisualUrl`) est remis à zéro à chaque ouverture, comme dans `init()`.
11. **`goToPage()` n'est pas testé unitairement.** Il appelle `TestsSuite::recreatePageIfContextChanged()`, qui lance Chrome. Sa modification se limite à un argument de `waitForNavigation()`. `goToMenu()` et `goToUrl()` sont testés.

---

## Contexte pour l'implémenteur

- **Dépôt :** worktree `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual`, branche `feat/bo-picker-snapshot` (créée depuis `dev`). Les commandes se lancent depuis ce dossier.
- **Ne jamais toucher** `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library` : c'est le checkout de l'utilisateur.
- **PHP :**
  - `php` = 8.4 ;
  - `"$HOME/Library/Application Support/Herd/bin/php81"` = 8.1.
- **Tests :**
  - suite complète : `php vendor/bin/phpunit` (≈ 550 tests verts au départ ; relève le nombre exact au premier lancement) ;
  - redirige TOUJOURS la sortie vers un fichier, par exemple `/private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/<nom>.txt 2>&1`, puis lance `tail -3`. Un processus Chrome enfant bloque les pipes ;
  - **aucun test ne lance Chrome** : doubles comme dans `PageSnapshotTest`, `VisualTestsSuiteBackOfficeTest`, `BackOfficeGoToMenuTest`, `BackOfficeLoginOutcomeTest`. N'appelle jamais `goToPage()` d'une vraie `BackOfficePage` dans un test.
- **Commits :**
  - chemins explicites, jamais `git add -A`, `git add .` ni `git commit -a` ;
  - messages en français, terminés par une ligne vide puis `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Lecture des fichiers :** `rtk proxy cat` ou `sed -n` (la sortie de `cat` est filtrée). Garde les commentaires non visés.
- **Noms partagés avec le plan de l'app** (`docs/superpowers/plans/2026-10-06-visual-bo-picker.md` du worktree `app-claude-bo-picker`) : ne pas les changer.
  - `PageSnapshot::captureCurrent(object $page, int $timeoutMs = 15000): SnapshotResult` ;
  - `VisualTestsSuite::openBackOfficeCheckpoint(array $checkpoint, int $loginTimeoutMs = 25000, int $menuTimeoutMs = 15000): string` ;
  - `VisualTestsSuite::closeBackOfficeSession(int $timeoutMs = 5000): void` ;
  - `PrestaFlow\Library\Exceptions\BackOfficeTimeoutException`.

---

### Task 1 : `PageSnapshot::captureCurrent()`

**Goal :** la capture d'une page déjà ouverte est une méthode publique ; `take()` l'appelle après sa navigation, sans changer de comportement.

**Files :**
- Modify : `src/Visual/PageSnapshot.php` (`take()` l.40-102 ; nouvelle méthode juste après)
- Modify : `tests/Unit/Visual/PageSnapshotTest.php` (propriété `$factory`, `snapshot()` l.16-127, deux tests ajoutés)

**Acceptance Criteria :**
- [ ] `captureCurrent(object $page, int $timeoutMs = 15000): SnapshotResult` fait, dans l'ordre : stabilité, `SETTLE_ANIMATIONS`, statut, dimensions (hauteur plafonnée à `MAX_HEIGHT`), `scrollTo(0, 0)`, `ELEMENT_MAP`, capture JPEG qualité 80.
- [ ] Elle ne navigue pas et ne ferme pas le navigateur.
- [ ] `take()` crée le navigateur, navigue (même message d'erreur), appelle `captureCurrent($page, $timeoutMs)` et ferme le navigateur dans son `finally`.
- [ ] Les 6 tests existants de `PageSnapshotTest` passent sans modification de leurs assertions.
- [ ] `take()` et `captureCurrent()` donnent le même `SnapshotResult` sur la même page factice.

**Verify :** `php vendor/bin/phpunit tests/Unit/Visual/PageSnapshotTest.php` → OK (8 tests)

**Steps :**

- [ ] **Step 1 : tests qui échouent**

Dans `tests/Unit/Visual/PageSnapshotTest.php` :

- ajoute, sous la propriété `$rec` :

```php
    /** @var \Closure(array): object fabrique de navigateur du dernier snapshot() (pour obtenir une page factice) */
    private \Closure $factory;
```

- dans `snapshot()`, remplace la dernière ligne

```php
        return new PageSnapshot($factory, stableTimeoutMs: 50, pollMs: 10);
```

par :

```php
        $this->factory = $factory;

        return new PageSnapshot($factory, stableTimeoutMs: 50, pollMs: 10);
```

- ajoute à la fin de la classe :

```php
    public function test_capture_current_captures_the_open_page_without_navigating_or_closing(): void
    {
        $snapshot = $this->snapshot(['status' => 404, 'size' => [1280, 2000]]);
        $page = ($this->factory)([])->createPage();

        $result = $snapshot->captureCurrent($page);

        $this->assertSame(['stable', 'settle', 'status', 'size', 'top', 'map', 'screenshot'], $this->rec['log']);
        $this->assertFalse($this->rec['closed']);
        $this->assertSame('JPEGDATA', $result->image);
        $this->assertSame('image/jpeg', $result->mime);
        $this->assertSame([1280, 2000], [$result->width, $result->height]);
        $this->assertSame(404, $result->status);
        $this->assertSame('body', $result->elements[0]['selector']);
    }

    public function test_take_gives_what_capture_current_gives_after_navigating(): void
    {
        $taken = $this->snapshot()->take('https://shop.test/', 'desktop');

        $snapshot = $this->snapshot();
        $current = $snapshot->captureCurrent(($this->factory)([])->createPage());

        $this->assertEquals($taken, $current);
    }
```

Lance `php vendor/bin/phpunit tests/Unit/Visual/PageSnapshotTest.php` (sortie vers un fichier) → 2 erreurs : `Call to undefined method …::captureCurrent()`.

- [ ] **Step 2 : implémentation**

Dans `src/Visual/PageSnapshot.php`, remplace le bloc de `take()` qui va de

```php
            $stable = $this->waitUntilStable($page);
```

jusqu'à

```php
            return new SnapshotResult((string) $image, 'image/jpeg', $width, $height, is_array($elements) ? $elements : [], $stable, $status);
```

(inclus) par :

```php
            return $this->captureCurrent($page, $timeoutMs);
```

Le `try { $page->navigate(...) } catch` qui précède et le `finally` qui ferme le navigateur restent tels quels.

Ajoute, juste après la méthode `take()` :

```php
    /**
     * Capture de la page déjà ouverte dans $page : stabilité (bornée par
     * stableTimeoutMs), animations figées, statut HTTP, carte des éléments et
     * capture pleine page en JPEG. Ne navigue pas et ne ferme rien : l'onglet et
     * son navigateur appartiennent à l'appelant (take(), ou l'app qui a ouvert
     * une page back-office avec VisualTestsSuite::openBackOfficeCheckpoint()).
     *
     * @param object $page onglet chrome-php (\HeadlessChromium\Page) ; typé object
     *                     pour les doubles de test, comme waitUntilStable()
     */
    public function captureCurrent(object $page, int $timeoutMs = 15000): SnapshotResult
    {
        $stable = $this->waitUntilStable($page);
        $page->evaluate(PageScripts::SETTLE_ANIMATIONS)->getReturnValue();

        $status = (int) $page->evaluate(
            "(function(){var n=performance.getEntriesByType('navigation')[0];return n&&n.responseStatus?n.responseStatus:0;})()"
        )->getReturnValue();
        // Une page en erreur (404, 500…) est capturée comme les autres : un point de
        // contrôle peut légitimement cibler une page d'erreur. Le statut est renvoyé.

        [$width, $height] = $page->evaluate(
            '[Math.ceil(document.documentElement.scrollWidth), Math.ceil(document.documentElement.scrollHeight)]'
        )->getReturnValue();
        $width = max(1, (int) $width);
        $height = max(1, min((int) $height, self::MAX_HEIGHT));

        $page->evaluate('window.scrollTo(0, 0)')->getReturnValue();
        $json = $page->evaluate(PageScripts::ELEMENT_MAP.'('.self::MAX_ELEMENTS.', '.$height.')')->getReturnValue($timeoutMs);
        $elements = is_string($json) ? json_decode($json, true) : null;

        $image = base64_decode((string) $page->screenshot([
            'format' => 'jpeg',
            'quality' => self::JPEG_QUALITY,
            'captureBeyondViewport' => true,
            'clip' => new Clip(0, 0, $width, $height),
        ])->getBase64($timeoutMs), true);

        return new SnapshotResult((string) $image, 'image/jpeg', $width, $height, is_array($elements) ? $elements : [], $stable, $status);
    }
```

Mets à jour le docblock de la classe : remplace la phrase « Navigateur DÉDIÉ (jamais l'instance statique de TestsSuite) : une capture ne doit pas perturber un run en cours, et inversement. » par :

```php
 * take() : navigateur DÉDIÉ (jamais l'instance statique de TestsSuite) : une
 * capture ne doit pas perturber un run en cours, et inversement.
 * captureCurrent() : page déjà ouverte par l'appelant (sélecteur visuel BO de
 * l'app, navigateur à portée « picker-… »).
```

- [ ] **Step 3 : vérifier**

```bash
php vendor/bin/phpunit tests/Unit/Visual/PageSnapshotTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t1.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t1.txt
php vendor/bin/phpunit > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full1.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full1.txt
```

Expected : `PageSnapshotTest` OK (8 tests) ; suite complète verte (nombre de départ + 2).

- [ ] **Step 4 : commit**

```bash
git add src/Visual/PageSnapshot.php tests/Unit/Visual/PageSnapshotTest.php
git commit -m "feat(visual): PageSnapshot::captureCurrent capture la page déjà ouverte ; take() l'appelle après sa navigation

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2 : plafonds de navigation et de connexion, sans changer le run

**Goal :** l'appelant peut borner la navigation (`CommonPage::$navigationTimeout`) et l'attente de l'issue de connexion (`Login\Page::$loginOutcomeTimeout`), et savoir si l'issue a été vue (`$loginOutcomeSeen`). Par défaut, rien ne change : 30 s chrome-php et 60 s.

**Files :**
- Modify : `src/Pages/CommonPage.php` (propriété après `public string $pageTitle = '';`, l.~45 ; `goToUrl()` l.~912)
- Modify : `src/Pages/BackOfficePage.php` (`goToPage()` l.50, `goToMenu()` l.207)
- Modify : `src/Pages/v9/BackOffice/Login/Page.php` (propriétés en tête de classe ; `login()` l.57)
- Modify : `tests/Unit/Pages/BackOfficeGoToMenuTest.php`
- Modify : `tests/Unit/Pages/BackOfficeLoginOutcomeTest.php`

**Acceptance Criteria :**
- [ ] `public ?int $navigationTimeout = null;` sur `CommonPage`, transmis comme 2e argument de `waitForNavigation()` par `goToUrl()`, `BackOfficePage::goToPage()` et `BackOfficePage::goToMenu()`. `null` = défaut chrome-php (30 s), donc comportement actuel.
- [ ] `public int $loginOutcomeTimeout = 60000;` et `public ?bool $loginOutcomeSeen = null;` sur `v9\BackOffice\Login\Page` (hérités par v7 et v8).
- [ ] `login()` (avec `$waitForNavigation`) appelle `waitForLoginOutcome($this->loginOutcomeTimeout)` et range le booléen renvoyé dans `$loginOutcomeSeen`.
- [ ] Signatures de `login()`, `waitForLoginOutcome()`, `goToPage()`, `goToMenu()` et `goToUrl()` inchangées.

**Verify :** `php vendor/bin/phpunit tests/Unit/Pages/BackOfficeGoToMenuTest.php tests/Unit/Pages/BackOfficeLoginOutcomeTest.php` → OK

**Steps :**

- [ ] **Step 1 : tests de navigation qui échouent**

Dans `tests/Unit/Pages/BackOfficeGoToMenuTest.php` :

- ajoute `use PrestaFlow\Library\Tests\TestsSuite;` sous `use PrestaFlow\Library\Pages\BackOfficePage;` ;
- dans la classe anonyme de `page()`, sous `public array $navigated = [];`, ajoute :

```php
            /** Plafond reçu par chaque waitForNavigation() (null = défaut chrome-php). */
            public array $waits = [];
```

- dans `navigate()` du double, remplace

```php
                        return new class {
                            public function waitForNavigation($event = null): void
                            {
                            }
                        };
```

par :

```php
                        return new class ($this->outer) {
                            public function __construct(private $outer)
                            {
                            }

                            public function waitForNavigation($event = null, $timeout = null): void
                            {
                                $this->outer->waits[] = $timeout;
                            }
                        };
```

- ajoute à la fin de la classe de test :

```php
    public function test_menu_navigation_keeps_the_chrome_default_unless_capped(): void
    {
        $href = 'http://shop.test/admin-dev/index.php?controller=AdminOrders&token=abc';
        $page = $this->page(['#subtab-AdminOrders' => $href]);

        $page->goToMenu('#subtab-AdminOrders');
        $page->navigationTimeout = 15000;
        $page->goToMenu('#subtab-AdminOrders');

        $this->assertSame([null, 15000], $page->waits);
    }

    public function test_url_navigation_keeps_the_chrome_default_unless_capped(): void
    {
        // goToUrl() réapplique les en-têtes persistants : vides ici, aucun navigateur n'est lancé.
        $headers = TestsSuite::$extraHttpHeaders;
        TestsSuite::$extraHttpHeaders = [];
        try {
            $page = $this->page([]);

            $page->goToUrl('http://shop.test/admin-dev/logout');
            $page->navigationTimeout = 5000;
            $page->goToUrl('http://shop.test/admin-dev/logout');

            $this->assertSame([null, 5000], $page->waits);
            $this->assertSame(['http://shop.test/admin-dev/logout', 'http://shop.test/admin-dev/logout'], $page->navigated);
        } finally {
            TestsSuite::$extraHttpHeaders = $headers;
        }
    }
```

Lance `php vendor/bin/phpunit tests/Unit/Pages/BackOfficeGoToMenuTest.php` → les 2 nouveaux tests échouent (`[null, null]` au lieu de `[null, 15000]` / `[null, 5000]`). Une dépréciation « Creation of dynamic property » peut s'afficher : elle disparaît avec l'implémentation.

- [ ] **Step 2 : tests de connexion qui échouent**

Dans `tests/Unit/Pages/BackOfficeLoginOutcomeTest.php`, dans `FakeLoginOutcomePage`, sous `public array $log = [];`, ajoute :

```php
    /** Plafond reçu par chaque waitForLoginOutcome(). */
    public array $outcomeTimeouts = [];

    public function waitForLoginOutcome(int $timeout = 60000, int $interval = 200): bool
    {
        $this->outcomeTimeouts[] = $timeout;

        return parent::waitForLoginOutcome($timeout, $interval);
    }
```

Ajoute à la fin de `BackOfficeLoginOutcomeTest` :

```php
    public function testLoginKeepsTheRunCeilingByDefault(): void
    {
        $page = $this->page();
        $page->settlesOnPoll = 1;

        $page->login();

        $this->assertSame([60000], $page->outcomeTimeouts);
        $this->assertTrue($page->loginOutcomeSeen);
    }

    public function testLoginUsesTheCeilingSetOnThePage(): void
    {
        $page = $this->page();
        $page->loginOutcomeTimeout = 150;
        $page->settlesOnPoll = 1;

        $page->login();

        $this->assertSame([150], $page->outcomeTimeouts);
    }

    public function testAnOutcomeNotSeenWithinTheCeilingIsRecorded(): void
    {
        $page = $this->page();
        $page->loginOutcomeTimeout = 150;
        $page->neverSettles = true;

        $page->login();

        $this->assertFalse($page->loginOutcomeSeen);
    }
```

Lance seulement les deux premiers (le 3e attendrait 60 s avant l'implémentation) :

```bash
php vendor/bin/phpunit tests/Unit/Pages/BackOfficeLoginOutcomeTest.php --filter 'testLoginKeepsTheRunCeilingByDefault|testLoginUsesTheCeilingSetOnThePage'
```

Expected : FAIL. `loginOutcomeSeen` n'existe pas (null au lieu de true), et `[60000]` au lieu de `[150]`.

- [ ] **Step 3 : implémentation**

Dans `src/Pages/CommonPage.php`, juste après `public string $pageTitle = '';`, ajoute :

```php

    /**
     * Plafond (ms) des navigations de goToUrl() et, en back-office, de
     * goToPage() / goToMenu() ; null = défaut de chrome-php (30 s). Posé puis
     * rétabli par VisualTestsSuite::openBackOfficeCheckpoint() pour le
     * sélecteur visuel de l'app, qui doit rester sous les 60 s de nginx.
     */
    public ?int $navigationTimeout = null;
```

Dans `goToUrl()`, remplace :

```php
        $this->getPage()->navigate($url)->waitForNavigation(DomPage::DOM_CONTENT_LOADED);
```

par :

```php
        $this->getPage()->navigate($url)->waitForNavigation(DomPage::DOM_CONTENT_LOADED, $this->navigationTimeout);
```

Dans `src/Pages/BackOfficePage.php` :
- `goToPage()` : remplace `$this->getPage()->navigate($url)->waitForNavigation(\HeadlessChromium\Page::DOM_CONTENT_LOADED);` par `$this->getPage()->navigate($url)->waitForNavigation(\HeadlessChromium\Page::DOM_CONTENT_LOADED, $this->navigationTimeout);` ;
- `goToMenu()` : remplace `$page->navigate($href)->waitForNavigation(\HeadlessChromium\Page::DOM_CONTENT_LOADED);` par `$page->navigate($href)->waitForNavigation(\HeadlessChromium\Page::DOM_CONTENT_LOADED, $this->navigationTimeout);`.

`setSingleShopContext()` et `goToSubMenu()` ne changent pas.

Dans `src/Pages/v9/BackOffice/Login/Page.php`, sous `public string $pageTitle = 'PrestaShop';`, ajoute :

```php

    /**
     * Plafond (ms) de l'attente de l'issue de la connexion dans login().
     * 60 s pour un run (premier tableau de bord lent en CI, voir
     * waitForLoginOutcome()) ; le sélecteur visuel de l'app le baisse.
     */
    public int $loginOutcomeTimeout = 60000;

    /**
     * Issue de la dernière connexion de login() : true = vue (session ouverte
     * ou alerte d'erreur), false = plafond atteint sans issue, null = pas encore
     * attendue. isLoggedIn() reste le constat de la session.
     */
    public ?bool $loginOutcomeSeen = null;
```

Dans `login()`, remplace :

```php
            $this->waitForLoginOutcome();
```

par :

```php
            $this->loginOutcomeSeen = $this->waitForLoginOutcome($this->loginOutcomeTimeout);
```

- [ ] **Step 4 : vérifier**

```bash
php vendor/bin/phpunit tests/Unit/Pages/BackOfficeGoToMenuTest.php tests/Unit/Pages/BackOfficeLoginOutcomeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2.txt
php vendor/bin/phpunit > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full2.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full2.txt
"$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit tests/Unit/Pages > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2-81.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2-81.txt
```

Expected : OK partout ; suite complète = Task 1 + 5 tests, sans dépréciation nouvelle.

- [ ] **Step 5 : commit**

```bash
git add src/Pages/CommonPage.php src/Pages/BackOfficePage.php src/Pages/v9/BackOffice/Login/Page.php tests/Unit/Pages/BackOfficeGoToMenuTest.php tests/Unit/Pages/BackOfficeLoginOutcomeTest.php
git commit -m "feat(pages): plafonds de navigation et d'issue de connexion réglables par page, défauts du run inchangés

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3 : `openBackOfficeCheckpoint()`, `closeBackOfficeSession()` et `BackOfficeTimeoutException`

**Goal :** l'app ouvre, dans le navigateur courant, la page d'un checkpoint BO avec le code du run, sous des plafonds courts, puis referme la session.

**Files :**
- Create : `src/Exceptions/BackOfficeTimeoutException.php`
- Modify : `src/Tests/VisualTestsSuite.php` (imports l.5-7 ; propriété `$boLoginCause` après `$boLoggedIn` l.52 ; `init()` l.~278 ; `ensureBackOfficeLogin()` l.403-435 ; `whereIs()` l.441-446 ; nouvelles méthodes après `ensureBackOfficeLogin()`)
- Modify : `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php` (doubles `page()` l.86-109 et `login()` l.111-127 ; tests ajoutés)

**Acceptance Criteria :**
- [ ] `openBackOfficeCheckpoint(array $checkpoint, int $loginTimeoutMs = 25000, int $menuTimeoutMs = 15000): string` :
  - `\LogicException` hors `bo`, et `\InvalidArgumentException` pour `auth => false` + `menu`, sans navigation ;
  - `auth` vrai : `ensureBackOfficeLogin()`, puis `goToPage('index')`, puis `goToMenu($menu)` si `menu` ;
  - `auth => false` : `goToPage('index')` seulement, et l'erreur « Session back-office déjà ouverte… » si le formulaire est absent ;
  - plafonds posés pendant l'appel (`navigationTimeout` des deux pages = `$menuTimeoutMs`, `loginOutcomeTimeout` = `$loginTimeoutMs`) et rétablis ensuite, même en cas d'exception ;
  - issue de connexion non vue, ou `OperationTimedOut` / `TimeoutException` dans la chaîne des causes : `BackOfficeTimeoutException` (message lisible, avec la durée en secondes) ;
  - autres erreurs : `\RuntimeException` avec le message du run (identifiants refusés + message du formulaire, page inattendue + chemin, entrée de menu introuvable) ;
  - renvoie le chemin et le contrôleur de la page ouverte, sans jeton.
- [ ] `closeBackOfficeSession(int $timeoutMs = 5000): void` appelle `logout()` de la page Login une seule fois si une session est ouverte, sous le plafond donné (rétabli ensuite), et ne lève jamais.
- [ ] Les tests existants de `VisualTestsSuiteBackOfficeTest` passent sans changement de leurs assertions (journaux identiques).

**Verify :** `php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php` → OK

**Steps :**

- [ ] **Step 1 : doubles enrichis (sans changer les journaux)**

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php` :

- imports, sous `use PHPUnit\Framework\TestCase;` :

```php
use HeadlessChromium\Exception\OperationTimedOut;
use PrestaFlow\Library\Exceptions\BackOfficeTimeoutException;
```

- remplace la méthode `page()` entière par :

```php
    /** Page factice : journalise navigation et captures dans un journal partagé. */
    private function page(\ArrayObject $log): object
    {
        return new class ($log, $this->chrome) {
            public array $calls = [];
            public ?string $failOn = null;
            /** Plafond de navigation posé par la suite (CommonPage::$navigationTimeout). */
            public ?int $navigationTimeout = null;
            /** Plafond vu à chaque navigation. */
            public array $navTimeouts = [];
            public ?\Throwable $navThrows = null;
            public ?\Throwable $menuThrows = null;
            public function __construct(public \ArrayObject $log, public object $chrome) {}
            public function getPage(): object { return $this->chrome; }
            public function goToPage($page = null, $params = null): void
            {
                $this->log[] = 'page '.$page;
                $this->navTimeouts[] = $this->navigationTimeout;
                if ($this->navThrows !== null) {
                    throw $this->navThrows;
                }
            }
            public function goToMenu(string $selectors): string
            {
                $this->log[] = 'menu '.$selectors;
                $this->navTimeouts[] = $this->navigationTimeout;
                if ($this->menuThrows !== null) {
                    throw $this->menuThrows;
                }

                return 'http://shop.test/admin-dev/x?token=t';
            }
            public function goToUrl(string $url): void { $this->log[] = 'goto '.$url; }
            public function waitForStable(): bool { $this->log[] = 'stable'; return true; }
            public function waitVisible(string $s): void {}
            public function scrollBelow(string $s): void { $this->log[] = 'scrollBelow '.$s; }
            public function scrollToTop(): void { $this->log[] = 'top'; }
            public function visualCheckpoint(string $name, ?string $selector = null, ?float $threshold = null, bool $fullPage = true, string $tag = 'auto', array $masks = [], ?int $maxDiffPixels = null, array $hide = [], bool $freezeTransitions = false): void
            {
                $this->log[] = 'capture '.$name;
                $this->calls[$name] = [$hide, $freezeTransitions];
                if ($this->failOn === $name) {
                    throw new \RuntimeException('capture ratée');
                }
            }
        };
    }
```

- remplace la méthode `login()` entière par :

```php
    private function login(\ArrayObject $log, bool $ok = true): object
    {
        // Ne navigue pas l'onglet partagé : la présence du formulaire est fixée par
        // le test ($this->chrome->form), comme l'afficherait la racine du BO pour la
        // session courante. Suffisant : la suite ne la lit qu'après une navigation.
        return new class ($log, $ok, $this->chrome) {
            /** Plafonds posés par la suite (CommonPage::$navigationTimeout, Login\Page::$loginOutcomeTimeout). */
            public ?int $navigationTimeout = null;
            public int $loginOutcomeTimeout = 60000;
            public ?bool $loginOutcomeSeen = null;
            /** Issue vue par login() dans le plafond (false = délai). */
            public bool $outcome = true;
            public bool $logoutThrows = false;
            public ?\Throwable $navThrows = null;
            public array $navTimeouts = [];
            public array $outcomeTimeouts = [];
            public array $logoutTimeouts = [];
            public function __construct(public \ArrayObject $log, public bool $ok, public object $chrome) {}
            public function getPage(): object { return $this->chrome; }
            public function getSelector($selector, $replacements = []): string
            {
                return ['emailInput' => '#email', 'alertDangerDiv' => '.alert-danger'][$selector];
            }
            public function goToPage($page = null, $params = null): void
            {
                $this->log[] = 'login:page '.$page;
                $this->navTimeouts[] = $this->navigationTimeout;
                if ($this->navThrows !== null) {
                    throw $this->navThrows;
                }
            }
            public function login($email = null, $password = null, $waitForNavigation = true): void
            {
                $this->log[] = 'login:submit';
                $this->outcomeTimeouts[] = $this->loginOutcomeTimeout;
                $this->loginOutcomeSeen = $this->outcome;
            }
            public function isLoggedIn(): bool { $this->log[] = 'login:check'; return $this->ok; }
            public function logout(): void
            {
                $this->log[] = 'login:logout';
                $this->logoutTimeouts[] = $this->navigationTimeout;
                if ($this->logoutThrows) {
                    throw new \RuntimeException('Cannot log out: no logout link found at "#header_logout".');
                }
            }
        };
    }
```

Lance `php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php` → OK, mêmes tests verts qu'avant (les journaux n'ont pas changé).

- [ ] **Step 2 : tests qui échouent**

Ajoute à la fin de `VisualTestsSuiteBackOfficeTest` :

```php
    public function test_open_checkpoint_logs_in_then_opens_the_dashboard_then_the_menu(): void
    {
        $this->chrome->location = '/admin-dev/index.php?controller=AdminProducts';
        $log = new \ArrayObject();
        $page = $this->page($log);
        $login = $this->login($log);
        $s = $this->suite(page: $page, login: $login);

        $where = $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts'], 25000, 15000);

        $this->assertSame([
            'login:page index', 'login:submit', 'login:check',
            'page index', 'menu #subtab-AdminProducts',
        ], $log->getArrayCopy());
        // Chemin et contrôleur, jamais le jeton.
        $this->assertSame('/admin-dev/index.php?controller=AdminProducts', $where);
        // Plafonds pendant l'ouverture, valeurs du run rétablies ensuite.
        $this->assertSame([25000], $login->outcomeTimeouts);
        $this->assertSame([15000], $login->navTimeouts);
        $this->assertSame([15000, 15000], $page->navTimeouts);
        $this->assertNull($page->navigationTimeout);
        $this->assertNull($login->navigationTimeout);
        $this->assertSame(60000, $login->loginOutcomeTimeout);
    }

    public function test_open_checkpoint_without_menu_stays_on_the_dashboard(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));

        $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => null]);

        $this->assertSame(['login:page index', 'login:submit', 'login:check', 'page index'], $log->getArrayCopy());
    }

    public function test_open_checkpoint_without_auth_opens_the_login_page_without_logging_in(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));

        $s->openBackOfficeCheckpoint(['name' => 'picker', 'auth' => false]);

        $this->assertSame(['page index'], $log->getArrayCopy());
    }

    public function test_open_checkpoint_without_auth_fails_when_a_session_is_open(): void
    {
        $this->chrome->form = false;
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session back-office déjà ouverte : la page de connexion ne peut pas être capturée');
        $s->openBackOfficeCheckpoint(['name' => 'picker', 'auth' => false]);
    }

    public function test_open_checkpoint_reports_refused_credentials_like_the_run(): void
    {
        $this->chrome->error = '  The employee does not exist.  ';
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log, ok: false));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts']);
            $this->fail('RuntimeException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->fail('Pas un délai : '.$e->getMessage());
        } catch (\RuntimeException $e) {
            $this->assertSame('Connexion au back-office impossible : identifiants refusés ou page inattendue : The employee does not exist.', $e->getMessage());
        }
        $this->assertNotContains('page index', $log->getArrayCopy());
    }

    public function test_open_checkpoint_reports_a_missing_menu_entry_like_the_run(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $page->menuThrows = new \RuntimeException('Entrée du menu introuvable : #subtab-Nope');
        $s = $this->suite(page: $page, login: $this->login($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-Nope']);
            $this->fail('RuntimeException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->fail('Pas un délai : '.$e->getMessage());
        } catch (\RuntimeException $e) {
            $this->assertSame('Entrée du menu introuvable : #subtab-Nope', $e->getMessage());
        }
        $this->assertNull($page->navigationTimeout);
    }

    public function test_open_checkpoint_turns_an_unseen_login_outcome_into_a_timeout(): void
    {
        $log = new \ArrayObject();
        $login = $this->login($log, ok: false);
        $login->outcome = false; // ni lien de déconnexion ni alerte dans le plafond
        $s = $this->suite(page: $this->page($log), login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e);
            $this->assertStringContainsString('connexion en 25 s', $e->getMessage());
        }
        $this->assertSame(60000, $login->loginOutcomeTimeout);
        $this->assertNull($login->navigationTimeout);
    }

    public function test_open_checkpoint_turns_a_navigation_timeout_into_a_timeout(): void
    {
        $log = new \ArrayObject();
        $page = $this->page($log);
        $page->navThrows = new OperationTimedOut('Operation timed out after 15s.');
        $s = $this->suite(page: $page, login: $this->login($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertStringContainsString('15 s', $e->getMessage());
            $this->assertInstanceOf(OperationTimedOut::class, $e->getPrevious());
        }
        $this->assertNull($page->navigationTimeout);
    }

    public function test_open_checkpoint_turns_a_login_page_timeout_into_a_timeout(): void
    {
        // Le délai survient dans ensureBackOfficeLogin(), qui le relève avec sa cause.
        $log = new \ArrayObject();
        $login = $this->login($log);
        $login->navThrows = new OperationTimedOut('Operation timed out after 15s.');
        $s = $this->suite(page: $this->page($log), login: $login);

        $this->expectException(BackOfficeTimeoutException::class);
        $this->expectExceptionMessage('15 s');
        $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);
    }

    public function test_open_checkpoint_is_refused_outside_the_back_office(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite('fo', page: $this->page($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker']);
            $this->fail('LogicException attendue');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('« bo »', $e->getMessage());
        }
        $this->assertSame([], $log->getArrayCopy());
    }

    public function test_open_checkpoint_refuses_a_login_page_with_a_menu(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'auth' => false, 'menu' => '#subtab-AdminOrders']);
            $this->fail('InvalidArgumentException attendue');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('menu interdit', $e->getMessage());
        }
        $this->assertSame([], $log->getArrayCopy());
    }

    public function test_close_session_logs_out_once_under_its_ceiling(): void
    {
        $log = new \ArrayObject();
        $login = $this->login($log);
        $s = $this->suite(page: $this->page($log), login: $login);
        $s->openBackOfficeCheckpoint(['name' => 'picker']);

        $s->closeBackOfficeSession(5000);
        $s->closeBackOfficeSession(5000);

        $this->assertSame(1, count(array_keys($log->getArrayCopy(), 'login:logout')));
        $this->assertSame([5000], $login->logoutTimeouts);
        $this->assertNull($login->navigationTimeout);
    }

    public function test_close_session_without_session_does_nothing(): void
    {
        $log = new \ArrayObject();
        $s = $this->suite(page: $this->page($log), login: $this->login($log));
        $s->closeBackOfficeSession();

        $s->openBackOfficeCheckpoint(['name' => 'picker', 'auth' => false]);
        $s->closeBackOfficeSession();

        $this->assertNotContains('login:logout', $log->getArrayCopy());
    }

    public function test_close_session_never_throws(): void
    {
        $log = new \ArrayObject();
        $login = $this->login($log);
        $login->logoutThrows = true;
        $s = $this->suite(page: $this->page($log), login: $login);
        $s->openBackOfficeCheckpoint(['name' => 'picker']);

        $s->closeBackOfficeSession();

        $this->assertContains('login:logout', $log->getArrayCopy());
        $this->assertNull($login->navigationTimeout);
    }
```

Lance `php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php` → les nouveaux tests échouent : classe `BackOfficeTimeoutException` introuvable, méthodes `openBackOfficeCheckpoint()` / `closeBackOfficeSession()` inexistantes.

- [ ] **Step 3 : exception de délai**

Crée `src/Exceptions/BackOfficeTimeoutException.php` :

```php
<?php

namespace PrestaFlow\Library\Exceptions;

/**
 * Le back-office n'a pas répondu dans le plafond donné (issue de la connexion,
 * ou chargement d'une page) : levée par VisualTestsSuite::openBackOfficeCheckpoint().
 * Message lisible, destiné à l'utilisateur.
 */
class BackOfficeTimeoutException extends TimeoutException
{
}
```

- [ ] **Step 4 : `VisualTestsSuite`**

Dans `src/Tests/VisualTestsSuite.php` :

1. Imports : remplace

```php
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Utils\Env;
use PrestaFlow\Library\Visual\VisualDevices;
```

par :

```php
use HeadlessChromium\Exception\OperationTimedOut;
use PrestaFlow\Library\Exceptions\BackOfficeTimeoutException;
use PrestaFlow\Library\Exceptions\TimeoutException;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Utils\Env;
use PrestaFlow\Library\Visual\VisualDevices;
```

2. Sous `protected bool $boLoggedIn = false;`, ajoute :

```php
    /** Cause de l'échec de connexion, gardée comme `previous` de l'erreur relevée (délai ≠ refus). */
    protected ?\Throwable $boLoginCause = null;

    /** Chemin et contrôleur de la page courante : jamais le jeton de l'URL. */
    private const LOCATION_JS = '(function(){var c=new URLSearchParams(location.search).get("controller");return location.pathname+(c?"?controller="+c:"");})()';
```

3. Dans `init()`, sous `$this->boLoginError = null;`, ajoute `$this->boLoginCause = null;`.

4. Dans `ensureBackOfficeLogin()`, remplace

```php
            } catch (\Throwable $e) {
                $this->boLoginError = $e->getMessage();
            }
        }

        throw new \RuntimeException('Connexion au back-office impossible : '.$this->boLoginError);
```

par :

```php
            } catch (\Throwable $e) {
                $this->boLoginError = $e->getMessage();
                $this->boLoginCause = $e;
            }
        }

        throw new \RuntimeException('Connexion au back-office impossible : '.$this->boLoginError, 0, $this->boLoginCause);
```

5. Dans `whereIs()`, remplace

```php
        $where = $this->readNow($page, '(function(){var c=new URLSearchParams(location.search).get("controller");return location.pathname+(c?"?controller="+c:"");})()');
```

par :

```php
        $where = $this->readNow($page, self::LOCATION_JS);
```

6. Ajoute, juste après la méthode `ensureBackOfficeLogin()` :

```php
    /**
     * Ouvre, dans le navigateur courant (TestsSuite::getPage()), la page d'un
     * checkpoint BO avec le code du run : connexion (ensureBackOfficeLogin()),
     * racine du BO, puis entrée du menu (goToMenu()) ; `auth => false` : racine
     * du BO sans session (page de connexion). Pour le sélecteur visuel de l'app,
     * qui capture ensuite la page avec PageSnapshot::captureCurrent().
     *
     * $loginTimeoutMs borne l'attente de l'issue de la connexion
     * (Login\Page::$loginOutcomeTimeout), $menuTimeoutMs chaque navigation
     * (CommonPage::$navigationTimeout). Les valeurs du run sont rétablies avant
     * de rendre la main, même en cas d'erreur.
     *
     * @return string chemin et contrôleur de la page ouverte, sans jeton ('' si illisible)
     *
     * @throws \LogicException            hors zone 'bo' ; \InvalidArgumentException pour auth => false avec un menu
     * @throws BackOfficeTimeoutException plafond dépassé (message lisible)
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

        $restore = $this->capBackOfficeWaits($page, $login, $loginTimeoutMs, $menuTimeoutMs);
        try {
            if ($cp['auth']) {
                try {
                    $this->ensureBackOfficeLogin($login);
                } catch (\RuntimeException $e) {
                    if ($login !== null && ($login->loginOutcomeSeen ?? null) === false) {
                        throw new BackOfficeTimeoutException(sprintf("Le back-office n'a pas répondu à la connexion en %d s.", intdiv($loginTimeoutMs, 1000)), 0, $e);
                    }
                    throw $e;
                }
            }
            $page->goToPage('index');
            if ($cp['menu'] !== null) {
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
                throw new BackOfficeTimeoutException(sprintf("Le back-office n'a pas répondu en %d s (chargement d'une page).", intdiv($menuTimeoutMs, 1000)), 0, $e);
            }
            if ($e instanceof \RuntimeException || !$e instanceof \Exception) {
                throw $e; // erreur du run telle quelle ; un \Error (défaut de code) n'est pas masqué
            }
            throw new \RuntimeException($e->getMessage(), 0, $e); // exceptions chrome-php (ResponseHasError…)
        } finally {
            $restore();
        }
    }

    /**
     * Déconnexion par le lien #header_logout de la page courante
     * (BackOffice\Login\Page::logout()) si une session est ouverte. Ne lève
     * jamais : un échec de déconnexion ne doit pas faire échouer la capture
     * (le navigateur de l'app est fermé juste après). $timeoutMs borne la
     * navigation de déconnexion.
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

    /**
     * Pose les plafonds de openBackOfficeCheckpoint() sur les pages et renvoie
     * de quoi rétablir les valeurs d'avant (celles du run).
     */
    private function capBackOfficeWaits(object $page, ?object $login, int $loginTimeoutMs, int $navigationTimeoutMs): \Closure
    {
        $pageBefore = $page->navigationTimeout ?? null;
        $loginBefore = $login?->navigationTimeout ?? null;
        $outcomeBefore = $login?->loginOutcomeTimeout ?? null;

        $page->navigationTimeout = $navigationTimeoutMs;
        if ($login !== null) {
            $login->navigationTimeout = $navigationTimeoutMs;
            $login->loginOutcomeTimeout = $loginTimeoutMs;
            $login->loginOutcomeSeen = null;
        }

        return static function () use ($page, $login, $pageBefore, $loginBefore, $outcomeBefore): void {
            $page->navigationTimeout = $pageBefore;
            if ($login !== null) {
                $login->navigationTimeout = $loginBefore;
                if ($outcomeBefore !== null) {
                    $login->loginOutcomeTimeout = $outcomeBefore;
                }
            }
        };
    }

    /** Délai de chrome-php ou de la lib quelque part dans la chaîne des causes. */
    private static function isTimeout(\Throwable $e): bool
    {
        for ($t = $e; $t !== null; $t = $t->getPrevious()) {
            if ($t instanceof OperationTimedOut || $t instanceof TimeoutException) {
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
```

7. Docblock de la classe (l.9-22) : ajoute à la fin, avant ` */` :

```php
 *
 * Sélecteur visuel de l'app : openBackOfficeCheckpoint() ouvre la page d'un
 * checkpoint BO avec ce même code (connexion, menu), sous des plafonds courts ;
 * closeBackOfficeSession() referme la session employé.
```

- [ ] **Step 5 : vérifier**

```bash
php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t3.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t3.txt
php vendor/bin/phpunit > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full3.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full3.txt
"$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full3-81.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full3-81.txt
git diff --stat dev -- src/Tests/VisualTestsSuite.php
```

Expected :
- `VisualTestsSuiteBackOfficeTest` OK, dont `test_unexpected_page_reports_where_the_browser_is` (vérifie `LOCATION_JS`) et `test_failed_login_fails_every_logged_in_checkpoint_without_retrying` (message inchangé) ;
- suite complète verte en 8.4 et en 8.1 (Task 2 + 14 tests).

- [ ] **Step 6 : commit**

```bash
git add src/Exceptions/BackOfficeTimeoutException.php src/Tests/VisualTestsSuite.php tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php
git commit -m "feat(visual): openBackOfficeCheckpoint ouvre la page BO d'un checkpoint comme le run, sous plafonds ; closeBackOfficeSession referme la session

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4 : livraison (coordinateur)

- [ ] Relecture finale (`rtk proxy git diff dev...HEAD > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/lib-branch.diff`). Points à vérifier :
  - `take()` : même ordre d'appels, même message de délai de navigation, `finally` qui ferme le navigateur ;
  - aucun défaut changé : `waitForLoginOutcome()` 60 s, `navigationTimeout` null ;
  - `ensureBackOfficeLogin()` : seul changement = la cause en `previous` ;
  - les noms partagés avec le plan de l'app (section « Contexte ») sont exacts.
- [ ] Suite complète verte en 8.4 et en 8.1 (sorties redirigées).
- [ ] Push de `feat/bo-picker-snapshot`, PR vers `dev` (titre : « Capture du sélecteur visuel en back-office »), description terminée par la ligne d'attribution prévue. CI verte.
- [ ] Sur demande seulement, dans cet ordre :
  1. merge de la PR en **commit de merge** (`gh pr merge <N> --merge`), sujet « <titre> (#N) » ;
  2. release : PR `dev` → `main` intitulée « Release: capture du sélecteur visuel en back-office (#N) », mergée en **commit de merge** (`gh pr merge <N> --merge`). **Jamais de rebase ni de squash** : une release rebasée réécrit les SHA de `main` et met la release suivante en conflit (incident #113/#115) ;
  3. signaler au coordinateur de l'app que la tâche 0 de son plan (`composer update prestaflow/php-library`, seul `composer.lock` change) peut commencer.

---

## Auto-relecture : couverture de la spec

| Section de la spec | Tâche(s) |
|---|---|
| `captureCurrent()` : tout ce que `take()` fait après la navigation (stabilité, `SETTLE_ANIMATIONS`, statut, dimensions plafonnées, `ELEMENT_MAP`, JPEG) | 1 |
| `take()` = navigateur + navigation + `captureCurrent()`, comportement et résultat inchangés (tests existants) | 1 (6 tests existants intacts + test d'égalité) |
| `openBackOfficeCheckpoint()` publique, navigateur courant, `normalize()` | 3 |
| `auth: false` : racine du BO, erreur « session déjà ouverte » | 3 |
| sinon `ensureBackOfficeLogin()`, `goToPage('index')`, `goToMenu()` si `menu` | 3 |
| Plafonds connexion (`waitForLoginOutcome`) et navigation, exception de délai dédiée au message lisible | 2 (propriétés), 3 (`BackOfficeTimeoutException`) ; écarts 2, 3, 5, 8 |
| Erreurs du run en `\RuntimeException` avec le message du run | 3 (identifiants refusés, menu introuvable, page inattendue via `ensureBackOfficeLogin`) |
| `area === 'bo'` exigée, sinon `\LogicException` | 3 |
| `closeBackOfficeSession()` : `logout()` si session ouverte, ne lève jamais | 3 ; écart 7 |
| Aucune autre méthode du run ne change ; `ensureBackOfficeLogin` / `goToMenu` seule implémentation | 2, 3 ; écarts 2 et 4 (cause en `previous`, `LOCATION_JS`), sans changement de comportement |
| Tests : `captureCurrent` avec les doubles de `PageSnapshotTest` | 1 |
| Tests : ordre connexion → tableau de bord → menu ; `auth: false` ; identifiants refusés ; menu introuvable ; délai ; refus hors BO | 3 |
| Tests : `closeBackOfficeSession` appelle `logout()` si session, ne lève jamais | 3 |
| Livraison : PR → `dev` (merge commit), release `dev` → `main` (merge commit), bump app (`composer.lock` seul) | 4 (bump : tâche 0 du plan de l'app) |
