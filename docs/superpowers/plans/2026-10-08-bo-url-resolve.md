# Une seule règle de résolution des URL du back-office : plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `TestsSuite::loadGlobals()` résout un `PRESTAFLOW_BO_URL` relatif avec la même règle que les suites visuelles et que l'app : un utilitaire `BackOfficeUrl`, aucun nouveau refus.

**Architecture :**
- Nouvel utilitaire statique pur `PrestaFlow\Library\Utils\BackOfficeUrl`, avec deux méthodes :
  - `resolve()` : corps actuel de `VisualTestsSuite::resolveBackOfficeUrl()` ;
  - `isRelative()`.
- `VisualTestsSuite::resolveBackOfficeUrl()` délègue à `resolve()`.
- `loadGlobals()` appelle les deux méthodes de l'utilitaire.

**Tech Stack :** PHP 8.1+, PHPUnit 10 (PHP 8.4 et 8.1).

**Spec :** `docs/superpowers/specs/2026-10-08-bo-url-resolve-design.md`

---

## Contexte implémenteur

- **Dépôt :** worktree `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library-bo-resolve`, branche `feat/bo-url-resolve`, partie de `origin/dev` (`514c443`). Lance toutes les commandes depuis ce dossier.
- **Toujours `git --no-replace-objects`.** Ne touche jamais `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library`, le checkout de l'utilisateur.
- **Dépendances :** `composer install -q` si `vendor/` manque.
- **Tests :**
  - redirige toujours la sortie vers le scratchpad `SP` : `php vendor/bin/phpunit > "$SP/<nom>.txt" 2>&1; tail -3 "$SP/<nom>.txt"` ;
  - PHP 8.4 avec `php`, PHP 8.1 avec `"$HOME/Library/Application Support/Herd/bin/php81"` ;
  - aucun test ne lance Chrome ;
  - base : 668 tests au départ.
- **Fichiers :**
  - écris-les avec Write/Edit ou python, jamais par redirection shell (un hook supprime les commentaires) ;
  - lis-les avec Read, `sed -n` ou `rtk proxy git …`.
- **Commits :**
  - chemins explicites ; jamais `git add -A`, `git add .` ni `commit -a` ;
  - messages en français, terminés par une ligne vide puis `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Environnement des tests :**
  - les tests de `loadGlobals()` écrivent dans `$_ENV`, puis restaurent la valeur d'avant ;
  - `loadGlobals()` charge aussi les `.env*` du dépôt avec Dotenv immutable : une variable déjà présente dans `$_ENV`, même vide, n'est pas réécrite. Définis donc explicitement `PRESTAFLOW_FO_URL` et `PRESTAFLOW_BO_URL` dans chaque cas, sauf le cas « absente ».

---

### Task 1 : utilitaire `BackOfficeUrl` et délégation de `VisualTestsSuite`

**Goal :** créer `PrestaFlow\Library\Utils\BackOfficeUrl::resolve()` et `::isRelative()`, testés seuls. `VisualTestsSuite::resolveBackOfficeUrl()` délègue à `resolve()` sans changer de comportement.

**Files :**
- Create : `src/Utils/BackOfficeUrl.php`
- Create : `tests/Unit/Utils/BackOfficeUrlTest.php`
- Modify : `src/Tests/VisualTestsSuite.php`, méthode `resolveBackOfficeUrl()` (vers la ligne 485)
- Modify : `tests/Unit/Visual/VisualTestsSuiteUrlsTest.php` (le fournisseur `backOfficeUrlCases()` est déplacé)

**Acceptance Criteria :**
- [ ] `BackOfficeUrl::resolve()` rend exactement les résultats des 13 cas actuels de `backOfficeUrlCases()`.
- [ ] `BackOfficeUrl::isRelative()` :
  - vrai pour `admin/`, `/admin`, `admin-dev/` et `  admin  ` ;
  - faux pour `http://x/a`, `HTTPS://x/a`, `//x/a`, `''` et `'   '`.
- [ ] `VisualTestsSuite::resolveBackOfficeUrl()` existe toujours (publique, statique, même signature) et délègue. Un test le vérifie sur un cas.
- [ ] `VisualTestsSuiteUrlsTest` reste vert.
- [ ] Suite complète verte en 8.4 et 8.1.

**Verify :** `php vendor/bin/phpunit --filter 'BackOfficeUrlTest|VisualTestsSuiteUrlsTest' > "$SP/bo-t1.txt" 2>&1; tail -3 "$SP/bo-t1.txt"` → `OK`

**Steps :**

- [ ] **Step 1 : test qui échoue.** Crée `tests/Unit/Utils/BackOfficeUrlTest.php` :

```php
<?php

namespace PrestaFlow\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\VisualTestsSuite;
use PrestaFlow\Library\Utils\BackOfficeUrl;

final class BackOfficeUrlTest extends TestCase
{
    /**
     * Mêmes cas que App\Support\BackOfficeUrl::resolve() (tests BackOfficeUrlTest de l'app).
     *
     * @return array<string, array{string, string, string}>
     */
    public static function resolveCases(): array
    {
        return [
            'absolue gardée' => ['http://shop.test/admin-dev/', 'http://other.test/', 'http://shop.test/admin-dev/'],
            'absolue rognée' => [' https://shop.test/admin123 ', '', 'https://shop.test/admin123/'],
            'absolue majuscules' => ['HTTP://shop.test/a', '', 'HTTP://shop.test/a/'],
            'relative sans slash' => ['admin-dev', 'http://shop.test', 'http://shop.test/admin-dev/'],
            'relative' => ['admin-dev/', 'http://shop.test/', 'http://shop.test/admin-dev/'],
            'relative à slash initial' => ['/admin-dev/', 'http://shop.test/', 'http://shop.test/admin-dev/'],
            'relative sous chemin' => ['admin/sub', ' http://shop.test/fr/ ', 'http://shop.test/fr/admin/sub/'],
            'protocole https' => ['//h.test/admin', 'https://fo.test', 'https://h.test/admin/'],
            'protocole majuscules' => ['//h.test/admin', 'HTTP://fo.test/fr', 'http://h.test/admin/'],
            'requête et fragment FO ignorés' => ['admin', 'http://fo.test/fr?x=1#a', 'http://fo.test/fr/admin/'],
            'fragment FO seul' => ['/admin', 'http://fo.test/#frag', 'http://fo.test/admin/'],
            'point dans un segment non initial' => ['/admin.v2/', 'http://fo.test', 'http://fo.test/admin.v2/'],
            'segment à point' => ['admin/v2.1/', 'http://fo.test', 'http://fo.test/admin/v2.1/'],
        ];
    }

    /** @dataProvider resolveCases */
    public function test_resolve(string $backOffice, string $frontOffice, string $expected): void
    {
        $this->assertSame($expected, BackOfficeUrl::resolve($backOffice, $frontOffice));
    }

    /** @return array<string, array{string, bool}> */
    public static function relativeCases(): array
    {
        return [
            'chemin' => ['admin/', true],
            'slash initial' => ['/admin', true],
            'défaut' => ['admin-dev/', true],
            'chemin rogné' => ['  admin  ', true],
            'absolue' => ['http://x/a', false],
            'absolue majuscules' => ['HTTPS://x/a', false],
            'protocole' => ['//x/a', false],
            'vide' => ['', false],
            'blanc' => ['   ', false],
        ];
    }

    /** @dataProvider relativeCases */
    public function test_is_relative(string $backOffice, bool $expected): void
    {
        $this->assertSame($expected, BackOfficeUrl::isRelative($backOffice));
    }

    public function test_visual_suite_keeps_delegating(): void
    {
        $this->assertSame('http://fo.test/fr/admin/', VisualTestsSuite::resolveBackOfficeUrl('admin', 'http://fo.test/fr?x=1#a'));
    }
}
```

- [ ] **Step 2 : constater l'échec.** `php vendor/bin/phpunit --filter BackOfficeUrlTest > "$SP/bo-t1-red.txt" 2>&1; tail -5 "$SP/bo-t1-red.txt"` → erreurs `Class "PrestaFlow\Library\Utils\BackOfficeUrl" not found`.

- [ ] **Step 3 : utilitaire.** Crée `src/Utils/BackOfficeUrl.php` :

```php
<?php

namespace PrestaFlow\Library\Utils;

/**
 * Règle unique de l'URL du back-office, la même que App\Support\BackOfficeUrl::resolve()
 * dans l'app PrestaFlow. Sert à loadGlobals() (PRESTAFLOW_BO_URL) et aux suites
 * visuelles ($backOfficeUrl). Ne refuse rien : la validation reste à l'appelant.
 */
final class BackOfficeUrl
{
    /**
     * URL BO effective :
     * - absolue http(s) (casse indifférente) : gardée telle quelle ;
     * - relative au protocole (`//hôte/admin`) : schéma de l'URL FO ;
     * - relative (`admin123/`, `/admin123`) : URL FO sans requête ni fragment,
     *   chemin FO gardé, puis le chemin, avec un seul « / » entre les deux.
     * Toujours terminée par « / ».
     */
    public static function resolve(string $backOffice, string $frontOffice): string
    {
        $backOffice = trim($backOffice);
        $frontOffice = trim($frontOffice);
        if (str_starts_with($backOffice, '//')) {
            if (preg_match('#^(https?)://#i', $frontOffice, $m) === 1) {
                $backOffice = strtolower($m[1]).':'.$backOffice;
            }
        } elseif (preg_match('#^https?://#i', $backOffice) !== 1) {
            $frontOffice = (string) preg_replace('/[?#].*\z/s', '', $frontOffice);
            $backOffice = rtrim($frontOffice, '/').'/'.ltrim($backOffice, '/');
        }

        return str_ends_with($backOffice, '/') ? $backOffice : $backOffice.'/';
    }

    /** Vrai si l'URL BO dépend de l'URL FO : ni absolue http(s), ni `//hôte`, ni vide. */
    public static function isRelative(string $backOffice): bool
    {
        $backOffice = trim($backOffice);

        return $backOffice !== ''
            && !str_starts_with($backOffice, '//')
            && preg_match('#^https?://#i', $backOffice) !== 1;
    }
}
```

- [ ] **Step 4 : délégation.** Dans `src/Tests/VisualTestsSuite.php` :
  - ajoute `use PrestaFlow\Library\Utils\BackOfficeUrl;` à côté de `use PrestaFlow\Library\Utils\Env;` ;
  - remplace le docblock et le corps de `resolveBackOfficeUrl()`, du `/**` qui précède `public static function resolveBackOfficeUrl(` jusqu'à l'accolade fermante de la méthode, par :

```php
    /**
     * URL BO effective : voir BackOfficeUrl::resolve() (règle unique, la même que
     * loadGlobals() et que l'app). Gardée publique pour compatibilité.
     */
    public static function resolveBackOfficeUrl(string $backOffice, string $frontOffice): string
    {
        return BackOfficeUrl::resolve($backOffice, $frontOffice);
    }
```

- [ ] **Step 5 : déplacer le fournisseur.** Dans `tests/Unit/Visual/VisualTestsSuiteUrlsTest.php`, supprime `backOfficeUrlCases()` (son docblock `@return` compris) et `test_resolve_back_office_url()` (son docblock `@dataProvider` compris). Ces cas vivent maintenant dans `BackOfficeUrlTest`.

- [ ] **Step 6 : vert.** `php vendor/bin/phpunit --filter 'BackOfficeUrlTest|VisualTestsSuiteUrlsTest' > "$SP/bo-t1.txt" 2>&1; tail -3 "$SP/bo-t1.txt"` → `OK`. Lance ensuite la suite complète en 8.4 et en 8.1 (`bo-t1-84.txt`, `bo-t1-81.txt`). Total attendu : 668 − 13 + 23 = 678 tests.

- [ ] **Step 7 : commit.**

```bash
git --no-replace-objects add src/Utils/BackOfficeUrl.php tests/Unit/Utils/BackOfficeUrlTest.php src/Tests/VisualTestsSuite.php tests/Unit/Visual/VisualTestsSuiteUrlsTest.php
git --no-replace-objects commit -m "refactor(urls): règle de l'URL du back-office dans l'utilitaire BackOfficeUrl

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2 : `loadGlobals()` utilise `BackOfficeUrl`, et README

**Goal :** `PRESTAFLOW_BO_URL`, ou le défaut `admin-dev/`, est résolu par `BackOfficeUrl::resolve()`. `$backOfficeRelative` suit `BackOfficeUrl::isRelative()`. Le README renvoie à la règle commune.

**Files :**
- Modify : `src/Tests/TestsSuite.php` (bloc `$backOfficeUrl` de `loadGlobals()`, vers les lignes 1188-1196, et le `use`)
- Create : `tests/Unit/Tests/LoadGlobalsBackOfficeUrlTest.php`
- Modify : `README.md` (ligne « The back-office area also uses `PRESTAFLOW_BO_URL` … »)

**Acceptance Criteria :**
- [ ] Avec `PRESTAFLOW_FO_URL=https://x/` :
  - `/admin` → `https://x/admin/` ;
  - `//autre.test/admin` → `https://autre.test/admin/` ;
  - `HTTPS://bo.test/admin` → `HTTPS://bo.test/admin/` ;
  - `admin123` → `https://x/admin123/`.
- [ ] Avec `PRESTAFLOW_FO_URL=https://x/?lang=2` et `PRESTAFLOW_BO_URL=admin-dev/` → `https://x/admin-dev/`. L'URL FO reste `https://x/?lang=2/`, inchangée.
- [ ] `PRESTAFLOW_BO_URL` absente → `<FO>admin-dev/`, et `$backOfficeRelative` vaut `admin-dev/`.
- [ ] `PRESTAFLOW_BO_URL` vide → l'URL FO, et `$backOfficeRelative` vaut `null`.
- [ ] `$backOfficeRelative` :
  - vaut la valeur rognée pour un chemin relatif ;
  - vaut `null` pour une URL absolue et pour `//hôte`.
- [ ] Les tests `test_cli_*` de `VisualTestsSuiteUrlsTest` restent verts.
- [ ] Suite complète verte en 8.4 et 8.1.

**Verify :** `php vendor/bin/phpunit --filter 'LoadGlobalsBackOfficeUrlTest|VisualTestsSuiteUrlsTest' > "$SP/bo-t2.txt" 2>&1; tail -3 "$SP/bo-t2.txt"` → `OK`

**Steps :**

- [ ] **Step 1 : test qui échoue.** Crée `tests/Unit/Tests/LoadGlobalsBackOfficeUrlTest.php` :

```php
<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

final class LoadGlobalsBackOfficeUrlTest extends TestCase
{
    /**
     * Construit une suite qui appelle loadGlobals() avec ces variables, puis restaure $_ENV.
     * Une valeur null retire la variable (cas « absente »).
     *
     * @param array<string, ?string> $env
     */
    private function suite(array $env): TestsSuite
    {
        $saved = [];
        foreach ($env as $key => $value) {
            $saved[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
            if ($value === null) {
                unset($_ENV[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
            }
        }
        try {
            return new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
                public function relative(): ?string
                {
                    return $this->backOfficeRelative;
                }
            };
        } finally {
            foreach ($saved as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    /** @return array<string, array{string, string, string, ?string}> */
    public static function cases(): array
    {
        return [
            'slash initial' => ['https://x/', '/admin', 'https://x/admin/', '/admin'],
            'relatif' => ['https://x/', 'admin123', 'https://x/admin123/', 'admin123'],
            'requête FO ignorée' => ['https://x/?lang=2', 'admin-dev/', 'https://x/admin-dev/', 'admin-dev/'],
            'protocole' => ['https://x/', '//autre.test/admin', 'https://autre.test/admin/', null],
            'absolue majuscules' => ['https://x/', 'HTTPS://bo.test/admin', 'HTTPS://bo.test/admin/', null],
            'absolue' => ['https://x/', 'https://bo.test/admin/', 'https://bo.test/admin/', null],
            'relatif rogné' => ['https://x/', '  admin  ', 'https://x/admin/', 'admin'],
            'vide' => ['https://x/', '', 'https://x/', null],
        ];
    }

    /** @dataProvider cases */
    public function test_back_office_url_follows_the_single_rule(string $fo, string $bo, string $expected, ?string $relative): void
    {
        $suite = $this->suite(['PRESTAFLOW_FO_URL' => $fo, 'PRESTAFLOW_BO_URL' => $bo]);

        $this->assertSame($expected, $suite->getGlobals()['BO']['URL']);
        $this->assertSame($relative, $suite->relative());
    }

    public function test_front_office_url_is_unchanged(): void
    {
        $suite = $this->suite(['PRESTAFLOW_FO_URL' => 'https://x/?lang=2', 'PRESTAFLOW_BO_URL' => 'admin-dev/']);

        $this->assertSame('https://x/?lang=2/', $suite->getGlobals()['FO']['URL']);
    }

    public function test_missing_back_office_url_defaults_to_admin_dev(): void
    {
        $suite = $this->suite(['PRESTAFLOW_FO_URL' => 'https://x/fr?y=1', 'PRESTAFLOW_BO_URL' => null]);

        $this->assertSame('https://x/fr/admin-dev/', $suite->getGlobals()['BO']['URL']);
        $this->assertSame('admin-dev/', $suite->relative());
    }
}
```

  Si un `.env*` du dépôt définit `PRESTAFLOW_BO_URL` et fait échouer `test_missing_back_office_url_defaults_to_admin_dev`, marque ce test sauté (`markTestSkipped`) quand `getenv('PRESTAFLOW_BO_URL') !== false` après la construction, et signale-le dans ton rapport. Ne modifie aucun `.env*`.

- [ ] **Step 2 : constater l'échec.** `php vendor/bin/phpunit --filter LoadGlobalsBackOfficeUrlTest > "$SP/bo-t2-red.txt" 2>&1; tail -15 "$SP/bo-t2-red.txt"`. Échecs attendus :
  - `slash initial` : `https://x//admin/` ;
  - `requête FO ignorée` ;
  - `protocole` ;
  - `absolue majuscules` ;
  - `relatif rogné` ;
  - le `$relative` des cas `protocole` et `vide` ;
  - `test_missing_back_office_url_defaults_to_admin_dev`, à cause de la requête FO.

- [ ] **Step 3 : implémentation.** Dans `src/Tests/TestsSuite.php` :
  - ajoute `use PrestaFlow\Library\Utils\BackOfficeUrl;` au-dessus de `use PrestaFlow\Library\Utils\Env;` ;
  - dans `loadGlobals()`, remplace ce bloc :

```php
        $backOfficeUrl = Env::get('PRESTAFLOW_BO_URL', $frontOfficeUrl . 'admin-dev/');
        $this->backOfficeRelative = Env::has('PRESTAFLOW_BO_URL') ? null : 'admin-dev/';
        if (!str_starts_with($backOfficeUrl, 'https://') && !str_starts_with($backOfficeUrl, 'http://')) {
            $this->backOfficeRelative = (string) $backOfficeUrl;
            $backOfficeUrl = $frontOfficeUrl . $backOfficeUrl;
        }
        if (!str_ends_with($backOfficeUrl, '/')) {
            $backOfficeUrl .= '/';
        }
```

par :

```php
        // Règle unique (BackOfficeUrl, la même que l'app et que $backOfficeUrl) :
        // chemin relatif complété par l'URL FO sans requête ni fragment,
        // `//hôte` au schéma de la FO, absolue gardée. Aucun refus ici.
        $rawBackOffice = (string) Env::get('PRESTAFLOW_BO_URL', 'admin-dev/');
        $this->backOfficeRelative = BackOfficeUrl::isRelative($rawBackOffice) ? trim($rawBackOffice) : null;
        $backOfficeUrl = BackOfficeUrl::resolve($rawBackOffice, $frontOfficeUrl);
```

  Garde le commentaire du docblock de `$backOfficeRelative` vers la ligne 268. Mets-le à jour s'il le faut : « PRESTAFLOW_BO_URL relative (chemin, ni absolue ni `//hôte`), ou défaut `admin-dev/` ».

- [ ] **Step 4 : vert.** `php vendor/bin/phpunit --filter 'LoadGlobalsBackOfficeUrlTest|VisualTestsSuiteUrlsTest' > "$SP/bo-t2.txt" 2>&1; tail -3 "$SP/bo-t2.txt"` → `OK`.

- [ ] **Step 5 : README.** Remplace la ligne :

```
The back-office area also uses `PRESTAFLOW_BO_URL` (admin URL), `PRESTAFLOW_BO_EMAIL` and `PRESTAFLOW_BO_PASSWD` (login).
```

par :

```
The back-office area also uses `PRESTAFLOW_BO_URL` (admin URL, default `admin-dev/`), `PRESTAFLOW_BO_EMAIL` and `PRESTAFLOW_BO_PASSWD` (login). A relative `PRESTAFLOW_BO_URL` is completed from `PRESTAFLOW_FO_URL` with the same rule as `$backOfficeUrl` below (shop path kept, query and fragment dropped, `//host` takes the shop scheme); nothing is refused for environment variables.
```

- [ ] **Step 6 : suite complète** en 8.4 et en 8.1 (`bo-t2-84.txt`, `bo-t2-81.txt`). Total attendu : 678 + 10 = 688 tests (1 test sauté en 8.1, comme au départ).

- [ ] **Step 7 : commit.**

```bash
git --no-replace-objects add src/Tests/TestsSuite.php tests/Unit/Tests/LoadGlobalsBackOfficeUrlTest.php README.md
git --no-replace-objects commit -m "fix(urls): PRESTAFLOW_BO_URL relative résolue avec la règle unique du back-office

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3 : livraison (coordinateur)

**Goal :** branche relue et verte, PR vers `dev`. La release et le bump de l'app se font sur accord de l'utilisateur.

**Files :** `composer.lock` de l'app, au bump seulement.

**Acceptance Criteria :**
- [ ] Revue finale faite, suite complète verte en 8.4 et 8.1.
- [ ] PR « Une seule règle de résolution des URL du back-office » vers `dev`.
- [ ] Sur demande de l'utilisateur :
  - merge de la PR, puis release `dev` → `main`, en commits de merge ;
  - puis, dans un worktree de l'app partant de `origin/main`, `composer update prestaflow/php-library` (seul `composer.lock` doit changer) ;
  - suite PHP de l'app verte ;
  - PR app.

**Verify :** `gh pr view <N> --repo PrestaFlow/php-library --json baseRefName,state` → `dev`, `OPEN`.

**Steps :**
- [ ] `git --no-replace-objects push -u origin feat/bo-url-resolve`.
- [ ] `gh pr create --repo PrestaFlow/php-library --base dev --title "Une seule règle de résolution des URL du back-office" --body-file "$SP/bo-pr.md"`. Le corps contient :
  - le tableau avant / après de la spec ;
  - « aucun nouveau refus » ;
  - `VisualTestsSuite::resolveBackOfficeUrl()` gardée ;
  - puis `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.
- [ ] Merges, release et bump : seulement sur demande, dans l'ordre ci-dessus.

---

## Auto-relecture : couverture de la spec

| Spec | Tâche |
|---|---|
| Utilitaire `BackOfficeUrl::resolve()` / `isRelative()` | 1 |
| `resolveBackOfficeUrl()` gardée, qui délègue | 1 |
| `loadGlobals()` : résolution, `$backOfficeRelative`, valeur vide, FO inchangée | 2 |
| Aucun nouveau refus | 2 (aucune exception ajoutée) |
| Tests : cas déplacés, `isRelative`, tableau « Problème », défaut | 1, 2 |
| README | 2 |
| Livraison lib puis bump de l'app | 3 |
