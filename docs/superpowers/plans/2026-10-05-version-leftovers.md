# Version PrestaShop : restes de la relecture : plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** une seule règle « majeure → namespace », sans test mort dans `Translations`, avec un montage cohérent dans le test des tags visuels.

**Architecture :** `Version::namespaceFromMajor()` (statique) est utilisée par `getMajorVersion()` et `ImportPage`. Le reste n'est que du nettoyage, sans changement de comportement.

**Tech Stack :** PHP ^8.1, PHPUnit 10.5.

**Spec :** `docs/superpowers/specs/2026-10-05-version-leftovers-design.md`

---

## Contexte pour l'implémenteur

- **Dépôt :** worktree `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual`, branche `chore/version-namespace-helper`. Les commandes se lancent depuis ce dossier.
- **Ne pas toucher** `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library`.
- **PHP :**
  - `php` = 8.4 ;
  - `"$HOME/Library/Application Support/Herd/bin/php81"` = 8.1.
- **Tests :**
  - suite complète : `php vendor/bin/phpunit` (542 tests verts au départ) ;
  - redirige TOUJOURS la sortie vers un fichier, par exemple `/private/tmp/claude-501/-Users-jonathan-Documents-GitHub-PrestaFlow-app/2393cd17-28d4-44c5-96f2-fa24f981a8c8/scratchpad/<nom>.txt 2>&1`, puis lance `tail -3`. Un processus Chrome enfant bloque les pipes.
- **Commits :**
  - chemins explicites, jamais `git add -A`, `git add .` ni `git commit -a` ;
  - messages en français, terminés par une ligne vide puis `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Lecture des fichiers :** `rtk proxy cat` ou `sed -n`. Garde les commentaires non visés.

---

### Task 1 : `Version::namespaceFromMajor()` et `Translations`

**Goal :** la règle « majeure → namespace » est écrite une seule fois, et le test mort de `getCatalog` disparaît.

**Files :**
- Modify : `src/Traits/Version.php` (`getMajorVersion()` vers la l.135 ; nouvelle méthode juste avant)
- Modify : `src/Traits/ImportPage.php` (`pageNamespaceVersion()` vers la l.75)
- Modify : `src/Resolvers/Translations.php` (vers la l.80)
- Modify : `tests/Unit/Pages/PageVersionIsolationTest.php` (un test ajouté)

**Acceptance Criteria :**
- [ ] `namespaceFromMajor('1.7')` vaut `'7'`, `('1.6')` vaut `'6'`, `('8')` vaut `'8'`, `('9')` vaut `'9'`.
- [ ] `getMajorVersion(namespace: true)` et `pageNamespaceVersion()` l'utilisent. Plus aucun `substr(…, strlen('1.'))` ni `substr($major, 2)` pour cette règle.
- [ ] `getCatalog()` n'a plus `if ($this->getMajorVersion() !== null)`.
- [ ] Suite complète verte en 8.4 et en 8.1.

**Verify :** `php vendor/bin/phpunit tests/Unit/Pages/PageVersionIsolationTest.php` → OK

**Steps :**

- [ ] **Step 1 : test**

Ajoute dans `tests/Unit/Pages/PageVersionIsolationTest.php` :

```php
    public function test_namespace_from_major_drops_the_leading_1(): void
    {
        // Un appel statique direct sur un trait est déprécié : on passe par une classe qui l'utilise.
        $parser = new class {
            use Version;
            public array $globals = [];
        };

        $this->assertSame('7', $parser::namespaceFromMajor('1.7'));
        $this->assertSame('6', $parser::namespaceFromMajor('1.6'));
        $this->assertSame('8', $parser::namespaceFromMajor('8'));
        $this->assertSame('9', $parser::namespaceFromMajor('9'));
    }
```

Lance `php vendor/bin/phpunit tests/Unit/Pages/PageVersionIsolationTest.php` (sortie vers un fichier) → FAIL, méthode inexistante.

- [ ] **Step 2 : implémentation**

Dans `src/Traits/Version.php`, juste avant le docblock de `getMajorVersion()`, ajoute :

```php
    /** Segment de namespace des pages pour une majeure : '1.7' → '7', '1.6' → '6', '9' → '9'. */
    public static function namespaceFromMajor(string $majorVersion): string
    {
        return str_starts_with($majorVersion, '1.') ? substr($majorVersion, strlen('1.')) : $majorVersion;
    }
```

Dans `getMajorVersion()`, remplace la fin :

```php
        $majorVersion = $this->versions['majorVersion'];

        if ($namespace && str_starts_with($majorVersion, '1.')) {
            return substr($majorVersion, strlen('1.'));
        }

        return $majorVersion;
```

par :

```php
        $majorVersion = $this->versions['majorVersion'];

        return $namespace ? self::namespaceFromMajor($majorVersion) : $majorVersion;
```

Dans `src/Traits/ImportPage.php`, `pageNamespaceVersion()`, remplace :

```php
        $major = self::parseVersions($patchVersion)['majorVersion'];

        return str_starts_with($major, '1.') ? substr($major, 2) : $major;
```

par :

```php
        return self::namespaceFromMajor(self::parseVersions($patchVersion)['majorVersion']);
```

Dans `src/Resolvers/Translations.php`, `getCatalog()`, remplace :

```php
        if ($this->getMajorVersion() !== null) {
            $pathToCatalog = $basePath.$this->getMajorVersion().'/'.$fileName;
            if (file_exists($pathToCatalog)) {
                $majorCatalog = json_decode(file_get_contents($pathToCatalog), true);
                if (!is_array($majorCatalog)) {
                    $majorCatalog = [];
                }
            }
        }
```

par le même bloc sans la condition englobante, dé-indenté d'un niveau :

```php
        $pathToCatalog = $basePath.$this->getMajorVersion().'/'.$fileName;
        if (file_exists($pathToCatalog)) {
            $majorCatalog = json_decode(file_get_contents($pathToCatalog), true);
            if (!is_array($majorCatalog)) {
                $majorCatalog = [];
            }
        }
```

Les blocs mineure et patch qui suivent ne changent pas.

- [ ] **Step 3 : vérifier**

```bash
php vendor/bin/phpunit tests/Unit/Pages/PageVersionIsolationTest.php
grep -n "substr(\$major, 2)\|strlen('1.')" src/Traits/ImportPage.php src/Traits/Version.php
php vendor/bin/phpunit
"$HOME/Library/Application Support/Herd/bin/php81" vendor/bin/phpunit
```

Expected :
- `PageVersionIsolationTest` : OK ;
- le `grep` ne renvoie que la ligne de `namespaceFromMajor` ;
- la suite complète est verte (543) en 8.4 et en 8.1.

- [ ] **Step 4 : commit**

```bash
git add src/Traits/Version.php src/Traits/ImportPage.php src/Resolvers/Translations.php tests/Unit/Pages/PageVersionIsolationTest.php
git commit -m "refactor(version): namespaceFromMajor, seule règle majeure → namespace ; test mort retiré de getCatalog

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2 : montage cohérent du test des tags visuels

**Goal :** `makePage()` de `VisualCheckpointTagTest` construit la page avec un `patchVersion`, au lieu de forcer une majeure.

**Files :**
- Modify : `tests/Unit/Visual/VisualCheckpointTagTest.php`

**Acceptance Criteria :**
- [ ] `makePage(array $globals = [], string $patchVersion = '9.0.0')` et plus aucun `setMajorVersion` dans `makePage`.
- [ ] Les assertions de tag sont inchangées (`auto-v9-…`, `auto-v1.7-…`, `auto-v8-…`) et vertes.

**Verify :** `php vendor/bin/phpunit tests/Unit/Visual/VisualCheckpointTagTest.php` → OK

**Steps :**

- [ ] **Step 1 : `makePage`**

- Signature : `private function makePage(array $globals = [], ?string $major = '9'): CommonPage` devient `private function makePage(array $globals = [], string $patchVersion = '9.0.0'): CommonPage`.
- La classe anonyme est construite avec `('en', $patchVersion, $globals)` au lieu de `('en', '8.1.0', $globals)`.
- Supprime ce bloc :

```php
        if ($major !== null) {
            $page->setMajorVersion($major);
        }
```

- [ ] **Step 2 : appels**

- `testPs17MajorVersionIsNotRenderedAsQuestionMark` : `$this->makePage(['PS_VERSION' => '1.7.8.11'], '1.7')` devient `$this->makePage([], '1.7.8.11')`.
- `testMajorVersionComesFromThisPageOnly` : remplace les trois lignes

```php
        $page = $this->makePage([], null);
        $other = $this->makePage([], null);
        $other->setMajorVersion('1.7');
```

par :

```php
        $page = $this->makePage([], '8.1.0');
        $other = $this->makePage([], '1.7.8.11');
```

  et adapte le commentaire au-dessus : « Deux pages de versions différentes : le tag de l'une ne dépend pas de l'autre. »
- Les autres appels `makePage()` sans argument restent tels quels (défaut `'9.0.0'` → `auto-v9-…`).

- [ ] **Step 3 : vérifier**

```bash
php vendor/bin/phpunit tests/Unit/Visual/VisualCheckpointTagTest.php
php vendor/bin/phpunit
```

Expected : OK, puis la suite complète verte.

- [ ] **Step 4 : commit**

```bash
git add tests/Unit/Visual/VisualCheckpointTagTest.php
git commit -m "test(visual): makePage construit la page avec son patch au lieu de forcer la majeure

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3 : livraison (coordinateur)

- [ ] Relecture finale, PR → `dev`, CI verte.
- [ ] Sur demande : merge, release → `main`, puis mise à jour de la lib dans l'app.
