# Régression visuelle du back-office — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Capturer le back-office de PrestaShop (connexion, tableau de bord, produits, commandes, clients, modules) en 1.7.8.11, 8.2.8 et 9.2.0, anglais, desktop, dans le workflow `visual.yml` (spec : `docs/superpowers/specs/2026-10-01-visual-backoffice-design.md`).

**Architecture:** `VisualTestsSuite` gagne une zone `bo` : connexion automatique, checkpoints résolus par le menu latéral (lien à jeton), clés `menu` / `auth` / `hide`. `CommonPage::visualCheckpoint()` gagne `hide` (display:none) et le gel des transitions. `BackOfficePage::goToMenu()` lit le lien d'une entrée du menu. Une suite `BackOffice` et une étape du workflow l'utilisent. Rien ne change pour les suites `fo`.

**Tech Stack:** PHP 8.1+, chrome-php, PHPUnit (`vendor/bin/phpunit`), GitHub Actions, Flashlight (docker compose).

---

## Règles communes à toutes les tâches

- **Worktree** : `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual`, branche `feat/visual-backoffice` (partie de `origin/dev`). Ne **jamais** toucher `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library` (checkout de l'utilisateur) et ne pas créer/supprimer de branches autres que celle-ci (les refs sont partagées avec ce checkout).
- **Commits** : jamais `git add -A`, `git add .` ni `git commit -a` ; chemins explicites. Trailer obligatoire :
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`
- **Tests** : `vendor/bin/phpunit tests/Unit/...` ; suite unitaire complète : `vendor/bin/phpunit tests/Unit` (≈ 473 tests au départ, tous verts). TDD : écrire le test, le voir échouer pour la bonne raison, implémenter, le voir passer.
- **Style** : commentaires en français, concis, comme le code autour.
- **Boutiques locales** (docker du dépôt) : 1.7.8.11 sur `http://localhost:8017/` (BO `/admin-dev/`), 9.2.0 sur `http://localhost:8092/` (BO `/admin-dev/`). Identifiants de test Flashlight (ceux de `.github/workflows/live-smoke.yml`) : `admin@prestashop.com` / `prestashop`. Ne modifier ni les données ni la configuration des boutiques.

---

### Task 1: `visualCheckpoint()` — `hide` et gel des transitions

**Goal:** `CommonPage::visualCheckpoint()` accepte `array $hide = []` et `bool $freezeTransitions = false`, injecte les styles correspondants avant la capture et les retire après (même en cas d'échec de capture).

**Files:**
- Modify: `src/Pages/CommonPage.php` (`visualCheckpoint()` ~l.616, helpers ~l.449-490)
- Test: `tests/Unit/Visual/VisualCheckpointMasksTest.php`

**Acceptance Criteria:**
- [ ] Signature : `visualCheckpoint(string $name, ?string $selector = null, ?float $threshold = null, bool $fullPage = true, string $tag = 'auto', array $masks = [], ?int $maxDiffPixels = null, array $hide = [], bool $freezeTransitions = false)`.
- [ ] `hide` : un `<style id="pf-visual-hide">` avec, par sélecteur, `:is(SEL) { display: none !important; }` ; rien si la liste est vide.
- [ ] Gel : un `<style id="pf-visual-freeze">` avec `*, *::before, *::after { transition: none !important; animation: none !important; }`, injecté **avant** `settleAnimations()` ; rien si `false`.
- [ ] Les deux styles sont retirés après la capture, y compris quand la capture lève.
- [ ] Appels existants inchangés (paramètres ajoutés en fin, défauts neutres) ; tests existants verts.

**Verify:** `vendor/bin/phpunit tests/Unit/Visual` → vert.

**Steps:**

- [ ] **Step 1: Tests (rouges)** — dans `VisualCheckpointMasksTest` (le `makePage()` existant journalise chaque `evaluate()` dans `evaluatedLog` et sait faire lever la capture) :
```php
    public function test_hidden_elements_are_removed_from_layout_then_restored(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false, 'auto', [], null, ['.onboarding-popup', '.a, .b']);
        $js = implode("\n", $page->evaluatedLog);

        $this->assertStringContainsString('pf-visual-hide', $js);
        $this->assertStringContainsString(':is(.onboarding-popup) { display: none !important; }', $js);
        $this->assertStringContainsString(':is(.a, .b) { display: none !important; }', $js);
        $this->assertStringContainsString("getElementById('pf-visual-hide')", $js);
    }

    public function test_transitions_are_frozen_before_settling_then_restored(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false, 'auto', [], null, [], true);
        $log = $page->evaluatedLog;

        $freeze = array_key_first(array_filter($log, fn ($s) => str_contains($s, 'transition: none !important')));
        $settle = array_key_first(array_filter($log, fn ($s) => str_contains($s, \PrestaFlow\Library\Visual\PageScripts::SETTLE_ANIMATIONS)));
        $remove = array_key_first(array_filter($log, fn ($s) => str_contains($s, "getElementById('pf-visual-freeze')")));
        $this->assertNotNull($freeze);
        $this->assertNotNull($settle);
        $this->assertNotNull($remove);
        $this->assertLessThan($settle, $freeze);
        $this->assertLessThan($remove, $settle);
    }

    public function test_no_hide_nor_freeze_style_by_default(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false, 'auto', ['.price']);
        $js = implode("\n", $page->evaluatedLog);

        $this->assertStringNotContainsString('pf-visual-hide', $js);
        $this->assertStringNotContainsString('pf-visual-freeze', $js);
    }

    public function test_hide_and_freeze_styles_are_removed_when_the_capture_fails(): void
    {
        $page = $this->makePage(screenshotThrows: true);
        try {
            $page->visualCheckpoint('hdr', null, null, false, 'auto', [], null, ['.popup'], true);
            $this->fail('la capture devait lever');
        } catch (\RuntimeException) {
        }
        $js = implode("\n", $page->evaluatedLog);

        $this->assertStringContainsString("getElementById('pf-visual-hide')", $js);
        $this->assertStringContainsString("getElementById('pf-visual-freeze')", $js);
    }
```
(Si `SETTLE_ANIMATIONS` n'est pas évalué tel quel — vérifier `settleAnimations()` — repérer l'appel de gel par une sous-chaîne propre à ce script.)
Lancer `vendor/bin/phpunit tests/Unit/Visual/VisualCheckpointMasksTest.php` → 4 échecs.

- [ ] **Step 2: Implémentation** (`src/Pages/CommonPage.php`) :
  - ajouter deux helpers génériques et s'en servir pour les deux nouveaux styles :
```php
    /** Ajoute un <style id=…> (best-effort côté appelant) ; sans effet si $css est vide. */
    private function injectVisualStyle(string $id, string $css): void
    {
        if ($css === '') {
            return;
        }
        $this->getPage()->evaluate(sprintf(
            "(function(){var s=document.createElement('style');s.id=%s;s.textContent=%s;document.head.appendChild(s);})()",
            json_encode($id, JSON_THROW_ON_ERROR),
            json_encode($css, JSON_THROW_ON_ERROR)
        ))->getReturnValue();
    }

    private function removeVisualStyle(string $id): void
    {
        try {
            $this->getPage()->evaluate(sprintf(
                "(function(){var s=document.getElementById('%s');if(s){s.remove();}})()",
                $id
            ))->getReturnValue();
        } catch (\Throwable $e) {
            // best-effort : la page a pu changer entre-temps
        }
    }
```
  - constantes :
```php
    /** Gel des transitions et animations CSS pendant la capture (mise en page du menu BO 9.x). */
    private const FREEZE_CSS = '*, *::before, *::after { transition: none !important; animation: none !important; }';
```
  - dans `visualCheckpoint()`, remplacer
```php
        $this->settleAnimations();
        $this->applyVisualMasks($masks);
        try {
```
    par
```php
        if ($freezeTransitions) {
            // Avant le gel des animations : la capture pleine page déclenche elle-même
            // des `resize`, et une transition lancée à ce moment fausserait l'image.
            $this->injectVisualStyle('pf-visual-freeze', self::FREEZE_CSS);
        }
        $this->settleAnimations();
        $this->applyVisualMasks($masks);
        $hide = array_values(array_filter(array_map('trim', $hide)));
        // Une règle par sélecteur, comme les masques ; `display: none` libère la place
        // (popups, fonds de modale), là où un masque laisse une zone vide grisée.
        $this->injectVisualStyle('pf-visual-hide', implode("\n", array_map(fn ($h) => ":is({$h}) { display: none !important; }", $hide)));
        try {
```
    et dans le `finally` qui appelle `removeVisualMasks($masks)`, ajouter après :
```php
            if ($hide !== []) {
                $this->removeVisualStyle('pf-visual-hide');
            }
            if ($freezeTransitions) {
                $this->removeVisualStyle('pf-visual-freeze');
            }
```
  - mettre à jour le docblock de `visualCheckpoint()` : une ligne pour `$hide`, une pour `$freezeTransitions`.

- [ ] **Step 3:** `vendor/bin/phpunit tests/Unit` → tout vert.

- [ ] **Step 4: Commit**
```bash
git add src/Pages/CommonPage.php tests/Unit/Visual/VisualCheckpointMasksTest.php
git commit -m "feat(visual): éléments masqués par display:none et gel des transitions pendant la capture

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: `BackOfficePage::goToMenu()`

**Goal:** Naviguer vers une entrée du menu latéral à partir d'une liste de sélecteurs séparés par des virgules : la première entrée présente donne son lien (qui porte le jeton).

**Files:**
- Modify: `src/Pages/BackOfficePage.php` (près de `goToSubMenu()` ~l.151)
- Test: `tests/Unit/Pages/BackOfficeGoToMenuTest.php` (nouveau ; vérifier le dossier des tests de pages existants avec `ls tests/Unit` et s'y aligner)

**Acceptance Criteria:**
- [ ] `goToMenu(string $selectors): string` : découpe sur `,`, ignore les vides ; pour chaque sélecteur, lit `document.querySelector(sel+' a') || document.querySelector(sel)` → `href` ; navigue vers le premier `href` non vide (`navigate($href)->waitForNavigation(DOM_CONTENT_LOADED)`) et le renvoie.
- [ ] Aucune entrée trouvée → `\RuntimeException` dont le message contient la liste de sélecteurs.
- [ ] Une ancre `#collapse-…` (entrée parente) n'est pas un lien valable : un `href` qui se termine par `#…` sans chemin propre (même URL que la page + ancre) est ignoré — règle : ignorer un `href` dont la partie avant `#` est égale à l'URL courante.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeGoToMenuTest.php` → vert.

**Steps:**

- [ ] **Step 1: Test (rouge).** Construire un `BackOfficePage` testable sur le modèle des pages factices de `tests/Unit/Visual/VisualCheckpointMasksTest.php` (sous-classe anonyme qui surcharge `getPage()` ; constructeur : lire celui de `BackOfficePage` et passer des globals minimales `['BO' => ['URL' => 'http://shop.test/admin-dev/']]`). La page factice :
  - `evaluate($js)` : si `$js` contient `location.href` → renvoie `'http://shop.test/admin-dev/index.php?controller=AdminDashboard&token=t'` ; sinon renvoie l'URL configurée pour le sélecteur présent dans `$js` (tableau `$links` sélecteur → href|null) ;
  - `navigate($url)` : journalise `$url` et renvoie un objet dont `waitForNavigation()` ne fait rien.
```php
    public function test_first_present_entry_wins(): void
    {
        $page = $this->page(['#subtab-AdminDashboard' => null, '#tab-AdminDashboard' => 'http://shop.test/admin-dev/index.php?controller=AdminDashboard&token=abc']);

        $href = $page->goToMenu('#subtab-AdminDashboard, #tab-AdminDashboard');

        $this->assertSame('http://shop.test/admin-dev/index.php?controller=AdminDashboard&token=abc', $href);
        $this->assertSame([$href], $page->navigated);
    }

    public function test_parent_anchor_is_not_a_link(): void
    {
        $page = $this->page(['#subtab-AdminCatalog' => 'http://shop.test/admin-dev/index.php?controller=AdminDashboard&token=t#collapse-2']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('#subtab-AdminCatalog');
        $page->goToMenu('#subtab-AdminCatalog');
    }

    public function test_missing_entry_fails_with_the_selectors(): void
    {
        $page = $this->page([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('#subtab-AdminOrders, #nope');
        $page->goToMenu('#subtab-AdminOrders, #nope');
    }
```

- [ ] **Step 2: Implémentation**
```php
    /**
     * Va sur une entrée du menu latéral. Les URL du back-office portent un jeton :
     * on lit le lien de l'entrée (qui le contient) au lieu de construire l'URL.
     * $selectors : liste séparée par des virgules, la première entrée présente
     * gagne (ex. `#subtab-AdminDashboard, #tab-AdminDashboard` selon la version).
     * Une entrée parente n'a qu'une ancre `#collapse-N` sur la page courante :
     * ce n'est pas un lien.
     *
     * @return string l'URL visitée
     */
    public function goToMenu(string $selectors): string
    {
        $page = $this->getPage();
        $current = (string) $page->evaluate('location.href')->getReturnValue();
        $currentBase = explode('#', $current, 2)[0];

        foreach (array_filter(array_map('trim', explode(',', $selectors))) as $selector) {
            $sel = json_encode($selector, JSON_THROW_ON_ERROR);
            $href = $page->evaluate(sprintf(
                '(function(){var e=document.querySelector(%s+" a")||document.querySelector(%s);return e&&e.href?e.href:null;})()',
                $sel,
                $sel
            ))->getReturnValue();
            if (!is_string($href) || $href === '' || (str_contains($href, '#') && explode('#', $href, 2)[0] === $currentBase)) {
                continue;
            }
            $page->navigate($href)->waitForNavigation(\HeadlessChromium\Page::DOM_CONTENT_LOADED);

            return $href;
        }

        throw new \RuntimeException(sprintf('Entrée du menu introuvable : %s', $selectors));
    }
```
(Attention : `explode(',', …)` coupe aussi une virgule interne à un sélecteur `:is(a, b)` ; les sélecteurs de menu n'en ont pas — le documenter dans le docblock.)

- [ ] **Step 3:** `vendor/bin/phpunit tests/Unit` → vert.

- [ ] **Step 4: Commit**
```bash
git add src/Pages/BackOfficePage.php tests/Unit/Pages/BackOfficeGoToMenuTest.php
git commit -m "feat(bo): goToMenu, navigation par le lien à jeton d'une entrée du menu

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: `VisualTestsSuite` — zone `bo`

**Goal:** Une suite visuelle avec `$area = 'bo'` capture le back-office : checkpoints non connectés d'abord, connexion une seule fois, navigation par `menu`, `hide` et gel des transitions ; les suites `fo` ne changent pas.

**Files:**
- Modify: `src/Tests/VisualTestsSuite.php`
- Test: `tests/Unit/Visual/VisualTestsSuiteTest.php`, `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php` (nouveau)

**Acceptance Criteria:**
- [ ] `protected string $area = 'fo';` (valeurs `fo` | `bo` ; autre valeur → `\InvalidArgumentException` dans `init()`), accesseur `public function area(): string`.
- [ ] `protected ?bool $freezeTransitions = null;` et `public function freezesTransitions(): bool` = `$freezeTransitions ?? ($this->area === 'bo')`.
- [ ] `normalize()` ajoute `'menu' => null, 'auth' => true, 'hide' => []`.
- [ ] `public function orderedCheckpoints(): array` : en `bo`, les checkpoints `auth === false` d'abord, ordre déclaré conservé dans chaque groupe ; en `fo`, `checkpoints()` tel quel. `init()` itère dessus (après le filtre `PRESTAFLOW_VISUAL_ONLY`).
- [ ] `importVisualPage()` : `fo` → `importPage('FrontOffice')` (inchangé) ; `bo` → `importPage('BackOffice')` et `importPage('BackOffice\Login')`. Vérifier les clés produites dans `$this->pages` (cf. `importPage()` dans `TestsSuite`, ex. `backOfficePage`, `backOfficeLoginPage`).
- [ ] En `bo`, chaque checkpoint :
  - `auth === true` → connexion paresseuse unique via la page `BackOffice\Login` : `goToPage('index')`, `login()` (identifiants des globals `BO_EMAIL`/`BO_PASSWD`), puis `isLoggedIn()` ; échec → `\RuntimeException('Connexion au back-office impossible : …')` pour ce checkpoint et les suivants connectés (le message de la 1re erreur est mémorisé, pas de nouvel essai).
  - clé de navigation `($cp['auth'] ? 'in' : 'out').'|'.($cp['menu'] ?? '')` à la place de l'URL pour le raccourci « même page que le précédent » ;
  - navigation : `goToPage('index')` sur la page `BackOffice`, puis `goToMenu($cp['menu'])` si `menu` est posé ;
  - ensuite, exactement le même déroulé qu'en `fo` (stabilité + avertissement, `waitFor`, `scrollBelow`/`scrollToTop`, `visualCheckpoint`).
- [ ] `visualCheckpoint()` reçoit `$cp['hide']` et `$this->freezesTransitions()` en `fo` comme en `bo` (en `fo`, `hide` vide et gel faux par défaut → comportement identique).
- [ ] En `bo`, `path`/`paths` sont ignorés (pas de `resolveUrl`) ; un checkpoint n'est sauté que par `excludeDevices`.
- [ ] Le déroulé commun (stabilité → capture) est factorisé dans une méthode privée appelée par les deux zones ; aucun test `fo` existant ne change.

**Verify:** `vendor/bin/phpunit tests/Unit` → vert.

**Steps:**

- [ ] **Step 1: Tests (rouges)** — `tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php` :
```php
<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\VisualTestsSuite;

final class VisualTestsSuiteBackOfficeTest extends TestCase
{
    private function suite(string $area = 'bo', ?bool $freeze = null): VisualTestsSuite
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends VisualTestsSuite {
            protected array $devices = ['desktop'];
            protected array $locales = ['en'];
            protected array $checkpoints = [
                ['name' => 'dashboard', 'menu' => '#subtab-AdminDashboard, #tab-AdminDashboard'],
                ['name' => 'login', 'auth' => false],
                ['name' => 'orders', 'menu' => '#subtab-AdminOrders', 'hide' => ['.popup']],
                ['name' => 'login-again', 'auth' => false],
            ];
            protected function importVisualPage(): void {}
            public function setArea(string $area, ?bool $freeze): void { $this->area = $area; $this->freezeTransitions = $freeze; }
        };
        $suite->setArea($area, $freeze);
        $suite->setGlobals(['PS_VERSION' => '9.2.0', 'LOCALE' => 'en', 'DEVICE' => 'desktop',
            'FO' => ['URL' => 'http://shop.test/', 'EMAIL' => '', 'PASSWD' => ''],
            'BO' => ['URL' => 'http://shop.test/admin-dev/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x']]);

        return $suite;
    }

    public function test_normalize_adds_backoffice_defaults(): void
    {
        $cp = VisualTestsSuite::normalize(['name' => 'x']);

        $this->assertNull($cp['menu']);
        $this->assertTrue($cp['auth']);
        $this->assertSame([], $cp['hide']);
    }

    public function test_logged_out_checkpoints_run_first_keeping_declared_order(): void
    {
        $names = array_column($this->suite()->orderedCheckpoints(), 'name');

        $this->assertSame(['login', 'login-again', 'dashboard', 'orders'], $names);
    }

    public function test_front_office_keeps_declared_order(): void
    {
        $names = array_column($this->suite('fo')->orderedCheckpoints(), 'name');

        $this->assertSame(['dashboard', 'login', 'orders', 'login-again'], $names);
    }

    public function test_transitions_are_frozen_by_default_in_the_back_office_only(): void
    {
        $this->assertTrue($this->suite('bo')->freezesTransitions());
        $this->assertFalse($this->suite('fo')->freezesTransitions());
        $this->assertFalse($this->suite('bo', false)->freezesTransitions());
        $this->assertTrue($this->suite('fo', true)->freezesTransitions());
    }

    public function test_init_registers_backoffice_checkpoints_in_run_order(): void
    {
        $s = $this->suite();
        $s->init();
        $titles = array_column(array_values($s->tests), 'title');

        $this->assertSame([
            'capture visuelle : login', 'capture visuelle : login-again',
            'capture visuelle : dashboard', 'capture visuelle : orders',
        ], $titles);
    }

    public function test_unknown_area_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->suite('admin')->init();
    }
}
```
(Adapter l'accès à `$s->tests` à ce que fait `VisualTestsSuiteTest::test_init_registers_tests_and_skips`.)
Dans `VisualTestsSuiteTest::test_normalize_defaults` (l.188), ajouter les 3 clés attendues. Lancer → échecs.

- [ ] **Step 2: Implémentation** (`src/Tests/VisualTestsSuite.php`) :
  - propriétés + docblocks :
```php
    /**
     * Zone capturée : 'fo' (front-office, chemins d'URL) ou 'bo' (back-office :
     * connexion automatique, checkpoints résolus par le menu latéral, car toute
     * URL d'admin porte un jeton).
     */
    protected string $area = 'fo';

    /** Gel des transitions CSS pendant la capture ; null = actif en 'bo' seulement (références 'fo' inchangées). */
    protected ?bool $freezeTransitions = null;

    private ?string $boLoginError = null;
    private bool $boLoggedIn = false;
```
  - `normalize()` : ajouter `'menu' => null, 'auth' => true, 'hide' => [],` au tableau de défauts, puis `$cp['auth'] = (bool) $cp['auth']; $cp['hide'] = array_values(array_filter(array_map('trim', (array) $cp['hide'])));`.
  - méthodes :
```php
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
```
  - `init()` :
    - en tête (après `parent::init()`), valider l'area : `if (!in_array($this->area, ['fo', 'bo'], true)) { throw new \InvalidArgumentException(sprintf('%s : area « %s » inconnue (fo, bo)', static::class, $this->area)); }` ;
    - remplacer `$checkpoints = $this->checkpoints();` par `$checkpoints = $this->orderedCheckpoints();` (le filtre ONLY garde l'ordre) ;
    - `$page = $this->pages[$this->area === 'bo' ? 'backOfficePage' : 'frontOfficePage'] ?? null;` (clé à vérifier) et `$login = $this->pages['backOfficeLoginPage'] ?? null;` ;
    - dans la boucle : si `bo`, ne pas appeler `resolvePath()` ; sauter seulement sur `excludeDevices` ; la « cible » est `($cp['auth'] ? 'in' : 'out').'|'.($cp['menu'] ?? '')` ; dans la closure, avant la navigation : `if ($cp['auth']) { $this->ensureBackOfficeLogin($login); }`, puis, si la cible diffère de `$this->lastVisualUrl` : `$page->goToPage('index'); if ($cp['menu'] !== null) { $page->goToMenu($cp['menu']); }` ;
    - si `fo` : inchangé (`resolvePath` / `resolveUrl` / `goToUrl`) ;
    - extraire le reste de la closure (de `waitForStable()` à `visualCheckpoint(...)`) dans `private function captureCheckpoint(object $page, array $cp, string $scope, bool $sameTarget): void`, et passer `$cp['hide'], $this->freezesTransitions()` en fin d'appel à `visualCheckpoint()`.
  - connexion :
```php
    /** Connexion unique au back-office avant le premier checkpoint connecté ; une erreur est rejouée sans nouvel essai. */
    private function ensureBackOfficeLogin(?object $login): void
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
                $login->login();
                if (!$login->isLoggedIn()) {
                    throw new \RuntimeException('identifiants refusés ou page inattendue');
                }
                $this->boLoggedIn = true;

                return;
            } catch (\Throwable $e) {
                $this->boLoginError = $e->getMessage();
            }
        }

        throw new \RuntimeException('Connexion au back-office impossible : '.$this->boLoginError);
    }
```
  - `importVisualPage()` :
```php
    protected function importVisualPage(): void
    {
        if ($this->area === 'bo') {
            $this->importPage('BackOffice');
            $this->importPage('BackOffice\Login');

            return;
        }
        $this->importPage('FrontOffice');
    }
```
  - mettre à jour le docblock de la classe (zone `bo`, clés `menu` / `auth` / `hide`).

- [ ] **Step 3:** `vendor/bin/phpunit tests/Unit` → vert (tests `fo` existants inchangés).

- [ ] **Step 4: Commit**
```bash
git add src/Tests/VisualTestsSuite.php tests/Unit/Visual/VisualTestsSuiteTest.php tests/Unit/Visual/VisualTestsSuiteBackOfficeTest.php
git commit -m "feat(visual): zone back-office (connexion, navigation par le menu, hide, gel des transitions)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Suite `BackOffice` et vérification locale

**Goal:** La suite `src/Tests/Suites/Visual/BackOffice.php` (6 checkpoints de la spec) passe deux fois de suite, en local, sur 1.7 (8017) et 9.2 (8092) : premier passage = premières captures, second = comparaison sans écart.

**Files:**
- Create: `src/Tests/Suites/Visual/BackOffice.php`
- Test: `tests/Unit/Visual/BackOfficeSuiteTest.php` (nouveau ; s'aligner sur `tests/Unit/Visual/FrontOfficeSuitesTest.php`)

**Acceptance Criteria:**
- [ ] `area = 'bo'`, `devices = ['desktop']`, `locales = ['en']`, 6 checkpoints dans cet ordre : `login` (`auth => false`), `dashboard`, `products`, `orders`, `customers`, `modules`, avec menus, masques et `hide` de la spec.
- [ ] Test unitaire : définition (noms, `auth`, menus, area, devices, locales) ; `orderedCheckpoints()` met `login` en premier.
- [ ] Vérification locale : sur 8017 puis 8092, deux passages ; le 2e : 6 PASS ; captures regardées (pages connectées, pas « Invalid token », pas la page de connexion hors `login`). Sorties et références locales supprimées ensuite.

**Verify:** `vendor/bin/phpunit tests/Unit` → vert ; second passage local : `Tests: 6 passed` (ou équivalent) sur chaque boutique.

**Steps:**

- [ ] **Step 1: Test (rouge)** :
```php
<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\Suites\Visual\BackOffice;

final class BackOfficeSuiteTest extends TestCase
{
    public function test_definition(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends BackOffice {
            protected function importVisualPage(): void {}
        };
        $cps = $suite->checkpoints();

        $this->assertSame('bo', $suite->area());
        $this->assertSame(['desktop'], $suite->devices());
        $this->assertSame(['en'], $suite->locales());
        $this->assertSame(['login', 'dashboard', 'products', 'orders', 'customers', 'modules'], array_column($cps, 'name'));
        $this->assertFalse($cps[0]['auth']);
        $this->assertSame('#subtab-AdminDashboard, #tab-AdminDashboard', $cps[1]['menu']);
        $this->assertSame('#subtab-AdminModulesSf', $cps[5]['menu']);
        $this->assertContains('.onboarding-popup', $cps[1]['hide']);
        $this->assertSame('login', $suite->orderedCheckpoints()[0]['name']);
    }
}
```

- [ ] **Step 2: Suite** :
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\Visual;

use PrestaFlow\Library\Tests\VisualTestsSuite;

/**
 * Back-office de PrestaShop (1.7.8, 8.2, 9.2), en anglais, sur desktop.
 *
 * Les URL d'admin portent un jeton : chaque page est atteinte par son entrée du
 * menu latéral. Le tableau de bord s'appelle `#tab-AdminDashboard` en 1.7 et
 * `#subtab-AdminDashboard` ensuite. Un masque absent d'une version est sans effet,
 * donc une même liste couvre les trois versions.
 */
class BackOffice extends VisualTestsSuite
{
    protected string $area = 'bo';

    protected array $devices = ['desktop'];

    protected array $locales = ['en'];

    protected array $checkpoints = [
        // Version affichée sous le logo en 1.7.
        ['name' => 'login', 'auth' => false, 'masks' => ['#login-header .text-center']],
        ['name' => 'dashboard', 'menu' => '#subtab-AdminDashboard, #tab-AdminDashboard',
            // Zones de widgets (chiffres, graphiques, actualités distantes) et période choisie.
            'masks' => ['#total_notif_number_wrapper', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar',
                '#hookDashboardZoneOne', '#hookDashboardZoneTwo', '#hookDashboardZoneThree', '#calendar_form'],
            // Popup d'onboarding animée de la 1.7, et son fond.
            'hide' => ['.onboarding-popup', '.modal-backdrop']],
        ['name' => 'products', 'menu' => '#subtab-AdminProducts',
            'masks' => ['#total_notif_number_wrapper', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar']],
        ['name' => 'orders', 'menu' => '#subtab-AdminOrders',
            'masks' => ['#total_notif_number_wrapper', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar',
                '.kpi-container', 'td.column-date_add']],
        ['name' => 'customers', 'menu' => '#subtab-AdminCustomers',
            'masks' => ['#total_notif_number_wrapper', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar',
                '.kpi-container', 'td.column-date_add', 'td.column-connect', '#customersShowcaseCard']],
        ['name' => 'modules', 'menu' => '#subtab-AdminModulesSf',
            'masks' => ['#total_notif_number_wrapper', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar',
                '.notification-counter']],
    ];
}
```
`vendor/bin/phpunit tests/Unit` → vert.

- [ ] **Step 3: Vérification locale** — pour chaque boutique (`PORT=8017` puis `PORT=8092`, version `1.7.8.11` puis `9.2.0`), deux fois :
```bash
PRESTAFLOW_FO_URL=http://localhost:$PORT/ PRESTAFLOW_BO_URL=http://localhost:$PORT/admin-dev/ \
PRESTAFLOW_BO_EMAIL=admin@prestashop.com PRESTAFLOW_BO_PASSWD=prestashop \
PRESTAFLOW_PS_VERSION=$VERSION PRESTAFLOW_DEVICE=desktop PRESTAFLOW_LOCALE=en PRESTAFLOW_CDP_TIMEOUT=15000 \
php bin/prestaflow run src/Tests/Suites/Visual/BackOffice.php
```
  - 1er passage : 6 `baseline` (ou PASS si des références existaient déjà — partir d'un `visual-baseline/` sans fichier `back-office.*`) ; 2e passage : 6 PASS.
  - Ouvrir les 6 images de `visual-baseline/back-office.*` de chaque version (outil Read sur les PNG) : pages connectées, bonne page, masques visibles là où prévu.
  - Si un checkpoint échoue au 2e passage, identifier la zone qui change (image de diff dans `prestaflow/`) et ajouter le masque ou le `hide` correspondant dans la suite, puis recommencer les deux passages. Noter chaque ajout dans le rapport.
  - Nettoyer : supprimer `visual-baseline/back-office.*` et le dossier `prestaflow/` produits (ne pas toucher aux autres fichiers de `visual-baseline/`, vérifier avec `git status` qu'aucun fichier suivi n'a changé).

- [ ] **Step 4: Commit**
```bash
git add src/Tests/Suites/Visual/BackOffice.php tests/Unit/Visual/BackOfficeSuiteTest.php
git commit -m "feat(visual): suite BackOffice (connexion, tableau de bord, produits, commandes, clients, modules)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Workflow `visual.yml` et README

**Goal:** Le workflow lance la suite `BackOffice` une fois par version (1.7.8.11, 8.2.8, 9.2.0 classic) ; le README documente la zone `bo`.

**Files:**
- Modify: `.github/workflows/visual.yml`
- Modify: `README.md` (section « Visual regression runs »)

**Acceptance Criteria:**
- [ ] Matrice : `backoffice: true` sur les lignes ps17 (8017), ps82 (8082), ps92 classic (8093) ; `false` (ou absent) sur ps92 hummingbird (8092).
- [ ] Nouvelle étape « Run the back-office visual suite » après l'étape front-office, `if: ${{ !cancelled() && matrix.backoffice }}` (elle tourne même si l'étape front-office a échoué), env : `PRESTAFLOW_FO_URL`, `PRESTAFLOW_BO_URL`, `PRESTAFLOW_BO_EMAIL: admin@prestashop.com`, `PRESTAFLOW_BO_PASSWD: prestashop`, `PRESTAFLOW_PS_VERSION`, `PRESTAFLOW_DEVICE: desktop`, `PRESTAFLOW_LOCALE: en`, `PRESTAFLOW_CDP_TIMEOUT: '15000'` ; sortie `prestaflow/` copiée dans `visual-output/backoffice/` ; son propre code de sortie.
- [ ] En-tête du workflow mis à jour (back-office : 3 versions, anglais, desktop ; navigation par le menu ; mêmes cache et artefact).
- [ ] Noms des jobs inchangés.
- [ ] README : la zone `bo`, les clés `menu` / `auth` / `hide`, `freezeTransitions`, et les variables `PRESTAFLOW_BO_URL` / `PRESTAFLOW_BO_EMAIL` / `PRESTAFLOW_BO_PASSWD`.
- [ ] YAML valide.

**Verify:** `ruby -ryaml -e "YAML.load_file('.github/workflows/visual.yml')"` → sans erreur.

**Steps:**
- [ ] **Step 1:** Lire `.github/workflows/visual.yml` en entier. Ajouter `backoffice: true|false` à chaque ligne de `matrix.include`.
- [ ] **Step 2:** Après « Run the visual suite », ajouter :
```yaml
      # Back-office : une fois par version (il ne dépend pas du thème), en anglais
      # sur desktop. Les URL d'admin portent un jeton : la suite navigue par le menu.
      - name: Run the back-office visual suite
        if: ${{ !cancelled() && matrix.backoffice }}
        env:
          PRESTAFLOW_FO_URL: http://localhost:${{ matrix.port }}/
          PRESTAFLOW_BO_URL: http://localhost:${{ matrix.port }}/admin-dev/
          PRESTAFLOW_BO_EMAIL: admin@prestashop.com
          PRESTAFLOW_BO_PASSWD: prestashop
          PRESTAFLOW_PS_VERSION: ${{ matrix.ps }}
          PRESTAFLOW_DEVICE: desktop
          PRESTAFLOW_LOCALE: en
          PRESTAFLOW_CDP_TIMEOUT: '15000'
        run: |
          status=0
          php bin/prestaflow run src/Tests/Suites/Visual/BackOffice.php || status=1
          if [ -d prestaflow ]; then
            mkdir -p visual-output/backoffice
            cp -R prestaflow/. visual-output/backoffice/
          fi
          exit $status
```
- [ ] **Step 3:** En-tête du workflow (commentaire du haut) : une puce « back-office ».
- [ ] **Step 4:** README, après le tableau des variables visuelles :
```markdown
### Back-office suites

A visual suite with `protected string $area = 'bo';` captures the back office. Every admin URL carries a token, so a checkpoint names a sidebar entry instead of a path:

| Key | Default | Effect |
|---|---|---|
| `menu` | `null` | Sidebar entry selector, or a comma-separated list (first present wins). The suite reads its link, which carries the token, and opens it. `null`: back-office root. |
| `auth` | `true` | `false`: captured logged out (the login page). Logged-out checkpoints run first; the suite then logs in once with `PRESTAFLOW_BO_EMAIL` / `PRESTAFLOW_BO_PASSWD`. |
| `hide` | `[]` | Selectors set to `display: none` during the capture (popups, modal backdrops). Works in both areas. |

CSS transitions are frozen during back-office captures (`protected ?bool $freezeTransitions`; `null` = on for `bo`, off for `fo`). See `src/Tests/Suites/Visual/BackOffice.php`.
```
- [ ] **Step 5:** Valider le YAML ; `vendor/bin/phpunit tests/Unit` → vert.
- [ ] **Step 6: Commit**
```bash
git add .github/workflows/visual.yml README.md
git commit -m "ci(visual): suite back-office sur 1.7.8.11, 8.2.8 et 9.2.0

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: PR et vérification en CI (coordinateur)

**Goal:** PR vers `dev`, 13 checks verts, captures BO des 3 versions vérifiées dans les artefacts.

**Acceptance Criteria:**
- [ ] PR `feat/visual-backoffice` → `dev` ouverte, 13/13 verts.
- [ ] Dans l'artefact de chaque ligne `backoffice`, `visual-output/backoffice/` contient les 6 captures, ce sont des pages connectées (hors `login`), sans « Invalid token ».
- [ ] Merge et release vers `main` sur demande de l'utilisateur ; après release, 2 ou 3 runs manuels comparent sans écart (BO compris).
- [ ] `.tasks.json` à jour.
