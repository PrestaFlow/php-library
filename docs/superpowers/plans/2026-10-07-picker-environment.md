# Sélecteur visuel : environnement, connexion au plus juste, nettoyage (lib) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:**
- appliquer l'environnement du run (Basic Auth, `PRESTAFLOW_EXTRA_HEADERS`, `PRESTAFLOW_COOKIES`) hors de `before()` : `TestsSuite::applyEnvironment()`, appelée par `PageSnapshot::take()` et par l'app ; `TestsSuite::clearEnvironment()` pour qu'un worker persistant oublie les en-têtes d'un job ;
- laisser à l'issue de la connexion BO tout le temps que le rechargement n'a pas pris (`Login\Page::$loginOutcomeDeadline`), au lieu de réserver 10 s fixes ;
- nettoyage : jetons doublement encodés masqués, docblock de `UNSTABLE_WARNING` à sa place, section README « Visual picker API ».

**Architecture:**
- `TestsSuite` : trois étapes privées statiques (`presetBasicAuthOn()`, `presetExtraHeadersOn()`, `presetCookiesOn()`), qui reçoivent le navigateur et la page sous forme de closures résolues seulement quand une variable est posée. `applyEnvironment()` les enchaîne sur un navigateur et une page donnés ; les trois méthodes protégées du run (`presetBasicAuth()`, `presetExtraHeadersFromEnv()`, `presetEnvCookies()`) les appellent sur le navigateur partagé. `before()` ne change pas.
- `PageSnapshot::take()` appelle `TestsSuite::applyEnvironment($browser, $page)` entre `createPage()` et `navigate()`.
- `Login\Page::login()` : plafond de l'issue = `max(1000, $loginOutcomeDeadline - nowMs())` si l'échéance est posée, sinon `$loginOutcomeTimeout`. `VisualTestsSuite::openBackOfficeCheckpoint()` pose l'échéance juste avant l'envoi et la rétablit à la fin (`capBackOfficeWaits()`).

**Tech Stack:** PHP ^8.1, PHPUnit 10.5, chrome-php.

**Spec:** `docs/superpowers/specs/2026-10-07-picker-environment-design.md` (partie app : `app/docs/superpowers/specs/2026-10-07-visual-picker-async-design.md`).

---

## Contexte implémenteur

- **Dépôt :** worktree `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library-picker-env`, branche `feat/picker-environment` (base `origin/dev` `da93bc1`). Toutes les commandes se lancent depuis ce dossier.
- **Ne jamais toucher** `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library` : c'est le checkout de l'utilisateur.
- **Dépendances :** `vendor/` est installé. S'il manque : `composer install -q`.
- **PHP :**
  - `php` = 8.4 ;
  - `"$HOME/Library/Application Support/Herd/bin/php81"` = 8.1, la version minimale : la suite complète doit passer avec les deux.
- **Tests :**
  - suite complète : `php vendor/bin/phpunit > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full.txt` ; au départ : `OK (593 tests, 1672 assertions)` ; à la fin du plan : 609 tests ;
  - redirige TOUJOURS la sortie de PHPUnit vers un fichier puis lis-le (`tail`, `sed -n`) : un processus Chrome enfant bloque les pipes ;
  - **aucun test ne lance Chrome** : doubles (classes anonymes) comme dans `PageSnapshotTest`, `VisualTestsSuiteBackOfficeTest`, `BackOfficeLoginOutcomeTest`. Les doubles reprennent les signatures de chrome-php : `Connection::setConnectionHttpHeaders(array $headers): void`, `Session::sendMessageSync(Message $message, ?int $timeout = null)`, `Page::setExtraHTTPHeaders(array $headers = []): void`, `Page::setCookies($cookies)` qui renvoie un `ResponseWaiter` (`await(?int $time = null)`), `Cookie::create($name, $value, array $params = [])` ;
  - variables d'environnement en test : via `$_ENV` (lu en premier par `Env::get()`), sauvegardées en `setUp()` et rétablies en `tearDown()`. Une chaîne vide vaut « absente » pour les trois étapes, même si le shell définit la variable.
- **Commits :**
  - chemins explicites, jamais `git add -A`, `git add .` ni `git commit -a` ;
  - messages en français, terminés par une ligne vide puis `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Lecture :** outil Read ou `sed -n 'A,Bp' fichier`. `cat`, `grep` et `git` sont filtrés par rtk : `rtk proxy grep -n …`, `rtk proxy git show …`, `rtk proxy git diff …`.
- **Numéros de ligne :** ceux de `da93bc1`. Une tâche qui insère du code décale les lignes suivantes du même fichier : repère-toi au texte cité dans chaque remplacement.
- **Noms partagés avec l'app** (ne pas les changer) :
  - `TestsSuite::applyEnvironment(object $browser, object $page): void` ;
  - `TestsSuite::clearEnvironment(): void` ;
  - `v9\BackOffice\Login\Page::$loginOutcomeDeadline` (`?int`, ms monotones) et `Login\Page::nowMs(): int` (protégée).

---

## Écarts vs spec

1. **`applyEnvironment(object $browser, object $page)`** au lieu de `(\HeadlessChromium\Browser $browser, \HeadlessChromium\Page $page)`. Les doubles de `PageSnapshotTest` sont des classes anonymes, pas des `Browser` / `Page` : un type strict ferait échouer `take()` dans tous ses tests. C'est la convention déjà prise pour `PageSnapshot::captureCurrent(object $page)`. Le docblock donne les vrais types ; l'app passe un vrai `Browser` et une vraie `Page`.
2. **`before()` appelle toujours les trois méthodes protégées**, et non `applyEnvironment()` directement. Les deux passent par les mêmes étapes privées. Appeler `applyEnvironment()` depuis `before()` aurait :
   - court-circuité une suite qui surcharge `presetBasicAuth()`, `presetExtraHeadersFromEnv()` ou `presetEnvCookies()` (la spec veut ne casser aucune de ces suites) ;
   - obligé à résoudre la page avant l'étape, même sans variable : `TestsSuite::getPage()` peut lever, et `presetExtraHeadersFromEnv()` ne doit jamais lancer de navigateur (`getBrowser(force: false)`, testé par `ExtraHeadersFromEnvTest`).
   
   « `before()` passe par elle » est prouvé par un test d'équivalence : sur les mêmes doubles, `before()` produit le journal d'`applyEnvironment()`, précédé du vidage des cookies.
3. **`capBackOfficeWaits()` ne sauvegarde plus `loginOutcomeTimeout`** : `openBackOfficeCheckpoint()` ne l'écrit plus. Elle sauvegarde et rétablit `loginOutcomeDeadline` à la place. Les assertions existantes « `loginOutcomeTimeout` reste à sa valeur » restent vraies et sont gardées.
4. **Double de connexion de `VisualTestsSuiteBackOfficeTest`** : `submitCostMs` (rechargement + issue + constat d'un bloc) est remplacé par `reloadCostMs`, `outcomeCostMs` et `checkCostMs`, pour reproduire `Login\Page::login()` (rechargement 10 s au plus, puis issue plafonnée par l'échéance, 1 s au moins) et `isLoggedIn()` (5 s au plus). Les tests de budget qui reposaient sur l'ancien calcul changent de chiffres (tâche 4, liste exacte).

---

### Task 1: `TestsSuite::applyEnvironment()` et `clearEnvironment()`

**Goal:** l'environnement du run s'applique à un navigateur et une page donnés, avec les étapes et l'ordre de `before()`, que `before()` garde à l'identique ; `clearEnvironment()` vide `$extraHttpHeaders`.

**Files:**
- Create: `tests/Unit/Tests/ApplyEnvironmentTest.php`
- Modify: `src/Tests/TestsSuite.php` (docblock de `$extraHttpHeaders` l.151-155 ; `presetBasicAuth()` et `presetExtraHeadersFromEnv()` l.717-814 ; `applyExtraHttpHeaders()` et `presetEnvCookies()` l.847-921)
- Test: `tests/Unit/Tests/ExtraHeadersFromEnvTest.php` (inchangé, doit rester vert)

**Acceptance Criteria:**
- [ ] `public static function applyEnvironment(object $browser, object $page): void` pose, dans l'ordre : Basic Auth (connexion avec `['Authorization' => …]`, puis `Network.enable` et `setExtraHTTPHeaders` sur la page), puis les en-têtes `PRESTAFLOW_EXTRA_HEADERS` fusionnés par-dessus (connexion avec tous les en-têtes, puis page), puis les cookies `PRESTAFLOW_COOKIES` sur la page.
- [ ] Sans variable, aucun appel au navigateur ni à la page, et `TestsSuite::$extraHttpHeaders` reste vide.
- [ ] `public static function clearEnvironment(): void` vide `TestsSuite::$extraHttpHeaders`. Le run ne l'appelle pas.
- [ ] `presetBasicAuth()`, `presetExtraHeadersFromEnv()`, `presetEnvCookies()` restent `protected`, avec la même signature, et délèguent aux étapes partagées. `before()` n'est pas modifiée.
- [ ] `before()` produit sur des doubles le même journal qu'`applyEnvironment()`, précédé de `Network.clearBrowserCookies`.
- [ ] Les 9 tests de `ExtraHeadersFromEnvTest` passent sans modification.

**Verify:** `php vendor/bin/phpunit tests/Unit/Tests > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t1.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t1.txt` → `OK (35 tests, 51 assertions)` (dont les 5 tests d'`ApplyEnvironmentTest` et les 9 d'`ExtraHeadersFromEnvTest`)

**Steps:**

- [ ] **Step 1 : test qui échoue**

Crée `tests/Unit/Tests/ApplyEnvironmentTest.php` :

```php
<?php

namespace PrestaFlow\Tests\Unit\Tests;

use HeadlessChromium\Communication\Message;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Environnement du run (Basic Auth, PRESTAFLOW_EXTRA_HEADERS, PRESTAFLOW_COOKIES)
 * appliqué à un navigateur et une page donnés. Aucun Chrome : doubles qui
 * reprennent les signatures de chrome-php (Connection::setConnectionHttpHeaders,
 * Session::sendMessageSync, Page::setExtraHTTPHeaders, Page::setCookies()->await())
 * et notent chaque appel dans un journal.
 */
final class ApplyEnvironmentTest extends TestCase
{
    private const KEYS = ['PRESTAFLOW_BASIC_USER', 'PRESTAFLOW_BASIC_PASS', 'PRESTAFLOW_EXTRA_HEADERS', 'PRESTAFLOW_COOKIES'];

    private array $envBackup = [];
    private array $headersBackup = [];
    private ?string $socketFile = null;

    protected function setUp(): void
    {
        foreach (self::KEYS as $key) {
            $this->envBackup[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
            // Chaîne vide = variable absente pour les trois étapes, même si le shell la définit.
            $_ENV[$key] = '';
        }
        $this->headersBackup = TestsSuite::$extraHttpHeaders;
        TestsSuite::$extraHttpHeaders = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        TestsSuite::$extraHttpHeaders = $this->headersBackup;
        if ($this->socketFile !== null) {
            InjectedBrowserSuite::forgetBrowser();
            @unlink($this->socketFile);
            TestsSuite::scopeBrowserFilesTo(null);
        }
    }

    /** @return array{0: object, 1: object, 2: \ArrayObject} navigateur, page, journal */
    private function doubles(): array
    {
        $journal = new \ArrayObject();

        $connection = new class ($journal) {
            public function __construct(private \ArrayObject $journal) {}
            public function isConnected(): bool { return true; }
            public function setConnectionHttpHeaders(array $headers): void
            {
                $this->journal[] = 'connection '.json_encode($headers);
            }
        };
        $session = new class ($journal) {
            public function __construct(private \ArrayObject $journal) {}
            public function sendMessageSync(Message $message, ?int $timeout = null): object
            {
                $this->journal[] = 'session '.$message->getMethod();

                return new \stdClass();
            }
        };
        $page = new class ($journal, $session) {
            public function __construct(private \ArrayObject $journal, private object $session) {}
            public function getSession(): object { return $this->session; }
            public function setExtraHTTPHeaders(array $headers = []): void
            {
                $this->journal[] = 'page '.json_encode($headers);
            }
            public function setCookies($cookies): object
            {
                foreach ($cookies as $cookie) {
                    $this->journal[] = sprintf('cookie %s=%s domain=%s path=%s', $cookie->getName(), $cookie->getValue(), (string) $cookie->offsetGet('domain'), (string) $cookie->offsetGet('path'));
                }

                return new class {
                    public function await(?int $time = null): self { return $this; }
                };
            }
        };
        $browser = new class ($connection, $page) {
            public function __construct(private object $connection, private object $page) {}
            public function getConnection(): object { return $this->connection; }
            public function getPages(): array { return [$this->page]; }
            public function createPage(): object { return $this->page; }
            public function close(): void {}
        };

        return [$browser, $page, $journal];
    }

    private function setFullEnvironment(): void
    {
        $_ENV['PRESTAFLOW_BASIC_USER'] = 'admin';
        $_ENV['PRESTAFLOW_BASIC_PASS'] = 's3cret';
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{"X-CI-Bypass":"k"}';
        $_ENV['PRESTAFLOW_COOKIES'] = '[{"name":"consent","value":"1","domain":"shop.test"},{"value":"sans nom"}]';
    }

    public function test_basic_auth_then_extra_headers_then_cookies(): void
    {
        $this->setFullEnvironment();
        [$browser, $page, $journal] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page);

        $basic = ['Authorization' => 'Basic YWRtaW46czNjcmV0'];
        $all = $basic + ['X-CI-Bypass' => 'k'];
        $this->assertSame([
            'connection '.json_encode($basic),
            'session Network.enable',
            'page '.json_encode($basic),
            'connection '.json_encode($all),
            'session Network.enable',
            'page '.json_encode($all),
            'cookie consent=1 domain=shop.test path=/',
        ], $journal->getArrayCopy());
        $this->assertSame($all, TestsSuite::$extraHttpHeaders);
    }

    public function test_extra_headers_may_override_basic_auth(): void
    {
        $_ENV['PRESTAFLOW_BASIC_USER'] = 'admin';
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{"Authorization":"Bearer t"}';
        [$browser, $page] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page);

        $this->assertSame(['Authorization' => 'Bearer t'], TestsSuite::$extraHttpHeaders);
    }

    public function test_nothing_is_applied_without_environment(): void
    {
        [$browser, $page, $journal] = $this->doubles();

        TestsSuite::applyEnvironment($browser, $page);

        $this->assertSame([], $journal->getArrayCopy());
        $this->assertSame([], TestsSuite::$extraHttpHeaders);
    }

    public function test_clear_environment_forgets_the_headers(): void
    {
        $this->setFullEnvironment();
        [$browser, $page] = $this->doubles();
        TestsSuite::applyEnvironment($browser, $page);

        TestsSuite::clearEnvironment();

        $this->assertSame([], TestsSuite::$extraHttpHeaders);
    }

    public function test_before_applies_the_same_environment_as_apply_environment(): void
    {
        $this->setFullEnvironment();
        [$browser, $page, $expected] = $this->doubles();
        TestsSuite::applyEnvironment($browser, $page);
        TestsSuite::$extraHttpHeaders = [];

        // before() trouve le navigateur partagé par son fichier socket et son cache :
        // on y range le double, sans lancer Chrome.
        [$shared, , $journal] = $this->doubles();
        TestsSuite::scopeBrowserFilesTo('apply-env-'.getmypid());
        $this->socketFile = TestsSuite::getFilePath('.browser');
        file_put_contents($this->socketFile, 'ws://127.0.0.1:1/devtools/browser/double');
        InjectedBrowserSuite::useBrowser($shared, 'ws://127.0.0.1:1/devtools/browser/double');

        (new InjectedBrowserSuite(loadGlobals: false, getBrowser: false))->before(headless: true);

        // before() vide d'abord les cookies de la suite précédente, puis applique l'environnement.
        $this->assertSame(
            array_merge(['session Network.clearBrowserCookies'], $expected->getArrayCopy()),
            $journal->getArrayCopy()
        );
    }
}

/** Suite qui range un navigateur double dans le cache de TestsSuite::getBrowser(). */
final class InjectedBrowserSuite extends TestsSuite
{
    public static function useBrowser(object $browser, string $socket): void
    {
        static::$browserInstance = $browser;
        static::$browserInstanceSocket = $socket;
    }

    public static function forgetBrowser(): void
    {
        static::$browserInstance = null;
        static::$browserInstanceSocket = null;
    }
}
```

`before()` retrouve le navigateur partagé par son fichier socket (`TestsSuite::getFilePath('.browser')`) et son cache (`$browserInstance`, `$browserInstanceSocket`, comparés à l'identique, puis `getConnection()->isConnected()`) : le test y range le double. Aucun Chrome n'est lancé. Le fichier socket est propre au test (`scopeBrowserFilesTo('apply-env-<pid>')`) et supprimé en `tearDown()`.

- [ ] **Step 2 : le voir échouer**

Run: `php vendor/bin/phpunit tests/Unit/Tests/ApplyEnvironmentTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t1.txt 2>&1; sed -n '1,40p' /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t1.txt`
Expected: `Errors: 5` : `Call to undefined method PrestaFlow\Library\Tests\TestsSuite::applyEnvironment()` (les cinq tests l'appellent).

- [ ] **Step 3 : implémentation**

Trois remplacements dans `src/Tests/TestsSuite.php`, dans cet ordre (du bas vers le haut, pour que les numéros de ligne de `da93bc1` restent justes).

Dans `src/Tests/TestsSuite.php`, remplace les lignes 847 à 921 (du docblock « (Ré)applique self::$extraHttpHeaders… » jusqu'à l'accolade fermante de `presetEnvCookies()`, juste avant `public function after()`) par :

```php
    /**
     * (Ré)applique self::$extraHttpHeaders sur la page courante. À appeler après
     * toute (re)création de page — notamment dans goToPage — car un en-tête posé
     * sur une page fermée est perdu. Best-effort.
     */
    public static function applyExtraHttpHeaders(): void
    {
        if (empty(TestsSuite::$extraHttpHeaders)) {
            return;
        }

        self::pushExtraHttpHeaders(TestsSuite::getPage());
    }

    /** Pose self::$extraHttpHeaders sur $page (rien sans en-tête ni page). Best-effort. */
    private static function pushExtraHttpHeaders(?object $page): void
    {
        if (empty(TestsSuite::$extraHttpHeaders) || !$page) {
            return;
        }

        try {
            // Network doit être activé pour que Network.setExtraHTTPHeaders prenne effet.
            $page->getSession()->sendMessageSync(new Message('Network.enable'));
            $page->setExtraHTTPHeaders(TestsSuite::$extraHttpHeaders);
        } catch (Throwable $e) {
            // best-effort
        }
    }

    /**
     * Pré-règle des cookies fournis via l'environnement, avant toute navigation.
     *
     * PRESTAFLOW_COOKIES = tableau JSON d'objets {name, value, domain?, path?, secure?}.
     * Exemple (neutraliser un bandeau RGPD Knowband) :
     *   PRESTAFLOW_COOKIES=[{"name":"___kbgdcc","value":"eyIx...","domain":"preprod.example.com"}]
     *
     * Best-effort : n'interrompt jamais l'exécution si l'API cookies échoue.
     *
     * Gardée pour les suites qui la surchargent : même code qu'applyEnvironment(),
     * sur la page courante du navigateur partagé.
     */
    protected function presetEnvCookies(): void
    {
        self::presetCookiesOn(static fn () => TestsSuite::getPage());
    }

    /**
     * Étape PRESTAFLOW_COOKIES de before() et d'applyEnvironment().
     *
     * @param \Closure(): ?object $pageOf
     */
    private static function presetCookiesOn(\Closure $pageOf): void
    {
        $raw = Env::get('PRESTAFLOW_COOKIES');
        if (!$raw) {
            return;
        }

        $cookies = \json_decode($raw, true);
        if (!is_array($cookies)) {
            return;
        }

        $page = $pageOf();
        if (!$page) {
            return;
        }

        foreach ($cookies as $c) {
            if (empty($c['name'])) {
                continue;
            }

            $params = [];
            foreach (['domain', 'path', 'secure', 'httpOnly', 'sameSite', 'expires', 'url'] as $key) {
                if (array_key_exists($key, $c)) {
                    $params[$key] = $c[$key];
                }
            }
            if (!isset($params['path'])) {
                $params['path'] = '/';
            }

            try {
                $page->setCookies([
                    Cookie::create($c['name'], (string) ($c['value'] ?? ''), $params),
                ])->await();
            } catch (Throwable $e) {
                // best-effort
            }
        }
    }
```

Dans `src/Tests/TestsSuite.php`, remplace les lignes 717 à 814 (du docblock « Authentification HTTP Basic via l'environnement… » jusqu'à l'accolade fermante de `presetExtraHeadersFromEnv()`, juste avant le docblock « Ferme la page courante et en crée une fraîche… ») par :

```php
    /**
     * Environnement du run (Basic Auth, en-têtes PRESTAFLOW_EXTRA_HEADERS, cookies
     * PRESTAFLOW_COOKIES) appliqué à un navigateur et à une page donnés, pour ceux
     * qui ne passent pas par before() : PageSnapshot::take() et le sélecteur
     * visuel de l'app. Mêmes étapes, dans le même ordre, que before() :
     *  1. Basic Auth → TestsSuite::$extraHttpHeaders['Authorization'] ;
     *  2. PRESTAFLOW_EXTRA_HEADERS fusionnés par-dessus ;
     *  3. en-têtes posés sur la connexion (setConnectionHttpHeaders) et sur la page
     *     (Network.enable puis setExtraHTTPHeaders), à chacune des deux étapes ;
     *  4. cookies PRESTAFLOW_COOKIES posés sur la page.
     *
     * Valeurs lues dans l'environnement (Env::get), jamais écrites dans un message
     * ni un journal. Sans variable, rien n'est posé. Best-effort comme before().
     * Les en-têtes restent dans TestsSuite::$extraHttpHeaders : clearEnvironment()
     * les oublie.
     *
     * @param object $browser \HeadlessChromium\Browser ; typé object pour les doubles de test
     * @param object $page    \HeadlessChromium\Page ; typé object pour les doubles de test
     */
    public static function applyEnvironment(object $browser, object $page): void
    {
        $browserOf = static fn () => $browser;
        $pageOf = static fn () => $page;

        self::presetBasicAuthOn($browserOf, $pageOf);
        self::presetExtraHeadersOn($browserOf, $pageOf);
        self::presetCookiesOn($pageOf);
    }

    /**
     * Oublie les en-têtes persistants (TestsSuite::$extraHttpHeaders). Le run ne
     * l'appelle pas ; l'app l'appelle en libérant le navigateur du sélecteur
     * visuel, pour qu'un worker persistant ne garde pas les en-têtes d'un job
     * pour le suivant.
     */
    public static function clearEnvironment(): void
    {
        TestsSuite::$extraHttpHeaders = [];
    }

    /**
     * Authentification HTTP Basic via l'environnement, posée en en-tête sur toutes
     * les requêtes (utile pour un environnement protégé : preprod/staging).
     *
     * PRESTAFLOW_BASIC_USER / PRESTAFLOW_BASIC_PASS. Best-effort.
     *
     * On pose l'en-tête au niveau de la CONNEXION du navigateur : BrowserFactory
     * réapplique ces en-têtes à chaque nouvelle page. C'est indispensable car
     * FrontOfficePage::goToPage() ferme la page courante et en crée une neuve —
     * un en-tête posé uniquement sur la page initiale serait perdu. On l'applique
     * aussi à la page courante pour couvrir la toute première navigation.
     *
     * Gardée pour les suites qui la surchargent : même code qu'applyEnvironment(),
     * sur le navigateur partagé et sa page courante.
     */
    protected function presetBasicAuth(): void
    {
        self::presetBasicAuthOn(
            static fn () => TestsSuite::getBrowser(),
            static fn () => TestsSuite::getPage(),
        );
    }

    /**
     * En-têtes HTTP arbitraires depuis l'environnement PRESTAFLOW_EXTRA_HEADERS.
     *
     * Format : objet JSON `{"Header-Name": "value", ...}`. Chaque paire est
     * fusionnée dans self::$extraHttpHeaders — donc appliquée à toutes les
     * requêtes (navigation top-level, sous-ressources, XHR) et réappliquée
     * après chaque recreatePage(). Mêmes garanties que presetBasicAuth().
     *
     * Cas d'usage :
     *  - Bypass WAF/CDN (ex. Cloudflare WAF Skip rule : `X-CI-Bypass: <secret>`)
     *  - Request tracing (`X-Request-Id`, `X-CI-Run: <id>`)
     *  - En-têtes spécifiques d'un edge (Netlify, Vercel, etc.)
     *
     * Best-effort : JSON invalide → warning stderr, on n'interrompt pas le
     * bootstrap. Les clés non-string ou valeurs non-string sont ignorées.
     *
     * Gardée pour les suites qui la surchargent : même code qu'applyEnvironment(),
     * sur le navigateur partagé (sans en lancer un) et sa page courante.
     */
    protected function presetExtraHeadersFromEnv(): void
    {
        self::presetExtraHeadersOn(
            static fn () => TestsSuite::getBrowser(force: false),
            static fn () => TestsSuite::getPage(),
        );
    }

    /**
     * Étape Basic Auth de before() et d'applyEnvironment(). Navigateur et page
     * sont résolus seulement quand une variable est posée : sans variable, le run
     * n'ouvre rien de plus qu'avant.
     *
     * @param \Closure(): ?object $browserOf
     * @param \Closure(): ?object $pageOf
     */
    private static function presetBasicAuthOn(\Closure $browserOf, \Closure $pageOf): void
    {
        $user = Env::get('PRESTAFLOW_BASIC_USER');
        $pass = Env::get('PRESTAFLOW_BASIC_PASS');
        if ($user === null || $user === '') {
            return;
        }

        $authHeader = 'Basic '.\base64_encode($user.':'.($pass ?? ''));

        // Mémorisé pour réapplication après chaque (re)création de page (goToPage).
        TestsSuite::$extraHttpHeaders['Authorization'] = $authHeader;

        $browser = $browserOf();
        if ($browser) {
            try {
                // Hérité par chaque page créée ensuite (dont le createPage de goToPage).
                $browser->getConnection()->setConnectionHttpHeaders(['Authorization' => $authHeader]);
            } catch (Throwable $e) {
                // best-effort
            }
        }

        // Applique sur la page courante (première navigation).
        self::pushExtraHttpHeaders($pageOf());
    }

    /**
     * Étape PRESTAFLOW_EXTRA_HEADERS de before() et d'applyEnvironment().
     *
     * @param \Closure(): ?object $browserOf
     * @param \Closure(): ?object $pageOf
     */
    private static function presetExtraHeadersOn(\Closure $browserOf, \Closure $pageOf): void
    {
        $raw = Env::get('PRESTAFLOW_EXTRA_HEADERS');
        if ($raw === null || $raw === '') {
            return;
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            fwrite(STDERR, "[PrestaFlow] PRESTAFLOW_EXTRA_HEADERS: JSON invalide, ignoré.\n");
            return;
        }

        $added = [];
        foreach ($decoded as $name => $value) {
            if (!is_string($name) || $name === '' || !is_scalar($value)) {
                continue;
            }
            $added[$name] = (string) $value;
        }
        if ($added === []) {
            return;
        }

        // Merge : on préserve les en-têtes déjà posés (ex. Authorization par
        // presetBasicAuth). L'ordre d'appel dans before() fait que Basic Auth
        // gagne — sauf si l'utilisateur redéfinit explicitement 'Authorization'
        // dans PRESTAFLOW_EXTRA_HEADERS (surcharge volontaire).
        TestsSuite::$extraHttpHeaders = array_merge(TestsSuite::$extraHttpHeaders, $added);

        // Même chemin d'application que Basic Auth : au niveau de la connexion
        // pour héritage par chaque nouvelle page, puis sur la page courante.
        $browser = $browserOf();
        if ($browser) {
            try {
                $browser->getConnection()->setConnectionHttpHeaders(TestsSuite::$extraHttpHeaders);
            } catch (Throwable $e) {
                // best-effort
            }

            self::pushExtraHttpHeaders($pageOf());
        }
    }
```

Dans `src/Tests/TestsSuite.php`, remplace :

```php
     * goToPage (qui ferme puis recrée la page). Alimenté par presetBasicAuth().
     */
```

par :

```php
     * goToPage (qui ferme puis recrée la page). Alimenté par presetBasicAuth(),
     * presetExtraHeadersFromEnv() et applyEnvironment() ; vidé par clearEnvironment().
     */
```

Points d'équivalence avec l'ancien code (à garder) :
- `presetBasicAuth()` résolvait `TestsSuite::getBrowser()` (force par défaut) après le contrôle de la variable, puis `applyExtraHttpHeaders()` → `getPage()` ;
- `presetExtraHeadersFromEnv()` résolvait `TestsSuite::getBrowser(force: false)` et ne touchait la page que si un navigateur existait ;
- `presetEnvCookies()` ne résolvait la page qu'avec un JSON valide ;
- `applyExtraHttpHeaders()` ne résout la page que s'il y a des en-têtes.

- [ ] **Step 4 : le voir passer**

Run: `php vendor/bin/phpunit tests/Unit/Tests > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t1.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t1.txt`
Expected: `OK (35 tests, 51 assertions)`.

Puis la suite complète : `OK (598 tests, …)`.

- [ ] **Step 5 : commit**

```bash
git add src/Tests/TestsSuite.php tests/Unit/Tests/ApplyEnvironmentTest.php
git commit -m "feat(env): TestsSuite::applyEnvironment applique Basic Auth, en-têtes et cookies du run à un navigateur donné ; clearEnvironment

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: `PageSnapshot::take()` applique l'environnement

**Goal:** une page FO de préproduction protégée se capture : `take()` applique l'environnement du run entre `createPage()` et `navigate()`.

**Files:**
- Modify: `src/Visual/PageSnapshot.php` (imports l.5-7, docblock de classe l.14-15, `take()` l.60)
- Modify: `tests/Unit/Visual/PageSnapshotTest.php` (imports l.5-9, `setUp()`/`tearDown()` avant `snapshot()` l.19, doubles l.37-143, test ajouté en fin de classe)

**Acceptance Criteria:**
- [ ] `take()` appelle `TestsSuite::applyEnvironment($browser, $page)` après `createPage()` et avant `navigate()`, dans le `try` dont le `finally` ferme le navigateur.
- [ ] Avec `PRESTAFLOW_BASIC_USER` / `PRESTAFLOW_BASIC_PASS` / `PRESTAFLOW_COOKIES`, le journal commence par : connexion (Authorization), `Network.enable`, en-têtes de page, cookie, puis `navigate`.
- [ ] Sans variable, les 10 tests existants passent sans modification de leurs assertions (même journal qu'avant).
- [ ] `captureCurrent()` ne change pas.

**Verify:** `php vendor/bin/phpunit tests/Unit/Visual/PageSnapshotTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2.txt` → `OK (11 tests, 37 assertions)`

**Steps:**

- [ ] **Step 1 : test qui échoue**

Dans `tests/Unit/Visual/PageSnapshotTest.php`, remplace :

```php
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Visual\PageScripts;
```

par :

```php
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Visual\PageScripts;
```

Dans `tests/Unit/Visual/PageSnapshotTest.php`, remplace :

```php
    private function snapshot(array $values = [], ?\Throwable $navigateThrows = null, bool $factoryThrows = false): PageSnapshot
```

par :

```php
    private const ENV_KEYS = ['PRESTAFLOW_BASIC_USER', 'PRESTAFLOW_BASIC_PASS', 'PRESTAFLOW_EXTRA_HEADERS', 'PRESTAFLOW_COOKIES'];

    private array $envBackup = [];
    private array $headersBackup = [];

    protected function setUp(): void
    {
        // take() applique l'environnement du run : absent par défaut, même si le shell le définit.
        foreach (self::ENV_KEYS as $key) {
            $this->envBackup[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
            $_ENV[$key] = '';
        }
        $this->headersBackup = TestsSuite::$extraHttpHeaders;
        TestsSuite::$extraHttpHeaders = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        TestsSuite::$extraHttpHeaders = $this->headersBackup;
    }

    private function snapshot(array $values = [], ?\Throwable $navigateThrows = null, bool $factoryThrows = false): PageSnapshot
```

Dans `tests/Unit/Visual/PageSnapshotTest.php`, remplace (double du navigateur) :

```php
                public function createPage()
                {
```

par :

```php
                public function getConnection()
                {
                    $rec = &$this->rec;

                    return new class ($rec) {
                        public function __construct(private array &$rec)
                        {
                        }

                        public function setConnectionHttpHeaders(array $headers): void
                        {
                            $this->rec['log'][] = 'connection '.json_encode($headers);
                        }
                    };
                }

                public function createPage()
                {
```

Dans `tests/Unit/Visual/PageSnapshotTest.php`, remplace (double de la page) :

```php
                        public function navigate(string $url)
                        {
```

par :

```php
                        public function getSession()
                        {
                            $rec = &$this->rec;

                            return new class ($rec) {
                                public function __construct(private array &$rec)
                                {
                                }

                                public function sendMessageSync(\HeadlessChromium\Communication\Message $message, ?int $timeout = null): object
                                {
                                    $this->rec['log'][] = 'session '.$message->getMethod();

                                    return new \stdClass();
                                }
                            };
                        }

                        public function setExtraHTTPHeaders(array $headers = []): void
                        {
                            $this->rec['log'][] = 'headers '.json_encode($headers);
                        }

                        public function setCookies($cookies)
                        {
                            foreach ($cookies as $cookie) {
                                $this->rec['log'][] = 'cookie '.$cookie->getName().'='.$cookie->getValue();
                            }

                            return new class {
                                public function await(?int $time = null): self
                                {
                                    return $this;
                                }
                            };
                        }

                        public function navigate(string $url)
                        {
```

Dans `tests/Unit/Visual/PageSnapshotTest.php`, remplace (fin de la classe) :

```php
        $this->assertSame(['value 15000', 'base64 15000'], $this->rec['timeouts']);
    }
}
```

par :

```php
        $this->assertSame(['value 15000', 'base64 15000'], $this->rec['timeouts']);
    }

    public function test_take_applies_the_run_environment_before_navigating(): void
    {
        $_ENV['PRESTAFLOW_BASIC_USER'] = 'admin';
        $_ENV['PRESTAFLOW_BASIC_PASS'] = 's3cret';
        $_ENV['PRESTAFLOW_COOKIES'] = '[{"name":"consent","value":"1","domain":"shop.test"}]';

        $result = $this->snapshot()->take('https://shop.test/', 'desktop');

        $auth = json_encode(['Authorization' => 'Basic YWRtaW46czNjcmV0']);
        $this->assertSame(
            ['connection '.$auth, 'session Network.enable', 'headers '.$auth, 'cookie consent=1', 'navigate https://shop.test/'],
            array_slice($this->rec['log'], 0, 5)
        );
        $this->assertSame('JPEGDATA', $result->image);
        $this->assertTrue($this->rec['closed']);
    }
}
```

- [ ] **Step 2 : le voir échouer**

Run: `php vendor/bin/phpunit tests/Unit/Visual/PageSnapshotTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2.txt 2>&1; sed -n '1,40p' /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2.txt`
Expected: `Tests: 11, …, Failures: 1` : `test_take_applies_the_run_environment_before_navigating`, le journal commence par `navigate https://shop.test/` au lieu de `connection {"Authorization":…}`.

- [ ] **Step 3 : implémentation**

Dans `src/Visual/PageSnapshot.php`, remplace :

```php
use HeadlessChromium\Page;
```

par :

```php
use HeadlessChromium\Page;
use PrestaFlow\Library\Tests\TestsSuite;
```

Dans `src/Visual/PageSnapshot.php`, remplace :

```php
 * take() : navigateur DÉDIÉ (jamais l'instance statique de TestsSuite) : une
 * capture ne doit pas perturber un run en cours, et inversement.
```

par :

```php
 * take() : navigateur DÉDIÉ (jamais l'instance statique de TestsSuite) : une
 * capture ne doit pas perturber un run en cours, et inversement. L'environnement
 * du run (Basic Auth, en-têtes, cookies : TestsSuite::applyEnvironment()) y est
 * appliqué avant la navigation.
```

Dans `src/Visual/PageSnapshot.php`, remplace :

```php
            $page = $browser->createPage();
            try {
```

par :

```php
            $page = $browser->createPage();
            // Boutique protégée (préproduction) : environnement du run avant la navigation.
            TestsSuite::applyEnvironment($browser, $page);
            try {
```

- [ ] **Step 4 : le voir passer**

Run: `php vendor/bin/phpunit tests/Unit/Visual/PageSnapshotTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t2.txt`
Expected: `OK (11 tests, 37 assertions)`. Puis la suite complète : `OK (599 tests, …)`.

- [ ] **Step 5 : commit**

```bash
git add src/Visual/PageSnapshot.php tests/Unit/Visual/PageSnapshotTest.php
git commit -m "feat(visual): PageSnapshot::take applique l'environnement du run avant la navigation

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: `Login\Page::$loginOutcomeDeadline` et `nowMs()`

**Goal:** posée, l'échéance plafonne l'attente de l'issue au temps restant après le rechargement (1 s au moins) ; absente (le run), rien ne change.

**Files:**
- Modify: `src/Pages/v9/BackOffice/Login/Page.php` (propriété après `$loginOutcomeTimeout` l.11-16, `login()` l.72-73, `nowMs()` avant le docblock de `waitForLoginOutcome()` l.79)
- Modify: `tests/Unit/Pages/BackOfficeLoginOutcomeTest.php` (double `FakeLoginOutcomePage` l.48-81, 4 tests ajoutés avant `testAnOutcomeNotSeenWithinTheCeilingIsRecorded()` l.179)

**Acceptance Criteria:**
- [ ] `public ?int $loginOutcomeDeadline = null;` sur `v9\BackOffice\Login\Page` (héritée par v7 et v8).
- [ ] `protected function nowMs(): int` renvoie `intdiv(hrtime(true), 1_000_000)`, la même horloge que `VisualTestsSuite::nowMs()`.
- [ ] Dans `login()` (avec attente), après `waitForPageReload()` : plafond = `max(1000, $loginOutcomeDeadline - $this->nowMs())` si l'échéance est posée, sinon `$loginOutcomeTimeout`.
- [ ] Les 9 tests existants passent sans modification de leurs assertions (sans échéance : 60 000, ou `$loginOutcomeTimeout` posé).

**Verify:** `php vendor/bin/phpunit tests/Unit/Pages/BackOfficeLoginOutcomeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t3.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t3.txt` → `OK (13 tests, 22 assertions)`

**Steps:**

- [ ] **Step 1 : test qui échoue**

Dans `tests/Unit/Pages/BackOfficeLoginOutcomeTest.php`, remplace :

```php
    /** Plafond reçu par chaque waitForLoginOutcome(). */
    public array $outcomeTimeouts = [];
```

par :

```php
    /** Plafond reçu par chaque waitForLoginOutcome(). */
    public array $outcomeTimeouts = [];

    /** Horloge factice (ms) lue par nowMs() ; le rechargement l'avance de $reloadMs. */
    public int $now = 0;
    public int $reloadMs = 0;

    protected function nowMs(): int
    {
        return $this->now;
    }
```

Dans `tests/Unit/Pages/BackOfficeLoginOutcomeTest.php`, remplace :

```php
    public function waitForPageReload()
    {
        $this->log[] = 'reload';
    }
```

par :

```php
    public function waitForPageReload()
    {
        $this->log[] = 'reload';
        $this->now += $this->reloadMs;
    }
```

Dans `tests/Unit/Pages/BackOfficeLoginOutcomeTest.php`, remplace :

```php
    public function testAnOutcomeNotSeenWithinTheCeilingIsRecorded(): void
```

par :

```php
    public function testLoginWaitsForTheOutcomeUntilTheDeadline(): void
    {
        $page = $this->page();
        $page->settlesOnPoll = 1;
        $page->now = 100000;
        $page->reloadMs = 3000;
        // Échéance à 20 s du début ; rechargement de 3 s : 17 s restent à l'issue.
        $page->loginOutcomeDeadline = 120000;

        $page->login();

        $this->assertSame([17000], $page->outcomeTimeouts);
        $this->assertSame(['set #email', 'set #passwd', 'click #submit_login', 'reload'], $page->log);
    }

    public function testLoginKeepsOneSecondForTheOutcomeOnceTheDeadlineIsPassed(): void
    {
        $page = $this->page();
        $page->settlesOnPoll = 1;
        $page->now = 100000;
        $page->reloadMs = 10000;
        $page->loginOutcomeDeadline = 105000;

        $page->login();

        $this->assertSame([1000], $page->outcomeTimeouts);
    }

    public function testTheDeadlineWinsOverTheCeiling(): void
    {
        $page = $this->page();
        $page->settlesOnPoll = 1;
        $page->loginOutcomeTimeout = 150;
        $page->loginOutcomeDeadline = 4000;

        $page->login();

        $this->assertSame([4000], $page->outcomeTimeouts);
    }

    public function testNoDeadlineByDefault(): void
    {
        $this->assertNull($this->page()->loginOutcomeDeadline);
    }

    public function testAnOutcomeNotSeenWithinTheCeilingIsRecorded(): void
```

- [ ] **Step 2 : le voir échouer**

Run: `php vendor/bin/phpunit tests/Unit/Pages/BackOfficeLoginOutcomeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t3.txt 2>&1; sed -n '1,60p' /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t3.txt`
Expected: `Tests: 13, …, Failures: 3, Warnings: 1` : les trois tests d'échéance reçoivent `60000` (ou `150`) au lieu du temps restant ; `testNoDeadlineByDefault` lit une propriété indéfinie (avertissement ; en 8.4, la propriété dynamique posée par les autres tests ajoute des dépréciations).

- [ ] **Step 3 : implémentation**

Dans `src/Pages/v9/BackOffice/Login/Page.php`, remplace :

```php
    /**
     * Plafond (ms) de l'attente de l'issue de la connexion dans login().
     * 60 s pour un run (premier tableau de bord lent en CI, voir
     * waitForLoginOutcome()) ; le sélecteur visuel de l'app le baisse.
     */
    public int $loginOutcomeTimeout = 60000;
```

par :

```php
    /**
     * Plafond (ms) de l'attente de l'issue de la connexion dans login().
     * 60 s pour un run (premier tableau de bord lent en CI, voir
     * waitForLoginOutcome()). Ignoré quand $loginOutcomeDeadline est posée.
     */
    public int $loginOutcomeTimeout = 60000;

    /**
     * Échéance (ms monotones, l'horloge de nowMs()) de l'attente de l'issue dans
     * login() : le plafond vaut alors le temps restant après le rechargement,
     * 1 s au moins. null (défaut, et toujours pendant un run) : $loginOutcomeTimeout.
     * Posée par VisualTestsSuite::openBackOfficeCheckpoint() : un rechargement
     * court laisse le reste du budget à l'issue.
     */
    public ?int $loginOutcomeDeadline = null;
```

Dans `src/Pages/v9/BackOffice/Login/Page.php`, remplace :

```php
            $this->waitForPageReload();
            $this->loginOutcomeSeen = $this->waitForLoginOutcome($this->loginOutcomeTimeout);
```

par :

```php
            $this->waitForPageReload();
            $timeout = $this->loginOutcomeDeadline === null
                ? $this->loginOutcomeTimeout
                : max(1000, $this->loginOutcomeDeadline - $this->nowMs());
            $this->loginOutcomeSeen = $this->waitForLoginOutcome($timeout);
```

Dans `src/Pages/v9/BackOffice/Login/Page.php`, remplace :

```php
    /**
     * Wait until the login has an outcome: the logout link (session open) or
```

par :

```php
    /** Horloge monotone en millisecondes, celle de $loginOutcomeDeadline (surchargée en test unitaire). */
    protected function nowMs(): int
    {
        return intdiv(hrtime(true), 1_000_000);
    }

    /**
     * Wait until the login has an outcome: the logout link (session open) or
```

- [ ] **Step 4 : le voir passer**

Run: `php vendor/bin/phpunit tests/Unit/Pages/BackOfficeLoginOutcomeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t3.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t3.txt`
Expected: `OK (13 tests, 22 assertions)`. Puis la suite complète : `OK (603 tests, …)`.

- [ ] **Step 5 : commit**

```bash
git add src/Pages/v9/BackOffice/Login/Page.php tests/Unit/Pages/BackOfficeLoginOutcomeTest.php
git commit -m "feat(login): échéance de l'issue de connexion (loginOutcomeDeadline), plafond = temps restant après le rechargement

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: `openBackOfficeCheckpoint()` attend l'issue jusqu'à l'échéance

**Goal:** le formulaire est envoyé dès qu'il reste `LOGIN_CHECK_MS + MIN_STEP_MS` (6 s) ; l'issue attend jusqu'à `min(début + $loginTimeoutMs, échéance globale) - LOGIN_CHECK_MS`, donc un rechargement court lui laisse le reste du budget ; l'échéance d'avant est rétablie après l'appel, même en erreur.

**Files:**
- Modify: `src/Tests/VisualTestsSuite.php` (docblock de `PAGE_RELOAD_MS` l.68-72, docblock de `$boBeforeLoginSubmit` l.84-88, docblock de `openBackOfficeCheckpoint()` l.497-505, `$deadline` l.547, closure l.563-566, `capBackOfficeWaits()` l.656-678)
- Modify: `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php` (double `login()` l.164-219 ; tests l.497-522, 590-606, 707-774, 776-794, 796-817, 887-912)

**Acceptance Criteria:**
- [ ] La closure `$boBeforeLoginSubmit` lève `BackOfficeTimeoutException` (« … (N s au total). ») s'il reste moins de `LOGIN_CHECK_MS + MIN_STEP_MS`, sinon pose `$login->loginOutcomeDeadline = min($start + $loginTimeoutMs, $deadline) - self::LOGIN_CHECK_MS`. Elle n'écrit plus `loginOutcomeTimeout`.
- [ ] `capBackOfficeWaits()` sauvegarde `loginOutcomeDeadline` et la rétablit (navigations comprises) dans le `finally`, même en erreur.
- [ ] `PAGE_RELOAD_MS` reste, documentée comme plafond fixe du rechargement, hors du calcul. Docblock du budget et pire cas (échéance + 10 s) mis à jour.
- [ ] Tests : rechargement court → reste du budget à l'issue ; envoi à 6 s restantes, refus à 5,5 s ; pire cas = échéance + 10 s ; échéance rétablie après un échec ; un run sur la même instance retrouve 60 s sans échéance.
- [ ] Les tests du run (`init()` / `runSteps()`) passent sans modification.

**Verify:** `php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t4.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t4.txt` → `OK (55 tests, 135 assertions)`

**Steps:**

- [ ] **Step 1 : tests qui échouent**

Le double de connexion reproduit désormais `Login\Page::login()` étape par étape. Tests modifiés :

| Test | Avant | Après |
|---|---|---|
| `…logs_in_then_opens_the_dashboard_then_the_menu` | issue 10 000 | échéance 20 000, issue 20 000 ; échéance remise à `null` |
| `…turns_an_unseen_login_outcome_into_a_timeout` | — | + échéance remise à `null` |
| `…keeps_a_minimal_login_outcome_wait_under_a_low_ceiling` | issue 1 000 | échéance 0, issue 1 000 |
| `…caps_each_step_to_what_is_left_of_the_global_budget` | `submitCostMs` 14 000 ; issue 10 000 ; navigations 14 000 / 11 000 | rechargement 4 s, issue 4 s, constat 5 s ; issue 4 000 ; navigations 15 000 / 12 000 |
| `…caps_the_login_outcome_to_what_is_left` | lecture 4 s ; issue 6 000 | lecture 2 s ; échéance 20 000, issue 3 000 |
| `…does_not_submit_the_login_when_the_reload_no_longer_fits` | renommé `…does_not_submit_the_login_with_less_than_the_check_and_one_second_left` : budget 5 + 5 s, page de connexion 4,5 s | |
| `…stops_before_a_step_once_the_deadline_is_reached` | `submitCostMs` 25 000 | pire cas : budget 5 + 5 s, envoi à 6 s, rechargement 10 s, issue 1 s, constat 5 s → horloge 20 000 |
| `…restores_the_run_ceilings_even_on_error` | — | + échéance 123 rétablie |
| `…the_login_ceiling_hook_does_not_outlive_its_opening` | budget 10 + 15 s, page de connexion 12 s | budget 5 + 5 s, page de connexion 4,5 s ; + aucune échéance pendant le run |

Nouveaux : `…leaves_the_rest_of_the_budget_to_the_outcome_after_a_short_reload`, `…submits_the_login_once_the_check_and_one_second_are_left`.

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
            /** Plafonds posés par la suite (CommonPage::$navigationTimeout, Login\Page::$loginOutcomeTimeout). */
            public ?int $navigationTimeout = null;
            public int $loginOutcomeTimeout = 60000;
            public ?bool $loginOutcomeSeen = null;
```

par :

```php
            /** Plafonds posés par la suite (CommonPage::$navigationTimeout, Login\Page::$loginOutcomeDeadline). */
            public ?int $navigationTimeout = null;
            public int $loginOutcomeTimeout = 60000;
            public ?int $loginOutcomeDeadline = null;
            public ?bool $loginOutcomeSeen = null;
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
            public array $outcomeTimeouts = [];
            public array $logoutTimeouts = [];
            /** Durées simulées : navigation, envoi du formulaire (rechargement + issue + constat). */
            public int $navCostMs = 0;
            public int $submitCostMs = 0;
```

par :

```php
            public array $outcomeTimeouts = [];
            /** Échéance de l'issue vue à chaque envoi du formulaire. */
            public array $outcomeDeadlines = [];
            public array $logoutTimeouts = [];
            /**
             * Durées simulées : navigation ; rechargement après l'envoi (10 s au plus,
             * comme CommonPage::waitForPageReload()) ; apparition de l'issue ;
             * constat de la session (isLoggedIn(), 5 s au plus).
             */
            public int $navCostMs = 0;
            public int $reloadCostMs = 0;
            public int $outcomeCostMs = 0;
            public int $checkCostMs = 0;
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
            public function login($email = null, $password = null, $waitForNavigation = true): void
            {
                $this->log[] = 'login:submit';
                $this->outcomeTimeouts[] = $this->loginOutcomeTimeout;
                // Fidèle à Login\Page : rechargement (10 s) + issue (plafond) + isLoggedIn() (5 s)
                // au plus ; au-delà, l'issue n'est pas vue.
                $max = 10000 + $this->loginOutcomeTimeout + 5000;
                $this->loginOutcomeSeen = $this->outcome && $this->submitCostMs <= $max;
                $this->chrome->now += min($this->submitCostMs, $max);
            }
            public function isLoggedIn(): bool { $this->log[] = 'login:check'; return $this->ok; }
```

par :

```php
            public function login($email = null, $password = null, $waitForNavigation = true): void
            {
                $this->log[] = 'login:submit';
                $this->outcomeDeadlines[] = $this->loginOutcomeDeadline;
                // Fidèle à Login\Page::login() : rechargement (10 s au plus), puis issue
                // plafonnée par le temps restant avant l'échéance (1 s au moins), ou par
                // $loginOutcomeTimeout sans échéance ; au-delà du plafond, l'issue n'est pas vue.
                $this->chrome->now += min($this->reloadCostMs, 10000);
                $ceiling = $this->loginOutcomeDeadline === null
                    ? $this->loginOutcomeTimeout
                    : max(1000, $this->loginOutcomeDeadline - $this->chrome->now);
                $this->outcomeTimeouts[] = $ceiling;
                $this->loginOutcomeSeen = $this->outcome && $this->outcomeCostMs <= $ceiling;
                $this->chrome->now += min($this->outcomeCostMs, $ceiling);
            }
            public function isLoggedIn(): bool
            {
                $this->log[] = 'login:check';
                $this->chrome->now += min($this->checkCostMs, 5000);

                return $this->ok;
            }
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
        // Plafonds pendant l'ouverture, valeurs du run rétablies ensuite. L'issue de
        // la connexion a le plafond moins les 10 s du rechargement qui la précède et
        // les 5 s du constat (isLoggedIn()) qui la suit.
        $this->assertSame([10000], $login->outcomeTimeouts);
        $this->assertSame([15000], $login->navTimeouts);
        $this->assertSame([15000, 15000], $page->navTimeouts);
        $this->assertNull($page->navigationTimeout);
        $this->assertNull($login->navigationTimeout);
        $this->assertSame(60000, $login->loginOutcomeTimeout);
```

par :

```php
        // Plafonds pendant l'ouverture, valeurs du run rétablies ensuite. Échéance de
        // l'issue : début + 25 s - 5 s de constat (isLoggedIn()) ; rechargement
        // instantané : toute l'attente revient à l'issue.
        $this->assertSame([20000], $login->outcomeDeadlines);
        $this->assertSame([20000], $login->outcomeTimeouts);
        $this->assertSame([15000], $login->navTimeouts);
        $this->assertSame([15000, 15000], $page->navTimeouts);
        $this->assertNull($page->navigationTimeout);
        $this->assertNull($login->navigationTimeout);
        $this->assertNull($login->loginOutcomeDeadline);
        $this->assertSame(60000, $login->loginOutcomeTimeout);
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
            $this->assertStringContainsString('à la connexion dans le délai imparti (25 s)', $e->getMessage());
        }
        $this->assertSame(60000, $login->loginOutcomeTimeout);
        $this->assertNull($login->navigationTimeout);
```

par :

```php
            $this->assertStringContainsString('à la connexion dans le délai imparti (25 s)', $e->getMessage());
        }
        $this->assertSame(60000, $login->loginOutcomeTimeout);
        $this->assertNull($login->loginOutcomeDeadline);
        $this->assertNull($login->navigationTimeout);
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
        $s->openBackOfficeCheckpoint(['name' => 'picker'], 5000, 15000);

        // 5 s ne couvrent pas les 10 s du rechargement : 1 s d'attente de l'issue au minimum.
        $this->assertSame([1000], $login->outcomeTimeouts);
        $this->assertSame(60000, $login->loginOutcomeTimeout);
```

par :

```php
        $s->openBackOfficeCheckpoint(['name' => 'picker'], 5000, 15000);

        // Échéance de l'issue = début + 5 s - 5 s de constat : 1 s d'attente au minimum.
        $this->assertSame([0], $login->outcomeDeadlines);
        $this->assertSame([1000], $login->outcomeTimeouts);
        $this->assertSame(60000, $login->loginOutcomeTimeout);
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
        $login = $this->login($log);
        $login->navCostMs = 12000;    // page de connexion (plafond 15 s) : t = 12 s
        $login->submitCostMs = 14000; // rechargement + issue + constat (≤ 25 s) : t = 26 s
        $s = $this->suite(page: $page, login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts'], 25000, 15000);

        $this->assertSame([15000], $login->navTimeouts);
        // Reste 28 s à l'envoi : issue = min(25, 28) - 10 - 5 = 10 s.
        $this->assertSame([10000], $login->outcomeTimeouts);
        // Tableau de bord : reste 14 s ; menu (t = 29 s) : reste 11 s.
        $this->assertSame([14000, 11000], $page->navTimeouts);
```

par :

```php
        $login = $this->login($log);
        $login->navCostMs = 12000;    // page de connexion (plafond 15 s) : t = 12 s
        $login->reloadCostMs = 4000;  // t = 16 s
        $login->outcomeCostMs = 4000; // t = 20 s
        $login->checkCostMs = 5000;   // t = 25 s
        $s = $this->suite(page: $page, login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts'], 25000, 15000);

        $this->assertSame([15000], $login->navTimeouts);
        // Échéance de l'issue 20 s ; rechargement fini à 16 s : 4 s pour l'issue.
        $this->assertSame([4000], $login->outcomeTimeouts);
        // Tableau de bord : reste 15 s ; menu (t = 28 s) : reste 12 s.
        $this->assertSame([15000, 12000], $page->navTimeouts);
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
        // Navigation au plafond (15 s) puis lecture du formulaire (4 s) : reste 21 s à
        // l'envoi, moins que le plafond de connexion (25 s).
        $this->chrome->evalCostMs = 4000;
        $login = $this->login($log);
        $login->navCostMs = 15000;
        $s = $this->suite(page: $this->page($log), login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);

        // min(25, 21) - 10 - 5 = 6 s : rechargement, issue et constat tiennent dans le reste.
        $this->assertSame([6000], $login->outcomeTimeouts);
```

par :

```php
        // Navigation de 15 s puis lecture du formulaire (2 s) : envoi à t = 17 s, à
        // 3 s de l'échéance de l'issue (25 - 5 = 20 s).
        $this->chrome->evalCostMs = 2000;
        $login = $this->login($log);
        $login->navCostMs = 15000;
        $s = $this->suite(page: $this->page($log), login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);

        $this->assertSame([20000], $login->outcomeDeadlines);
        $this->assertSame([3000], $login->outcomeTimeouts);
    }

    public function test_open_checkpoint_leaves_the_rest_of_the_budget_to_the_outcome_after_a_short_reload(): void
    {
        // Rechargement d'1 s : l'issue reçoit tout le reste avant son échéance (16 s),
        // et un premier tableau de bord de 14 s est vu (l'ancien calcul donnait 10 s).
        $log = new \ArrayObject();
        $login = $this->login($log);
        $login->navCostMs = 3000;
        $login->reloadCostMs = 1000;
        $login->outcomeCostMs = 14000;
        $s = $this->suite(page: $this->page($log), login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker'], 25000, 15000);

        $this->assertSame([16000], $login->outcomeTimeouts);
        $this->assertTrue($login->loginOutcomeSeen);
        $this->assertSame(['login:page index', 'login:submit', 'login:check', 'page index'], $log->getArrayCopy());
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
    public function test_open_checkpoint_does_not_submit_the_login_when_the_reload_no_longer_fits(): void
    {
        $log = new \ArrayObject();
        // Échéance 10 + 15 = 25 s ; page de connexion en 12 s (plafond 15 s) : reste
        // 13 s < 10 s de rechargement + 5 s de constat + 1 s.
        $login = $this->login($log);
        $login->navCostMs = 12000;
        $s = $this->suite(page: $this->page($log), login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 10000, 15000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertSame("Le back-office n'a pas répondu dans le délai imparti (25 s au total).", $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $this->assertNotContains('login:submit', $log->getArrayCopy());
        $this->assertSame(60000, $login->loginOutcomeTimeout);
    }
```

par :

```php
    public function test_open_checkpoint_does_not_submit_the_login_with_less_than_the_check_and_one_second_left(): void
    {
        $log = new \ArrayObject();
        // Échéance 5 + 5 = 10 s ; page de connexion en 4,5 s : reste 5,5 s < 5 s de
        // constat + 1 s.
        $login = $this->login($log);
        $login->navCostMs = 4500;
        $s = $this->suite(page: $this->page($log), login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 5000, 5000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertSame("Le back-office n'a pas répondu dans le délai imparti (10 s au total).", $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $this->assertNotContains('login:submit', $log->getArrayCopy());
        $this->assertNull($login->loginOutcomeDeadline);
        $this->assertSame(60000, $login->loginOutcomeTimeout);
    }

    public function test_open_checkpoint_submits_the_login_once_the_check_and_one_second_are_left(): void
    {
        $log = new \ArrayObject();
        // Échéance 10 s ; page de connexion en 4 s : reste 6 s = constat + 1 s, envoi.
        $login = $this->login($log);
        $login->navCostMs = 4000;
        $s = $this->suite(page: $this->page($log), login: $login);

        $s->openBackOfficeCheckpoint(['name' => 'picker'], 5000, 5000);

        $this->assertSame(['login:page index', 'login:submit', 'login:check', 'page index'], $log->getArrayCopy());
        $this->assertSame([1000], $login->outcomeTimeouts);
    }
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
        $login = $this->login($log);
        $login->navCostMs = 15000;
        $login->submitCostMs = 25000; // 10 + 10 + 5 s au plus : t = 40 s, échéance atteinte
        $s = $this->suite(page: $page, login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts'], 25000, 15000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertSame("Le back-office n'a pas répondu dans le délai imparti (40 s au total).", $e->getMessage());
        }
        $this->assertNotContains('page index', $log->getArrayCopy());
        $this->assertSame([], $page->navTimeouts);
        $this->assertNull($page->navigationTimeout);
```

par :

```php
        $login = $this->login($log);
        // Pire cas : envoi à 6 s de l'échéance (10 s), rechargement au plafond de 10 s,
        // issue 1 s, constat 5 s : t = 20 s = échéance + 10 s (PAGE_RELOAD_MS).
        $login->navCostMs = 4000;
        $login->reloadCostMs = 10000;
        $login->outcomeCostMs = 1000;
        $login->checkCostMs = 5000;
        $s = $this->suite(page: $page, login: $login);

        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts'], 5000, 5000);
            $this->fail('BackOfficeTimeoutException attendue');
        } catch (BackOfficeTimeoutException $e) {
            $this->assertSame("Le back-office n'a pas répondu dans le délai imparti (10 s au total).", $e->getMessage());
        }
        $this->assertSame(20000, $this->chrome->now);
        $this->assertNotContains('page index', $log->getArrayCopy());
        $this->assertSame([], $page->navTimeouts);
        $this->assertNull($page->navigationTimeout);
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
        $login->navigationTimeout = 30000;
        $login->loginOutcomeTimeout = 45000;
        $s = $this->suite(page: $page, login: $login);
```

par :

```php
        $login->navigationTimeout = 30000;
        $login->loginOutcomeTimeout = 45000;
        $login->loginOutcomeDeadline = 123;
        $s = $this->suite(page: $page, login: $login);
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
        $this->assertSame(30000, $login->navigationTimeout);
        $this->assertSame(45000, $login->loginOutcomeTimeout);
    }
```

par :

```php
        $this->assertSame(30000, $login->navigationTimeout);
        $this->assertSame(45000, $login->loginOutcomeTimeout);
        // Échéance posée pour l'envoi (début + 25 - 5 s), celle d'avant rétablie ensuite.
        $this->assertSame([20000], $login->outcomeDeadlines);
        $this->assertSame(123, $login->loginOutcomeDeadline);
    }
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
        $login = $this->login($log = new \ArrayObject());
        $login->navCostMs = 12000;
        $page = $this->page($log);
        $s = $this->suite(page: $page, login: $login);
        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 10000, 15000);
```

par :

```php
        $login = $this->login($log = new \ArrayObject());
        $login->navCostMs = 4500;
        $page = $this->page($log);
        $s = $this->suite(page: $page, login: $login);
        try {
            $s->openBackOfficeCheckpoint(['name' => 'picker'], 5000, 5000);
```

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
        $this->assertSame(60000, end($login->outcomeTimeouts));
        $this->assertSame($fresh->getArrayCopy(), $log->getArrayCopy());
```

par :

```php
        $this->assertSame(60000, end($login->outcomeTimeouts));
        $this->assertNull(end($login->outcomeDeadlines));
        $this->assertSame($fresh->getArrayCopy(), $log->getArrayCopy());
```

- [ ] **Step 2 : les voir échouer**

Run: `php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t4.txt 2>&1; rtk proxy grep -E "^[0-9]+\)|^Tests:" /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t4.txt`
Expected: `Tests: 55, …, Errors: 1, Failures: 7`.
- erreur : `…submits_the_login_once_the_check_and_one_second_are_left` (l'ancien seuil de 16 s refuse l'envoi : `BackOfficeTimeoutException`) ;
- échecs : `…logs_in_then_opens_the_dashboard_then_the_menu`, `…keeps_a_minimal_login_outcome_wait_under_a_low_ceiling`, `…caps_each_step_to_what_is_left_of_the_global_budget`, `…caps_the_login_outcome_to_what_is_left`, `…leaves_the_rest_of_the_budget_to_the_outcome_after_a_short_reload`, `…stops_before_a_step_once_the_deadline_is_reached`, `…restores_the_run_ceilings_even_on_error` (aucune échéance posée).

`…does_not_submit_the_login_with_less_than_the_check_and_one_second_left` passe déjà (5,5 s < 16 s) : il fixe la borne basse du nouveau seuil.

- [ ] **Step 3 : implémentation**

Dans `src/Tests/VisualTestsSuite.php`, remplace :

```php
    /**
     * Rechargement attendu par Login\Page::login() avant l'issue de la connexion
     * (CommonPage::waitForPageReload(), 10 s fixes) : déduit du plafond de connexion.
     */
    private const PAGE_RELOAD_MS = 10000;
```

par :

```php
    /**
     * Plafond fixe du rechargement attendu par Login\Page::login() avant l'issue
     * de la connexion (CommonPage::waitForPageReload(), 10 s au plus). N'entre
     * plus dans le calcul du budget : l'issue attend jusqu'à son échéance
     * (Login\Page::$loginOutcomeDeadline), si bien qu'un rechargement court lui
     * laisse le reste. Il borne le dépassement du pire cas de
     * openBackOfficeCheckpoint().
     */
    private const PAGE_RELOAD_MS = 10000;
```

Dans `src/Tests/VisualTestsSuite.php`, remplace :

```php
    /**
     * Appelé par ensureBackOfficeLogin() juste avant l'envoi du formulaire :
     * openBackOfficeCheckpoint() y pose le plafond de l'issue selon le reste du
     * budget (null pendant un run).
     */
```

par :

```php
    /**
     * Appelé par ensureBackOfficeLogin() juste avant l'envoi du formulaire :
     * openBackOfficeCheckpoint() y pose l'échéance de l'issue
     * (Login\Page::$loginOutcomeDeadline) selon le budget (null pendant un run).
     */
```

Dans `src/Tests/VisualTestsSuite.php`, remplace :

```php
     * - envoi du formulaire : seulement s'il reste au moins 16 s
     *   (PAGE_RELOAD_MS + LOGIN_CHECK_MS + 1 s), car Login\Page::login() attend
     *   d'abord un rechargement de 10 s fixes (CommonPage::waitForPageReload()),
     *   et Login\Page::isLoggedIn() jusqu'à 5 s après l'issue ; l'issue de la
     *   connexion (Login\Page::$loginOutcomeTimeout) reçoit
     *   max(1 s, min($loginTimeoutMs, reste) - 10 s - 5 s). Si $loginTimeoutMs +
     *   $menuTimeoutMs < 17 s, le formulaire n'est donc jamais envoyé.
     * Pire cas : échéance + lectures JS et remplissage du formulaire (≤ 5 s
     * chacun), non plafonnés par ce budget.
```

par :

```php
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
```

Dans `src/Tests/VisualTestsSuite.php`, remplace :

```php
        $budgetMs = $loginTimeoutMs + $menuTimeoutMs;
        $deadline = $this->nowMs() + $budgetMs;
```

par :

```php
        $budgetMs = $loginTimeoutMs + $menuTimeoutMs;
        $start = $this->nowMs();
        $deadline = $start + $budgetMs;
```

Dans `src/Tests/VisualTestsSuite.php`, remplace :

```php
                    $this->boBeforeLoginSubmit = static function (object $login) use ($left, $loginTimeoutMs): void {
                        $rest = $left(PHP_INT_MAX, self::PAGE_RELOAD_MS + self::LOGIN_CHECK_MS + self::MIN_STEP_MS);
                        $login->loginOutcomeTimeout = max(self::MIN_STEP_MS, min($loginTimeoutMs, $rest) - self::PAGE_RELOAD_MS - self::LOGIN_CHECK_MS);
                    };
```

par :

```php
                    $this->boBeforeLoginSubmit = static function (object $login) use ($left, $start, $deadline, $loginTimeoutMs): void {
                        // Délai s'il ne reste pas de quoi attendre l'issue (1 s) puis la constater (5 s).
                        $left(PHP_INT_MAX, self::LOGIN_CHECK_MS + self::MIN_STEP_MS);
                        $login->loginOutcomeDeadline = min($start + $loginTimeoutMs, $deadline) - self::LOGIN_CHECK_MS;
                    };
```

Dans `src/Tests/VisualTestsSuite.php`, remplace :

```php
    /**
     * Note les plafonds d'avant openBackOfficeCheckpoint() (ceux du run) et
     * renvoie de quoi les rétablir. Les plafonds sont posés étape par étape.
     */
    private function capBackOfficeWaits(object $page, ?object $login): \Closure
    {
        $pageBefore = $page->navigationTimeout ?? null;
        $loginBefore = $login?->navigationTimeout ?? null;
        $outcomeBefore = $login?->loginOutcomeTimeout ?? null;
        if ($login !== null) {
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
```

par :

```php
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
```

- [ ] **Step 4 : les voir passer**

Run: `php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t4.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t4.txt`
Expected: `OK (55 tests, 135 assertions)`. Puis la suite complète : `OK (605 tests, …)`.

- [ ] **Step 5 : commit**

```bash
git add src/Tests/VisualTestsSuite.php tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php
git commit -m "feat(visual): openBackOfficeCheckpoint attend l'issue de connexion jusqu'à l'échéance, envoi dès 6 s restantes

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: nettoyage : jetons doublement encodés, docblock de `UNSTABLE_WARNING`, README

**Goal:** `redactUrls()` masque aussi les jetons doublement encodés ; le docblock orphelin retrouve `UNSTABLE_WARNING` ; le README documente l'API du sélecteur visuel.

**Files:**
- Modify: `src/Tests/VisualTestsSuite.php` (docblocks l.91-95 ; `redactUrls()` l.680-691, décalée par la tâche 4)
- Modify: `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php` (fournisseur `urlsToRedact()`)
- Modify: `README.md` (nouvelle sous-section avant « ## Run a suite against a throwaway shop », l.84)

**Acceptance Criteria:**
- [ ] `%2526token%253D…`, `%253Ftoken%253D…`, `%2526_token%253D…`, `%253F_token%253D…` sont masqués (`…`), la suite de l'URL après le jeton est gardée.
- [ ] Les 8 cas existants d'`urlsToRedact()` donnent le même résultat.
- [ ] Le docblock « Avertissement posé sur le test quand waitForStable() expire… » est juste au-dessus de `UNSTABLE_WARNING`, celui du budget de pixels juste au-dessus de `DEFAULT_MAX_DIFF_PIXELS`.
- [ ] README : sous-section anglaise `### Visual picker API` à la fin de « Visual regression runs » : séquence `openBackOfficeCheckpoint()` → `PageSnapshot::captureCurrent(TestsSuite::getPage())` → `closeBackOfficeSession()` → `resetBrowser()` ; `take()` pour une page FO avec l'environnement ; exceptions (`BackOfficeTimeoutException` à attraper avant `\RuntimeException`, `\LogicException`, `\InvalidArgumentException`) ; `getPrevious()` peut contenir l'URL brute ; `applyEnvironment()` / `clearEnvironment()`.

**Verify:** `php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t5.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t5.txt` → `OK (59 tests, 139 assertions)` ; `rtk proxy grep -n "^## \|^### " README.md` → `### Visual picker API` entre `### Back-office suites` et `## Run a suite against a throwaway shop`

**Steps:**

- [ ] **Step 1 : test qui échoue**

Dans `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php`, remplace :

```php
            'jeton en entité HTML' => ['http://shop.test/a?controller=X&amp;token=html123', 'http://shop.test/a?controller=X&amp;token=…'],
```

par :

```php
            'jeton en entité HTML' => ['http://shop.test/a?controller=X&amp;token=html123', 'http://shop.test/a?controller=X&amp;token=…'],
            'jeton doublement encodé %2526' => ['http://shop.test/r?back=x%2526token%253Dabc123', 'http://shop.test/r?back=x%2526token%253D…'],
            'jeton doublement encodé %253F' => ['http://shop.test/r?back=y%253Ftoken%253Dxyz789', 'http://shop.test/r?back=y%253Ftoken%253D…'],
            '_token doublement encodé, suite gardée' => ['http://shop.test/r?back=z%2526_token%253Dqrs456%2526id%253D7', 'http://shop.test/r?back=z%2526_token%253D…%2526id%253D7'],
            '_token doublement encodé en tête' => ['http://shop.test/r?back=%252Fadmin%253F_token%253Dmno321', 'http://shop.test/r?back=%252Fadmin%253F_token%253D…'],
```

- [ ] **Step 2 : le voir échouer**

Run: `php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php --filter redacts > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t5.txt 2>&1; rtk proxy grep -E "^[0-9]+\)|^Tests:" /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t5.txt`
Expected: `Tests: 12, Assertions: 12, Failures: 4` : les 4 nouveaux cas, jeton laissé en clair (`%2526`/`%253F`/`%253D` ne sont pas reconnus).

- [ ] **Step 3 : implémentation**

Dans `src/Tests/VisualTestsSuite.php`, remplace :

```php
    /**
     * Masque jetons (`token=`, `_token=`, encodés ou en entité HTML) et identifiants d'URL
     * (`scheme://user:pass@`) d'un message relayé à l'utilisateur.
     */
    private static function redactUrls(string $message): string
    {
        // Jeton en clair, encodé (%26token%3D, %3F_token%3D) ou en entité HTML (&amp;token=).
        $message = preg_replace('~((?:[?&;]|%26|%3F)_?token(?:=|%3D))[^&"\'\s%<>]+~i', '$1…', $message) ?? $message;
```

par :

```php
    /**
     * Masque jetons (`token=`, `_token=`, encodés une ou deux fois, ou en entité HTML) et
     * identifiants d'URL (`scheme://user:pass@`) d'un message relayé à l'utilisateur.
     */
    private static function redactUrls(string $message): string
    {
        // Jeton en clair, encodé (%26token%3D, %3F_token%3D), doublement encodé
        // (%2526token%253D, %253F_token%253D) ou en entité HTML (&amp;token=).
        $message = preg_replace('~((?:[?&;]|%26|%3F|%2526|%253F)_?token(?:=|%3D|%253D))[^&"\'\s%<>]+~i', '$1…', $message) ?? $message;
```

Dans `src/Tests/VisualTestsSuite.php`, remplace :

```php
    /** Avertissement posé sur le test quand waitForStable() expire (la capture est prise quand même). */
    /** Budget par défaut de pixels changés (cf. CommonPage::visualCheckpoint). */
    public const DEFAULT_MAX_DIFF_PIXELS = \PrestaFlow\Library\Pages\CommonPage::DEFAULT_MAX_DIFF_PIXELS;

    public const UNSTABLE_WARNING = 'Page non stabilisée après 5 s (images/polices encore en chargement) : capture prise quand même.';
```

par :

```php
    /** Budget par défaut de pixels changés (cf. CommonPage::visualCheckpoint). */
    public const DEFAULT_MAX_DIFF_PIXELS = \PrestaFlow\Library\Pages\CommonPage::DEFAULT_MAX_DIFF_PIXELS;

    /** Avertissement posé sur le test quand waitForStable() expire (la capture est prise quand même). */
    public const UNSTABLE_WARNING = 'Page non stabilisée après 5 s (images/polices encore en chargement) : capture prise quand même.';
```

Dans `README.md`, remplace :

```markdown
## Run a suite against a throwaway shop
```

par :

````markdown
### Visual picker API

The visual picker of the PrestaFlow app captures a page and lists its elements, so that a user can pick a checkpoint selector. It uses the run's own code, under short ceilings.

**Back-office page.** Open the checkpoint's page in the shared browser, capture it, log out, then close the browser:

```php
use PrestaFlow\Library\Exceptions\BackOfficeTimeoutException;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Visual\PageSnapshot;

TestsSuite::scopeBrowserFilesTo('picker-'.$jobId);
$browser = TestsSuite::getBrowser(force: true);
TestsSuite::applyEnvironment($browser, TestsSuite::getPage()); // Basic Auth, headers, cookies

$suite = new MyBackOfficeVisualSuite(loadGlobals: true, getBrowser: false);
try {
    $where = $suite->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts']);
    $result = (new PageSnapshot())->captureCurrent(TestsSuite::getPage());
} catch (BackOfficeTimeoutException $e) {
    // the back office did not answer in time: readable message
} catch (\RuntimeException $e) {
    // run error, readable message: credentials refused, unexpected page, menu entry not found
} finally {
    $suite->closeBackOfficeSession();
    TestsSuite::resetBrowser();
    TestsSuite::clearEnvironment();
}
```

- `openBackOfficeCheckpoint(array $checkpoint, int $loginTimeoutMs = 25000, int $menuTimeoutMs = 15000): string` logs in (unless `auth => false`), opens the back-office root, then the `menu` entry. It returns the path and controller of the opened page, never its token. The run's ceilings are restored before it returns, even on error.
- `PageSnapshot::captureCurrent(object $page, int $timeoutMs = 15000): SnapshotResult` captures the open tab without navigating or closing anything.
- `closeBackOfficeSession(int $timeoutMs = 5000): void` follows the logout link of an open session. It never throws.
- `TestsSuite::resetBrowser()` closes the browser.

**Front-office page.** `(new PageSnapshot())->take($url, $device)` starts a dedicated browser, applies the run's environment (`TestsSuite::applyEnvironment()`), opens the URL, captures the page and closes the browser. It throws `SnapshotException` when Chrome cannot start or the page does not load in time.

**Exceptions of `openBackOfficeCheckpoint()`.**

| Exception | When |
|---|---|
| `BackOfficeTimeoutException` | The back office did not answer within the budget. It extends `\RuntimeException`: catch it first. |
| `\RuntimeException` | Run error with a readable message (credentials refused, unexpected page, menu entry not found, session already open for an `auth => false` checkpoint). |
| `\LogicException` | The suite's `$area` is not `bo`. |
| `\InvalidArgumentException` | An `auth => false` checkpoint with a `menu`. |

Messages never carry a token (`token`, `_token`, plain, encoded or double-encoded) nor URL credentials. `getPrevious()` keeps the original cause, which may hold the raw URL: never show or log it.

**Environment.** `TestsSuite::applyEnvironment($browser, $page)` applies what `before()` applies to a run, in the same order: Basic Auth (`PRESTAFLOW_BASIC_USER` / `PRESTAFLOW_BASIC_PASS`), then the `PRESTAFLOW_EXTRA_HEADERS` headers on the connection and the page, then the `PRESTAFLOW_COOKIES` cookies. Values are read from the environment only. Without these variables, nothing is set. The headers stay in `TestsSuite::$extraHttpHeaders` for the pages created afterwards: call `TestsSuite::clearEnvironment()` when releasing the browser, so that a persistent worker does not keep them for the next job.

## Run a suite against a throwaway shop
````

- [ ] **Step 4 : le voir passer**

Run: `php vendor/bin/phpunit tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t5.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/t5.txt`
Expected: `OK (59 tests, 139 assertions)`.

Puis : `sed -n '95,99p' src/Tests/VisualTestsSuite.php` → chaque docblock au-dessus de sa constante ; `rtk proxy grep -n "^## \|^### " README.md` → `### Visual picker API` après `### Back-office suites`. Suite complète : `OK (609 tests, …)`.

- [ ] **Step 5 : commit**

```bash
git add src/Tests/VisualTestsSuite.php tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php README.md
git commit -m "chore(visual): jetons doublement encodés masqués, docblock de UNSTABLE_WARNING à sa place, README de l'API du sélecteur

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: livraison (coordinateur)

**Goal:** branche relue, verte en 8.4 et 8.1, PR vers `dev` ; merge, release et bump de l'app sur demande seulement.

**Files:** aucun fichier modifié (relecture, PR, merges).

**Acceptance Criteria:**
- [ ] Relecture du diff faite, points ci-dessous vérifiés.
- [ ] Suite complète verte en PHP 8.4 et 8.1 : `OK (609 tests, …)`.
- [ ] PR vers `dev` « Sélecteur visuel : environnement et connexion au plus juste », CI verte.
- [ ] Merge, release et bump seulement sur demande de l'utilisateur, en commits de merge.

**Verify:** `gh pr view --json title,baseRefName,state` → titre « Sélecteur visuel : environnement et connexion au plus juste », base `dev`

**Steps:**

- [ ] Relecture : `rtk proxy git diff origin/dev...HEAD > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/lib-branch.diff`, puis lecture du fichier. Points à vérifier :
  - `before()` inchangée ; les trois méthodes protégées gardent signature et visibilité ; aucune valeur d'environnement dans un message, un `fwrite` ou un journal (seul le message « JSON invalide, ignoré » existant) ;
  - `take()` : `applyEnvironment()` entre `createPage()` et `navigate()`, dans le `try` du `finally` qui ferme le navigateur ;
  - `Login\Page::login()` : sans échéance, `waitForLoginOutcome($this->loginOutcomeTimeout)` comme avant ;
  - `openBackOfficeCheckpoint()` n'écrit plus `loginOutcomeTimeout` ; `PAGE_RELOAD_MS` n'est plus lue que par les docblocks ;
  - les noms partagés avec l'app (section « Contexte implémenteur ») sont exacts.
- [ ] Suite complète en 8.4 : `php vendor/bin/phpunit > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full84.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full84.txt` → `OK (609 tests, …)`.
- [ ] Suite complète en 8.1 : `"$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit > /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full81.txt 2>&1; tail -3 /private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/full81.txt` → `OK (609 tests, …)`.
- [ ] Push de `feat/picker-environment` (`git push -u origin feat/picker-environment`), PR vers `dev` : `gh pr create --base dev --title "Sélecteur visuel : environnement et connexion au plus juste" --body-file <fichier du scratchpad>`. Corps : les trois volets (environnement, échéance de l'issue, nettoyage), le pire cas (échéance + 10 s), puis la ligne d'attribution `🤖 Generated with [Claude Code](https://claude.com/claude-code)`. CI verte.
- [ ] Sur demande seulement, dans cet ordre :
  1. merge de la PR en **commit de merge** (`gh pr merge <N> --merge`), sujet « Sélecteur visuel : environnement et connexion au plus juste (#N) » ;
  2. release : PR `dev` → `main` intitulée « Release: sélecteur visuel, environnement et connexion au plus juste (#N) », mergée en **commit de merge** (`gh pr merge <M> --merge`). **Jamais de rebase ni de squash** : une release rebasée réécrit les SHA de `main` et met la release suivante en conflit ;
  3. bump dans l'app (`/Users/jonathan/Documents/GitHub/PrestaFlow/app`, `prestaflow/php-library` en `dev-dev`) : `composer update prestaflow/php-library`, seul `composer.lock` change ; commit à chemin explicite (`git add composer.lock`).

---

## Auto-relecture : couverture de la spec

| Point de la spec | Tâche |
|---|---|
| §1 `applyEnvironment()` publique statique : Basic Auth → `$extraHttpHeaders['Authorization']`, en-têtes extra fusionnés par-dessus, connexion + page (`Network.enable`, `setExtraHTTPHeaders`), cookies | 1 (écart 1 : `object`) |
| §1 méthodes protégées gardées, en délégant ; `before()` même comportement | 1 (écart 2 ; `ExtraHeadersFromEnvTest` intact, test d'équivalence) |
| §1 `clearEnvironment()` vide `$extraHttpHeaders`, non appelée par le run | 1 |
| §1 valeurs de `Env::get` seulement, jamais dans un message ni un journal | 1 (code repris tel quel), 6 (relecture) |
| §2 `take()` appelle `applyEnvironment()` entre `createPage()` et `navigate()` ; sans variable, rien ; `captureCurrent()` inchangée | 2 |
| §3 `Login\Page::$loginOutcomeDeadline` (`?int`, `null`), plafond `max(1000, échéance − maintenant)` après le rechargement, sinon `$loginOutcomeTimeout` ; `nowMs()` protégée | 3 |
| §3 `openBackOfficeCheckpoint()` : échéance `min(début + $loginTimeoutMs, échéance globale) − LOGIN_CHECK_MS` posée dans `$boBeforeLoginSubmit` ; envoi dès `LOGIN_CHECK_MS + MIN_STEP_MS` ; rétablie par `capBackOfficeWaits()` | 4 (écart 3) |
| §3 `PAGE_RELOAD_MS` documentée, hors calcul ; docblock du budget et pire cas | 4 |
| §4 `redactUrls()` doublement encodé, `_token` compris, cas existants inchangés | 5 |
| §4 docblock replacé au-dessus de `UNSTABLE_WARNING` | 5 |
| §4 README « Visual picker API » (séquence BO, `take()`, exceptions, `getPrevious()`, `applyEnvironment()` / `clearEnvironment()`) | 5 |
| Tests : `applyEnvironment` (ordre, contenu, cookies, rien sans variable, `before()` passe par elle) ; `clearEnvironment` | 1 |
| Tests : `take()` applique avant `navigate` | 2 |
| Tests : `login()` avec échéance (horloge factice) / sans échéance 60 000 | 3 |
| Tests : rechargement court → reste à l'issue ; envoi dès 6 s ; échéance rétablie même en erreur | 4 |
| Tests : `redactUrls` doublement encodé | 5 |
| Livraison : PR → `dev` (merge commit), release `dev` → `main` (merge commit), bump app (`composer.lock` seul) | 6 |
