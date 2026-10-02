# Version PrestaShop portée par chaque objet : plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** chaque page, suite et scénario porte sa propre version de PrestaShop. Il n'y a plus de cache statique partagé, et une page sans version lève `InvalidVersionException`.

**Architecture :**
- Le trait `Traits\Version` passe d'un `public static $versions` à une propriété d'instance `protected array $versions`.
- Les pages prennent leur version dès le début de leur constructeur (`initVersion()`), avant de calculer sélecteurs et messages.
- `Scenario` et `ImportPage` se transmettent la version explicitement.

**Tech Stack :** PHP ^8.1, PHPUnit 10.5. Pas de navigateur dans ces tests.

**Spec :** `docs/superpowers/specs/2026-10-02-version-per-instance-design.md`

---

## Contexte pour l'implémenteur

- **Dépôt :** worktree `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual`, branche `fix/version-per-instance`. Toutes les commandes se lancent depuis ce dossier.
- **Binaires PHP :**
  - PHP 8.4 : `php` ;
  - PHP 8.1 : `"$HOME/Library/Application Support/Herd/bin/php81"` ;
  - PHP 8.2 : `"$HOME/Library/Application Support/Herd/bin/php82"` ;
  - PHP 8.3 : `"$HOME/Library/Application Support/Herd/bin/php83"`.
- **Suite complète :** `php vendor/bin/phpunit` (523 tests verts au départ).
- **Pourquoi le statique pose problème :** un statique déclaré dans un trait appartient à chaque classe qui fait `use` du trait.
  - `CommonPage` l'obtient via `Resolvers\Translations`, qui fait `use Version`.
  - `FrontOfficePage` refait `use Translations` : avant PHP 8.3 il partage le statique de `CommonPage`, à partir de 8.3 il a le sien.
  - Toute sous-classe qui ne refait pas `use` (BackOfficePage, pages v7/v8/v9, classes anonymes) partage le statique de `CommonPage` sur toutes les versions de PHP.
- **Commits :**
  - chemins explicites, jamais `git add -A`, `git add .` ni `git commit -a` ;
  - messages en français ;
  - chaque message se termine par la ligne `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>` (précédée d'une ligne vide).
- **Ne pas toucher** `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library` (checkout de l'utilisateur).

## Fichiers

| Fichier | Rôle |
|---|---|
| `src/Traits/Version.php` | Version par instance, import de l'exception, fin du repli `'8'` |
| `src/Pages/CommonPage.php` | `initVersion()`, appel dans le constructeur, retrait du contournement de `visualCheckpoint()` |
| `src/Pages/BackOfficePage.php`, `src/Pages/FrontOfficePage.php` | Appel de `initVersion()` en tête de constructeur |
| `src/Scenarios/Scenario.php` | Reprise du tableau de versions de la suite, analyse de `PATCH_VERSION` |
| `src/Traits/ImportPage.php` | Suppression de la recopie des versions (la page la prend à sa construction) |
| `tests/Unit/Pages/PageVersionInitTest.php` (nouveau) | Version prise à la construction, erreurs |
| `tests/Unit/Pages/PageVersionIsolationTest.php` (nouveau) | Deux pages de versions différentes ne se contaminent pas |
| `tests/Unit/Scenarios/ScenarioVersionTest.php` (nouveau) | Versions du scénario |
| `tests/Unit/Tests/SuiteVersionWiringTest.php` | + import de pages par deux suites de versions différentes |
| `tests/Unit/Traits/VersionOverrideTest.php`, `tests/Unit/Resolvers/TranslationsCatalogTest.php`, `tests/Unit/Pages/BackOfficeGoToMenuTest.php` | Ne touchent plus le statique |
| `tests/Unit/Visual/VisualCheckpointTagTest.php` | Le test « globals d'abord » devient « version de la page » |

---

### Task 1 : les pages prennent leur version à la construction

**Goal :** une page analyse son `patchVersion`, sinon le `PS_VERSION` des globals, avant ses sélecteurs. Sans aucune version, elle lève `InvalidVersionException`. L'exception est importée dans le trait.

**Files :**
- Create : `tests/Unit/Pages/PageVersionInitTest.php`
- Modify : `src/Traits/Version.php` (ajout d'un `use`)
- Modify : `src/Pages/CommonPage.php:47-62` (constructeur) ; nouvelle méthode `initVersion()` juste après le constructeur
- Modify : `src/Pages/BackOfficePage.php:18-36`, `src/Pages/FrontOfficePage.php:22-41`

**Acceptance Criteria :**
- [ ] `new CommonPage('en', '1.7.8.11', [])` a la majeure `1.7` (`7` en namespace) et le patch `1.7.8.11`.
- [ ] `patchVersion` vide avec `PS_VERSION` `9.2.0` donne la majeure `9`.
- [ ] Sans `patchVersion` ni `PS_VERSION`, la construction lève `InvalidVersionException` avec le message « version PrestaShop inconnue ».
- [ ] `patchVersion` `8.1.10` lève `InvalidVersionException` (« Error with version 8.1.10 »), et non une erreur « classe introuvable ».
- [ ] Suite complète verte en PHP 8.4.

**Verify :** `php vendor/bin/phpunit tests/Unit/Pages/PageVersionInitTest.php` → OK (5 tests)

**Steps :**

- [ ] **Step 1 : écrire le test qui échoue**

`tests/Unit/Pages/PageVersionInitTest.php` :

```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\InvalidVersionException;
use PrestaFlow\Library\Pages\CommonPage;

/**
 * Une page prend sa version à sa construction : son argument patchVersion,
 * sinon le PS_VERSION des globals. Sans l'un ni l'autre, erreur explicite
 * (plus de repli silencieux sur '8').
 */
final class PageVersionInitTest extends TestCase
{
    public function test_patch_version_argument_sets_the_page_version(): void
    {
        $page = new CommonPage('en', '1.7.8.11', []);

        $this->assertSame('1.7', $page->getMajorVersion());
        $this->assertSame('7', $page->getMajorVersion(namespace: true));
        $this->assertSame('1.7.8.11', $page->getPatchVersion());
    }

    public function test_patch_version_argument_wins_over_the_globals(): void
    {
        $page = new CommonPage('en', '8.1.0', ['PS_VERSION' => '9.2.0']);

        $this->assertSame('8', $page->getMajorVersion());
    }

    public function test_globals_ps_version_is_used_when_patch_version_is_empty(): void
    {
        $page = new CommonPage('en', '', ['PS_VERSION' => '9.2.0']);

        $this->assertSame('9', $page->getMajorVersion());
        $this->assertSame('9.2.0', $page->getPatchVersion());
    }

    public function test_a_page_without_any_version_throws(): void
    {
        $this->expectException(InvalidVersionException::class);
        $this->expectExceptionMessage('version PrestaShop inconnue');

        new CommonPage('en', '', []);
    }

    public function test_an_unsupported_version_format_throws_the_library_exception(): void
    {
        $this->expectException(InvalidVersionException::class);
        $this->expectExceptionMessage('Error with version 8.1.10');

        new CommonPage('en', '8.1.10', []);
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

Run : `php vendor/bin/phpunit tests/Unit/Pages/PageVersionInitTest.php`

Expected : FAIL.
- `test_a_page_without_any_version_throws` : aucune exception.
- `test_an_unsupported_version_format_throws_the_library_exception` : aucune exception, car le constructeur n'analyse pas encore la version.
- Les autres tests peuvent passer ou échouer selon le cache statique.

- [ ] **Step 3 : importer l'exception dans le trait**

Dans `src/Traits/Version.php`, remplacer :

```php
use PrestaFlow\Library\Utils\Env;
```

par :

```php
use PrestaFlow\Library\Exceptions\InvalidVersionException;
use PrestaFlow\Library\Utils\Env;
```

- [ ] **Step 4 : ajouter `initVersion()` et l'appeler dans les trois constructeurs**

Dans `src/Pages/CommonPage.php`, ajouter l'import en tête (avec les autres `use`) :

```php
use PrestaFlow\Library\Exceptions\InvalidVersionException;
```

Remplacer le début du constructeur :

```php
    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->globals = $globals;
```

par :

```php
    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->initVersion(patchVersion: $patchVersion, globals: $globals);
        $this->globals = $globals;
```

Ajouter juste après la méthode `__construct` (avant `setCustoms`) :

```php
    /**
     * Version PrestaShop de cette page : son patchVersion, sinon le
     * PS_VERSION des globals. Appelée en tête des constructeurs, avant que
     * getSelectors()/getMessages() ne choisissent les fichiers par majeure.
     */
    public function initVersion(string $patchVersion, array $globals): void
    {
        $version = $patchVersion !== '' ? $patchVersion : ($globals['PS_VERSION'] ?? null);

        if (!is_string($version) || $version === '') {
            throw new InvalidVersionException(
                static::class . ' : version PrestaShop inconnue (ni patchVersion ni PS_VERSION dans les globals).'
            );
        }

        $this->exctractVersions($version);
    }
```

Dans `src/Pages/BackOfficePage.php` et `src/Pages/FrontOfficePage.php`, remplacer dans `__construct` :

```php
    {
        $this->globals = $globals;
        $this->customs = array_merge($this->customs, $customs);
        $this->initLocale(locale: $locale);
```

par :

```php
    {
        $this->initVersion(patchVersion: $patchVersion, globals: $globals);
        $this->globals = $globals;
        $this->customs = array_merge($this->customs, $customs);
        $this->initLocale(locale: $locale);
```

- [ ] **Step 5 : vérifier le succès, puis la suite complète**

Run : `php vendor/bin/phpunit tests/Unit/Pages/PageVersionInitTest.php`

Expected : OK (5 tests).

Run : `php vendor/bin/phpunit`

Expected : OK. Si un test construit une page sans version, il échoue désormais sur « version PrestaShop inconnue ». Corriger alors **le test**, jamais `src/` :
- passer un `patchVersion` au constructeur (`'9.0.0'` si le test ne dépend pas de la version) ;
- ou ajouter `'PS_VERSION' => '9.0.0'` à ses globals.

- [ ] **Step 6 : commit**

```bash
git add src/Traits/Version.php src/Pages/CommonPage.php src/Pages/BackOfficePage.php src/Pages/FrontOfficePage.php tests/Unit/Pages/PageVersionInitTest.php
git commit -m "fix(version): une page prend sa version à la construction

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

(Ajouter au `git add` tout test corrigé à l'étape 5, chemin par chemin.)

---

### Task 2 : la version devient une propriété d'instance

**Goal :** supprimer `public static $versions`. Chaque objet garde ses versions, `getMajorVersion()` ne retombe plus sur `'8'`, et les trois tests qui touchaient le statique sont adaptés.

**Files :**
- Create : `tests/Unit/Pages/PageVersionIsolationTest.php`
- Modify : `src/Traits/Version.php` (réécriture complète ci-dessous)
- Modify : `tests/Unit/Traits/VersionOverrideTest.php:16-32`
- Modify : `tests/Unit/Resolvers/TranslationsCatalogTest.php:14-28`
- Modify : `tests/Unit/Pages/BackOfficeGoToMenuTest.php:13-30`

**Acceptance Criteria :**
- [ ] Deux `BackOfficePage` (8.1.0 puis 9.0.0) gardent chacune leur majeure, sur toutes les versions de PHP.
- [ ] Une `BackOfficePage` 8.1.0 puis une `FrontOfficePage` 9.0.0 gardent chacune leur majeure, en PHP 8.1 comme en 8.4.
- [ ] `grep -rn '::\$versions' src tests` ne renvoie rien.
- [ ] Un objet sans version ni `PS_VERSION` lève `InvalidVersionException` dans `getMajorVersion()`.
- [ ] `setVersions()` n'accepte qu'un tableau et ne garde que les trois clés.
- [ ] Suite complète verte en PHP 8.4 et 8.1.

**Verify :** `"$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit tests/Unit/Pages/PageVersionIsolationTest.php` → OK (4 tests)

**Steps :**

- [ ] **Step 1 : écrire le test qui échoue**

`tests/Unit/Pages/PageVersionIsolationTest.php` :

```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\InvalidVersionException;
use PrestaFlow\Library\Pages\BackOfficePage;
use PrestaFlow\Library\Pages\FrontOfficePage;
use PrestaFlow\Library\Traits\Version;

/**
 * La version vivait dans un statique de trait, partagé entre toutes les
 * pages (et, avant PHP 8.3, entre back-office et front-office) : la dernière
 * page construite imposait sa version aux autres.
 */
final class PageVersionIsolationTest extends TestCase
{
    public function test_two_back_office_pages_keep_their_own_version(): void
    {
        $first = new class ('en', '8.1.0', []) extends BackOfficePage {};
        $second = new class ('en', '9.0.0', []) extends BackOfficePage {};

        $this->assertSame('8', $first->getMajorVersion());
        $this->assertSame('9', $second->getMajorVersion());
    }

    public function test_a_back_office_page_does_not_leak_into_a_front_office_page(): void
    {
        $bo = new class ('en', '8.1.0', []) extends BackOfficePage {};
        $fo = new class ('en', '9.0.0', []) extends FrontOfficePage {};

        $this->assertSame('8', $bo->getMajorVersion());
        $this->assertSame('9', $fo->getMajorVersion());
    }

    public function test_an_object_without_any_version_throws_instead_of_assuming_8(): void
    {
        $object = new class {
            use Version;
            public array $globals = [];
        };

        $this->expectException(InvalidVersionException::class);
        $this->expectExceptionMessage('version PrestaShop inconnue');

        $object->getMajorVersion();
    }

    public function test_set_versions_keeps_only_the_three_known_keys(): void
    {
        $object = new class {
            use Version;
            public array $globals = [];
        };

        $object->setVersions(['majorVersion' => '9', 'minorVersion' => '9.2', 'patchVersion' => '9.2.0', 'other' => 'x']);

        $this->assertSame(
            ['patchVersion' => '9.2.0', 'minorVersion' => '9.2', 'majorVersion' => '9'],
            $object->getVersions()
        );
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

Run : `"$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit tests/Unit/Pages/PageVersionIsolationTest.php`

Expected : FAIL.
- `test_two_back_office_pages_keep_their_own_version` : `'9'` au lieu de `'8'` (statique partagé).
- `test_a_back_office_page_does_not_leak_into_a_front_office_page` : `'9'` au lieu de `'8'` en PHP 8.1.
- `test_an_object_without_any_version_throws_instead_of_assuming_8` : pas d'exception (repli `'8'`).
- `test_set_versions_keeps_only_the_three_known_keys` : la clé `other` est gardée.

- [ ] **Step 3 : réécrire `src/Traits/Version.php`**

Contenu complet du fichier :

```php
<?php

namespace PrestaFlow\Library\Traits;

use PrestaFlow\Library\Exceptions\InvalidVersionException;
use PrestaFlow\Library\Utils\Env;

trait Version
{
    private static array $supportedVersions = [
        '1.7',
        '8',
        '9'
    ];

    /**
     * Version PrestaShop de CET objet (page, suite, scénario). Propriété
     * d'instance : un statique de trait est partagé par les sous-classes (et,
     * avant PHP 8.3, entre classes qui refont `use`), si bien qu'une page
     * imposait sa version aux suivantes.
     */
    protected array $versions = [
        'patchVersion' => null,
        'minorVersion' => null,
        'majorVersion' => null,
    ];

    protected ?string $psVersionOverride = null;

    public function onVersion(string $version): self
    {
        if (!preg_match('/^\d+\.\d+(\.\d+){0,2}$/', $version)) {
            throw new \InvalidArgumentException(
                "Invalid PS version: '" . $version . "'. Expected format like '1.7.8.11' or '9.0.1'."
            );
        }

        $this->psVersionOverride = $version;

        if (!empty($this->globals)) {
            $this->resolveVersion();
        }

        return $this;
    }

    public function resolveVersion(): void
    {
        $propertyVersion = property_exists($this, 'psVersion') ? ($this->psVersion ?? null) : null;

        $version = $this->psVersionOverride
            ?? $propertyVersion
            ?? Env::get('PRESTAFLOW_PS_VERSION')
            ?? ($this->globals['PS_VERSION'] ?? null)
            ?? '8.1.0';

        if (!is_array($this->globals ?? null)) {
            $this->globals = [];
        }
        $this->globals['PS_VERSION'] = $version;

        $this->setVersions([]);

        $this->exctractVersions($version);
    }

    public function isVersionSupported()
    {
        if (in_array($this->getMajorVersion(), self::$supportedVersions)) {
            return true;
        }

        return false;
    }

    public function setVersions(array $versions = []): array
    {
        return $this->versions = [
            'patchVersion' => $versions['patchVersion'] ?? null,
            'minorVersion' => $versions['minorVersion'] ?? null,
            'majorVersion' => $versions['majorVersion'] ?? null,
        ];
    }

    public function getVersions(): array
    {
        return $this->versions;
    }

    public function setPatchVersion(string $patchVersion)
    {
        $this->versions['patchVersion'] = $patchVersion;
    }

    public function getPatchVersion()
    {
        return $this->versions['patchVersion'];
    }

    public function setMinorVersion(string $minorVersion)
    {
        $this->versions['minorVersion'] = $minorVersion;
    }

    public function getMinorVersion()
    {
        return $this->versions['minorVersion'];
    }

    public function setMajorVersion(string $majorVersion)
    {
        $this->versions['majorVersion'] = $majorVersion;
    }

    /**
     * Majeure de cet objet ('1.7', '8', '9'). Si elle n'a jamais été posée,
     * elle se déduit du PS_VERSION des globals ; sans lui, erreur explicite
     * (il n'y a plus de repli sur '8').
     */
    public function getMajorVersion(bool $namespace = false)
    {
        if (empty($this->versions['majorVersion'])) {
            $psVersion = $this->globals['PS_VERSION'] ?? null;

            if (!is_string($psVersion) || $psVersion === '') {
                throw new InvalidVersionException(
                    static::class . ' : version PrestaShop inconnue (aucune version reçue, ni PS_VERSION dans les globals).'
                );
            }

            $this->exctractVersions($psVersion);
        }

        $majorVersion = $this->versions['majorVersion'];

        if ($namespace && str_starts_with($majorVersion, '1.')) {
            return substr($majorVersion, strlen('1.'));
        }

        return $majorVersion;
    }

    public function exctractVersions(string $patchVersion)
    {
        $this->versions['patchVersion'] = $patchVersion;

        if (strlen($patchVersion) === 7 || strlen($patchVersion) === 8) {
            $this->versions['minorVersion'] = substr($patchVersion, 0, 5);
        } else if (strlen($patchVersion) === 5) {
            $this->versions['minorVersion'] = substr($patchVersion, 0, 3);
        } else {
            throw new InvalidVersionException('Error with version ' . $patchVersion);
        }

        $minorVersion = $this->versions['minorVersion'];
        if (str_starts_with($minorVersion, '1.7')) {
            $this->versions['majorVersion'] = '1.7';
        } else if (str_starts_with($minorVersion, '1.6')) {
            $this->versions['majorVersion'] = '1.6';
        } else {
            $this->versions['majorVersion'] = substr($minorVersion, 0, 1);
        }
    }
}
```

Notes :
- les formats acceptés par `exctractVersions()` sont inchangés (longueurs 5, 7 et 8) ;
- `resolveVersion()` garde son ordre de priorité et son défaut `'8.1.0'` ;
- en namespace, `1.6` donne désormais `6`, comme le faisait déjà la branche « globals » d'origine.

- [ ] **Step 4 : adapter les trois tests qui touchaient le statique**

`tests/Unit/Traits/VersionOverrideTest.php` : dans `makeSuite()`, supprimer le bloc suivant (chaque instance part déjà de versions vides) :

```php
        // Reset the static state via the concrete class using the trait so no deprecation is raised.
        $suite::$versions = [
            'patchVersion' => null,
            'minorVersion' => null,
            'majorVersion' => null,
        ];
```

`tests/Unit/Resolvers/TranslationsCatalogTest.php` : dans `makePage()`, supprimer :

```php
        $page::$versions = [
            'patchVersion' => null,
            'minorVersion' => null,
            'majorVersion' => null,
        ];
```

`tests/Unit/Pages/BackOfficeGoToMenuTest.php` : supprimer le docblock, la propriété `$versions`, `setUp()` et `tearDown()` (lignes 13 à 30) :

```php
    /**
     * The page versions live in a static (Traits\Version) that getPageName()
     * fills on first use and never re-derives. Before PHP 8.3 a class
     * re-using that trait (FrontOfficePage, via Translations) shares the
     * parent's storage, so whatever a BackOfficePage caches here is what the
     * next front-office page in the process sees. Leave it as found.
     */
    private array $versions = [];

    protected function setUp(): void
    {
        $this->versions = CommonPage::$versions;
    }

    protected function tearDown(): void
    {
        CommonPage::$versions = $this->versions;
    }
```

Retirer aussi `use PrestaFlow\Library\Pages\CommonPage;` de ce fichier s'il n'est plus utilisé ailleurs. Vérifier avec `grep -n CommonPage tests/Unit/Pages/BackOfficeGoToMenuTest.php`.

- [ ] **Step 5 : vérifier**

Run :

```bash
"$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit tests/Unit/Pages/PageVersionIsolationTest.php
grep -rn '::\$versions' src tests
php vendor/bin/phpunit
"$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit
```

Expected :
- isolation : OK (4 tests) ;
- `grep` : aucune ligne ;
- les deux suites complètes : OK.

Si un test échoue sur « version PrestaShop inconnue », appliquer la règle de la Task 1 (Step 5) : corriger le test en lui donnant une version, jamais `src/`. Cas probables, dont le constructeur n'appelle pas `parent::__construct` :
- `FakeThemePage` dans `tests/Unit/Pages/ThemeSelectorsTest.php` : ajouter `$this->setMajorVersion('9');` dans son constructeur, s'il échoue ;
- les classes anonymes de `ClickFallbackTest` et `VisibilityOnLostBrowserTest`.

- [ ] **Step 6 : commit**

```bash
git add src/Traits/Version.php tests/Unit/Pages/PageVersionIsolationTest.php tests/Unit/Traits/VersionOverrideTest.php tests/Unit/Resolvers/TranslationsCatalogTest.php tests/Unit/Pages/BackOfficeGoToMenuTest.php
git commit -m "fix(version): version par instance, plus de cache statique ni de repli sur 8

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

(Ajouter au `git add` tout test corrigé à l'étape 5, chemin par chemin.)

---

### Task 3 : transmission explicite (scénario, import de page)

**Goal :**
- `Scenario` reprend le **tableau** de versions de sa suite, et analyse `PATCH_VERSION` s'il est présent au lieu de le passer tel quel à `setVersions()`.
- `importPage()` ne recopie plus les versions de la suite dans la page : la page les prend à sa construction, et `initTranslations()` les recalcule à partir du même patch.

**Files :**
- Create : `tests/Unit/Scenarios/ScenarioVersionTest.php`
- Modify : `src/Scenarios/Scenario.php:32-36`
- Modify : `src/Traits/ImportPage.php:37-43`
- Modify : `tests/Unit/Tests/SuiteVersionWiringTest.php` (nouveau test en fin de classe)

**Acceptance Criteria :**
- [ ] Un scénario créé sur une suite 1.7.8.11 a la majeure `1.7` et le patch `1.7.8.11`.
- [ ] Avec `PATCH_VERSION` `9.2.0` dans les globals (suite en 8.1.0), le scénario a un tableau de versions dont la majeure vaut `9`.
- [ ] Changer la version du scénario ne change pas celle de la suite.
- [ ] Deux suites (1.7.8.11 puis 9.0.0) qui importent `FrontOffice\Home` obtiennent une page v7 puis une page v9, et la première page reste en `1.7`.
- [ ] Suite complète verte en PHP 8.4.

**Verify :** `php vendor/bin/phpunit tests/Unit/Scenarios/ScenarioVersionTest.php tests/Unit/Tests/SuiteVersionWiringTest.php` → OK

**Steps :**

- [ ] **Step 1 : écrire les tests qui échouent**

`tests/Unit/Scenarios/ScenarioVersionTest.php` :

```php
<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Scenarios\Scenario;
use PrestaFlow\Library\Tests\TestsSuite;

final class ScenarioVersionTest extends TestCase
{
    private function suite(string $version, array $globals = []): TestsSuite
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {};
        $suite->onVersion($version);
        $suite->setGlobals(['LOCALE' => 'en', ...$globals]);

        return $suite;
    }

    public function test_the_scenario_takes_the_suite_versions(): void
    {
        $scenario = new Scenario($this->suite('1.7.8.11'));

        $this->assertSame('1.7', $scenario->getMajorVersion());
        $this->assertSame('1.7.8.11', $scenario->getPatchVersion());
    }

    public function test_a_patch_version_global_is_parsed_not_stored_as_a_string(): void
    {
        $scenario = new Scenario($this->suite('8.1.0', ['PATCH_VERSION' => '9.2.0']));

        $this->assertSame(
            ['patchVersion' => '9.2.0', 'minorVersion' => '9.2', 'majorVersion' => '9'],
            $scenario->getVersions()
        );
    }

    public function test_changing_the_scenario_version_leaves_the_suite_alone(): void
    {
        $suite = $this->suite('8.1.0');
        $scenario = new Scenario($suite);

        $scenario->setMajorVersion('9');

        $this->assertSame('8', $suite->getMajorVersion());
    }
}
```

Ajouter en fin de classe dans `tests/Unit/Tests/SuiteVersionWiringTest.php` :

```php
    public function test_two_suites_import_pages_of_their_own_version(): void
    {
        $old = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            protected $psVersion = '1.7.8.11';
        };
        $old->importPage('FrontOffice\Home');

        $new = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            protected $psVersion = '9.0.0';
        };
        $new->importPage('FrontOffice\Home');

        $this->assertInstanceOf(\PrestaFlow\Library\Pages\v7\FrontOffice\Home\Page::class, $old->pages['frontOfficeHomePage']);
        $this->assertInstanceOf(\PrestaFlow\Library\Pages\v9\FrontOffice\Home\Page::class, $new->pages['frontOfficeHomePage']);
        $this->assertSame('1.7', $old->pages['frontOfficeHomePage']->getMajorVersion());
        $this->assertSame('9', $new->pages['frontOfficeHomePage']->getMajorVersion());
    }
```

- [ ] **Step 2 : vérifier l'échec**

Run : `php vendor/bin/phpunit tests/Unit/Scenarios/ScenarioVersionTest.php tests/Unit/Tests/SuiteVersionWiringTest.php`

Expected : FAIL sur `test_a_patch_version_global_is_parsed_not_stored_as_a_string` (TypeError : `setVersions()` attend un tableau et reçoit `'9.2.0'`). Les autres tests peuvent déjà passer après la Task 2. Ils servent de garde-fous.

- [ ] **Step 3 : implémenter**

Dans `src/Scenarios/Scenario.php`, remplacer :

```php
        $locale = $this->globals['LOCALE'] ?? $testSuite->getLocale();
        $versions = $this->globals['PATCH_VERSION'] ?? $testSuite->getVersions();

        $this->setVersions(versions: $versions);
        $this->setLocale(locale: $locale);
```

par :

```php
        $locale = $this->globals['LOCALE'] ?? $testSuite->getLocale();

        $this->setVersions(versions: $testSuite->getVersions());
        if (is_string($this->globals['PATCH_VERSION'] ?? null) && $this->globals['PATCH_VERSION'] !== '') {
            $this->exctractVersions($this->globals['PATCH_VERSION']);
        }
        $this->setLocale(locale: $locale);
```

Dans `src/Traits/ImportPage.php`, remplacer :

```php
        $pageInstance->setLocale(locale: $locale);
        $pageInstance->setPatchVersion($patchVersion);
        $pageInstance->setMinorVersion($this->getMinorVersion());
        $pageInstance->setMajorVersion($this->getMajorVersion());

        $pageInstance->initTranslations(
```

par :

```php
        $pageInstance->setLocale(locale: $locale);

        // La page a pris sa version à sa construction (patchVersion) ;
        // initTranslations() la recalcule à partir du même patch.
        $pageInstance->initTranslations(
```

- [ ] **Step 4 : vérifier**

Run : `php vendor/bin/phpunit tests/Unit/Scenarios/ScenarioVersionTest.php tests/Unit/Tests/SuiteVersionWiringTest.php`

Expected : OK.

Run : `php vendor/bin/phpunit`

Expected : OK.

- [ ] **Step 5 : commit**

```bash
git add src/Scenarios/Scenario.php src/Traits/ImportPage.php tests/Unit/Scenarios/ScenarioVersionTest.php tests/Unit/Tests/SuiteVersionWiringTest.php
git commit -m "fix(version): scénario et import de page se transmettent la version

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4 : la capture visuelle lit la version de sa page

**Goal :** retirer de `visualCheckpoint()` le contournement « globals `PS_VERSION` d'abord ». Il n'existait qu'à cause du cache statique. La majeure du tag vient désormais de `getMajorVersion()`.

**Files :**
- Modify : `src/Pages/CommonPage.php` (dans `visualCheckpoint()`, ~l.700-708)
- Modify : `tests/Unit/Visual/VisualCheckpointTagTest.php` (remplacer `testMajorVersionDerivesFromGlobalsPsVersionFirst`)

**Acceptance Criteria :**
- [ ] Le tag d'une page vient de sa propre version, même si une autre page a changé la sienne entre-temps.
- [ ] Le cas 1.7 donne toujours `auto-v1.7-…`.
- [ ] Le commentaire « cache statique » a disparu de `CommonPage.php`.
- [ ] Suite complète verte en PHP 8.4.

**Verify :** `php vendor/bin/phpunit tests/Unit/Visual/` → OK

**Steps :**

- [ ] **Step 1 : remplacer le test**

Dans `tests/Unit/Visual/VisualCheckpointTagTest.php`, remplacer toute la méthode :

```php
    public function testMajorVersionDerivesFromGlobalsPsVersionFirst(): void
    {
        // Le cache statique de version peut être périmé (suite précédente du worker).
        $page = $this->makePage(['PS_VERSION' => '8.1.0'], '1.7');

        $page->visualCheckpoint('home');

        $this->assertSame('auto-v8-1280x720-fr', TestsSuite::$visualResults[0]['tag']);
    }
```

par :

```php
    public function testMajorVersionComesFromThisPageOnly(): void
    {
        // makePage() construit la page avec patchVersion '8.1.0'. Une autre
        // page passée en 1.7 ensuite ne doit pas changer son tag.
        $page = $this->makePage([], null);
        $other = $this->makePage([], null);
        $other->setMajorVersion('1.7');

        $page->visualCheckpoint('home');

        $this->assertSame('auto-v8-1280x720-fr', TestsSuite::$visualResults[0]['tag']);
    }
```

- [ ] **Step 2 : vérifier que le test passe déjà**

Run : `php vendor/bin/phpunit tests/Unit/Visual/VisualCheckpointTagTest.php`

Expected : OK. La version est par instance depuis la Task 2 ; ce test garde la régression.

- [ ] **Step 3 : simplifier `visualCheckpoint()`**

Dans `src/Pages/CommonPage.php`, remplacer :

```php
        // globals PS_VERSION d'abord (vérité de la suite courante ; le cache statique
        // de Version peut venir d'une suite précédente du worker), puis le cache.
        // '1.7' / '1.6' sont des majeures valides (auparavant rejetées → « v? »).
        $rawMajorVersion = $this->getMajorVersion();
        $majorVersion = \PrestaFlow\Library\Visual\VisualTag::majorFromVersion(
            is_string($this->globals['PS_VERSION'] ?? null) ? $this->globals['PS_VERSION'] : null
        ) ?? \PrestaFlow\Library\Visual\VisualTag::majorFromVersion(
            is_scalar($rawMajorVersion) ? (string) $rawMajorVersion : null
        );
```

par :

```php
        // Majeure de cette page ; '1.7' / '1.6' sont des majeures valides
        // (auparavant rejetées → « v? »).
        $rawMajorVersion = $this->getMajorVersion();
        $majorVersion = \PrestaFlow\Library\Visual\VisualTag::majorFromVersion(
            is_scalar($rawMajorVersion) ? (string) $rawMajorVersion : null
        );
```

- [ ] **Step 4 : vérifier**

Run :

```bash
php vendor/bin/phpunit tests/Unit/Visual/
grep -n "cache statique" src/Pages/CommonPage.php
php vendor/bin/phpunit
```

Expected :
- tests visuels : OK ;
- `grep` : aucune ligne ;
- suite complète : OK.

- [ ] **Step 5 : commit**

```bash
git add src/Pages/CommonPage.php tests/Unit/Visual/VisualCheckpointTagTest.php
git commit -m "refactor(visual): le tag lit la version de la page, sans contournement

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5 : vérification sur PHP 8.1 à 8.4

**Goal :** prouver que le comportement ne dépend plus de la version de PHP, et corriger les derniers tests qui construiraient une page sans version.

**Files :**
- Modify : uniquement des tests qui échouent ici (aucun fichier `src/` attendu).

**Acceptance Criteria :**
- [ ] `phpunit` complet vert sous PHP 8.1, 8.2, 8.3 et 8.4.
- [ ] Les nouveaux tests d'isolation passent aussi avec `--order-by=random` (3 graines) sous PHP 8.1.
- [ ] `grep -rn "self::\$versions\|::\$versions" src tests` ne renvoie rien.

**Verify :** les quatre commandes du Step 1 → OK

**Steps :**

- [ ] **Step 1 : lancer la matrice**

```bash
"$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit
"$HOME/Library/Application Support/Herd/bin/php82" vendor/bin/phpunit
"$HOME/Library/Application Support/Herd/bin/php83" vendor/bin/phpunit
php vendor/bin/phpunit
```

Expected : OK pour chacune.

- [ ] **Step 2 : ordre aléatoire**

```bash
for seed in 1 2 3; do "$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit --order-by=random --random-order-seed=$seed tests/Unit/Pages tests/Unit/Visual tests/Unit/Scenarios tests/Unit/Tests tests/Unit/Traits tests/Unit/Resolvers || echo "FAIL seed $seed"; done
```

Expected : aucune ligne « FAIL seed ».

- [ ] **Step 3 : corriger si besoin**

Un échec sur « version PrestaShop inconnue » vient d'un test qui construit une page sans version. Lui donner une version (`patchVersion` `'9.0.0'`, `'PS_VERSION' => '9.0.0'` dans ses globals, ou `setMajorVersion('9')` dans un constructeur factice). Ne jamais remettre un défaut dans `src/`. Relancer le Step 1.

Un autre type d'échec doit être remonté avec la sortie complète, sans contournement.

- [ ] **Step 4 : commit (seulement si des tests ont changé)**

```bash
git add <chemins exacts des tests corrigés>
git commit -m "test(version): versions explicites dans les pages factices

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Après le plan (hors tâches, sur demande de l'utilisateur)

1. Pousser la branche, ouvrir la PR vers `dev` et attendre la CI (13 checks : PHPUnit 8.1 à 8.5, Smoke, Visual).
2. Release `dev` → `main` (« Release: … (#N) »).
3. Mettre à jour la lib dans l'app : seul `composer.lock` change. Lancer ensuite les tests de l'app.
   - `app/Services/Suite/SuiteMetadata.php` cite `setMajorVersion` / `getMajorVersion` / `exctractVersions` : leurs noms ne changent pas.
   - Point d'attention : une suite chargée sans `loadGlobals` qui importerait une page lève maintenant une erreur au lieu de supposer la v8.
