# Workflow Visual sur PrestaShop 1.7.8 et 8.2 — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faire tourner la régression visuelle du front-office (`FrontOfficeClassic`, EN + FR × desktop + mobile) aussi sur les boutiques Flashlight PrestaShop 1.7.8.11 et 8.2.8, en plus des deux lignes 9.2.0 existantes, sans changer le comportement de la lib.

**Architecture:** Deux changements seulement. (1) Les suites visuelles ouvrent le panier vide par `cart?action=show` / `panier?action=show` : en 1.7, `/cart` avec un panier vide redirige vers l'accueil. (2) La matrice de `.github/workflows/visual.yml` porte la version de PrestaShop (`ps`) et passe à 4 lignes ; `matrix.ps` remplace le `9.2.0` codé en dur (nom du job, `PRESTAFLOW_PS_VERSION`, artefact) et entre dans les clés de cache des références, passées en `v3`. Le script `.github/visual/install-french.sh` ne change pas (il gère déjà `AppKernel`/`AdminKernel`).

**Tech Stack:** GitHub Actions, Docker Compose + images `prestashop/prestashop-flashlight` (1.7.8.11-nginx, 8.2.8-nginx, 9.2.0-nginx), PHP 8.3, PHPUnit 10, le CLI `bin/prestaflow`, `gh`.

---

Spec : `docs/superpowers/specs/2026-10-01-visual-older-prestashop-design.md`.

## Constats vérifiés en écrivant ce plan (2026-10-01)

| Sujet | Constat |
|---|---|
| Query string dans `path` | **Supportée.** `VisualTestsSuite::resolveUrl()` concatène `base + prefix + ltrim(path, '/')` sans parser l'URL, et `FrontOfficePage::goToUrl()` navigue tel quel. Les suites utilisent déjà `search?s=test` / `recherche?s=test`. Vérifié : `FrontOfficeClassic` instanciée sans navigateur résout `search` en FR vers `http://localhost:8092/fr/recherche?s=test`. La spec est donc correcte sur ce point. |
| Panier en 1.7 (local, port 8017) | `/cart` → **302 vers `/`** ; `/cart?action=show` → 200. |
| Panier en 9.2 (local) | `/en/cart?action=show` et `/fr/panier?action=show` → 200 sur 8092 et 8093. |
| ps17 local | Une seule langue : `/en/` et `/fr/` → 404, `/login` et `/search?s=test` → 200. D'où `PRESTAFLOW_PREFIX_LOCALE=false` et EN seul en local. |
| ps82 local | Non démarré (port 8082 : pas de réponse). La 8.2 n'est vérifiée qu'en CI. |
| Nom des références | `{scope}.{checkpoint}--auto-v{majeure}-{L}x{H}-{locale}.png`, la majeure valant `1.7`, `8` ou `9` (`VisualTag::majorFromVersion`). Ex. `front-office-classic.cart-empty--auto-v1.7-1920x1080-en.png`. |
| Images de diff | Écrites à **chaque comparaison** (pas seulement en cas d'écart) dans `prestaflow/screens/diff/`. Une exécution qui crée les références n'en produit pas. 8 checkpoints × 4 passages = 32 diffs par artefact quand tout compare. |
| Protection de `main` | 11 checks requis aujourd'hui, **dont déjà** `Visual (PrestaShop 9.2.0, classic)` et `Visual (PrestaShop 9.2.0, hummingbird)` (app_id 15368 = GitHub Actions). L'en-tête actuel de `visual.yml` (« Not a required check, for now ») est donc périmé : la Tâche 2 le corrige. |
| Tests unitaires | `./vendor/bin/phpunit --testsuite Unit` : 465 tests OK avant ce plan. Aucun test ne vérifie encore les définitions des suites `Suites/Visual/*` : la Tâche 1 en ajoute un. |
| actionlint | Non installé sur la machine. Validation YAML par `ruby -ryaml`. |

**Écart avec la spec à connaître :** aucun écart bloquant. Deux précisions :

1. La spec dit que les captures FR des artefacts 1.7 / 8.2 sont « regardées ». Ce n'est pas qu'esthétique : si le pack FR 1.7 ou 8 ne traduisait pas les réécritures (`connexion`, `panier`, `nous-contacter`, `recherche`), ces pages répondraient 404, la vérification `/en/` `/fr/` resterait verte (elle ne teste que l'accueil) et le premier run **enregistrerait des 404 comme références**. La Tâche 3 en fait donc un critère explicite, avec la conduite à tenir.
2. L'en-tête du workflow est réécrit en entier (versions couvertes, raison du chemin de panier, cache `v3`, et statut « requis » réel des lignes 9.2).

## Contraintes pour l'exécutant

- **Travailler uniquement dans le worktree** `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual` (branche `ci/visual-older-ps`). Ne **jamais** toucher `/Users/jonathan/Documents/GitHub/PrestaFlow/php-library` (checkout de l'utilisateur).
- **Ne jamais lancer `docker compose` depuis le worktree en local.** Les boutiques locales ps17 / ps92 tournent sous le projet compose `php-library` (checkout de l'utilisateur) ; un `docker compose up` ici créerait un projet `php-library-visual` en conflit sur les ports 8017/8092/8093. On se contente de les appeler en HTTP.
- **Ne pas installer le français sur la boutique ps17 locale** (état de la boutique de l'utilisateur). Le FR 1.7 / 8.2 se vérifie en CI.
- **Jamais** `git add -A`, `git add .` ni `git commit -a` : toujours des chemins explicites.
- Ne jamais committer `visual-baseline/`, `visual-output/` ni `prestaflow/` (ignorés, mais les vérifications locales se font de toute façon dans un répertoire temporaire hors du dépôt).
- Pas de merge, pas de release, pas de modification de la protection de branche sans accord explicite de l'utilisateur.
- Chaque commit se termine par le trailer `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## File Structure

**Modifiés :**
- `src/Tests/Suites/Visual/FrontOfficeClassic.php` — chemin du checkpoint `cart-empty` (`cart?action=show`, FR `panier?action=show`) + docblock (versions couvertes, raison du chemin).
- `src/Tests/Suites/Visual/FrontOfficeHummingbird.php` — même chemin de panier, par cohérence + docblock.
- `.github/workflows/visual.yml` — matrice à 4 lignes avec `ps`, `matrix.ps` au lieu de `9.2.0`, clés de cache `visual-refs-v3-<ps>-<theme>-…`, en-tête réécrit.

**Créés :**
- `tests/Unit/Visual/FrontOfficeSuitesTest.php` — vérifie l'URL du panier des deux suites livrées (avec et sans préfixe de locale).

**Inchangés :** `.github/visual/install-french.sh` (sauf contingence de la Tâche 3), `docker-compose.yml`, `live-smoke.yml`, tout le code de la lib.

---

### Task 1: Suites — panier en `cart?action=show`

**Goal:** Le checkpoint `cart-empty` des deux suites visuelles capture la vraie page panier sur toutes les versions (en 1.7, `/cart` redirige vers l'accueil quand le panier est vide).

**Files:**
- Create: `tests/Unit/Visual/FrontOfficeSuitesTest.php`
- Modify: `src/Tests/Suites/Visual/FrontOfficeClassic.php`
- Modify: `src/Tests/Suites/Visual/FrontOfficeHummingbird.php`

**Acceptance Criteria:**
- [ ] Dans les deux suites, `cart-empty` a `'path' => 'cart?action=show'` et `'paths' => ['fr' => 'panier?action=show']` ; les autres checkpoints, masques et `scrollBelow` sont inchangés.
- [ ] Les docblocks expliquent le chemin du panier ; celui de `FrontOfficeClassic` dit qu'elle tourne sur 1.7.8.11, 8.2.8 et 9.2.0.
- [ ] `tests/Unit/Visual/FrontOfficeSuitesTest.php` échoue avant la modification des suites et passe après (4 tests).
- [ ] `./vendor/bin/phpunit --testsuite Unit` : OK, 469 tests.
- [ ] Local, ps17 (8017, EN, sans préfixe) : `FrontOfficeClassic` desktop + mobile lancée deux fois depuis un répertoire temporaire ; les 4 exécutions sortent en 0 ; le 2ᵉ passage compare (8 diffs par device) ; la capture `cart-empty` montre la page panier, pas l'accueil.
- [ ] Local, ps92 (8092 Hummingbird, 8093 Classic, préfixe `/en/` `/fr/`) : `cart-empty` seul, desktop, EN + FR, deux passages, tout en 0.
- [ ] Aucun fichier de sortie (références, captures) laissé dans le dépôt ni dans le répertoire temporaire.

**Verify:**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual && ./vendor/bin/phpunit --testsuite Unit
```

Attendu : `OK (469 tests, …)`. Puis les vérifications locales des étapes 6 et 7 (toutes les lignes `exit=0`).

**Steps:**

- [ ] **Step 1: Pré-vol — les boutiques locales répondent comme prévu**

```bash
for u in "http://localhost:8017/cart" "http://localhost:8017/cart?action=show" \
         "http://localhost:8092/en/cart?action=show" "http://localhost:8092/fr/panier?action=show" \
         "http://localhost:8093/en/cart?action=show" "http://localhost:8093/fr/panier?action=show"; do
  echo "$u -> $(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 10 "$u")"
done
```

Attendu : `/cart` sur 8017 → `302 http://localhost:8017/` ; toutes les autres → `200`. Si une boutique ne répond pas, **ne pas** la démarrer depuis le worktree : demander à l'utilisateur de la relancer depuis son checkout, ou sauter la vérification correspondante en le signalant.

- [ ] **Step 2: Écrire le test qui échoue**

Créer `tests/Unit/Visual/FrontOfficeSuitesTest.php` :

```php
<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\Suites\Visual\FrontOfficeClassic;
use PrestaFlow\Library\Tests\Suites\Visual\FrontOfficeHummingbird;
use PrestaFlow\Library\Tests\VisualTestsSuite;

/**
 * URL réellement ouvertes par les suites visuelles livrées (celles que lance
 * .github/workflows/visual.yml).
 *
 * Le panier vide passe par cart?action=show : en 1.7, /cart avec un panier
 * vide redirige vers l'accueil, et la capture « cart-empty » aurait été celle
 * de la home. La query string traverse resolvePath() / resolveUrl() telle quelle.
 */
final class FrontOfficeSuitesTest extends TestCase
{
    /** @return array<string, array{class-string<VisualTestsSuite>}> */
    public static function suites(): array
    {
        return [
            'classic' => [FrontOfficeClassic::class],
            'hummingbird' => [FrontOfficeHummingbird::class],
        ];
    }

    /** @param class-string<VisualTestsSuite> $class */
    private function suite(string $class, bool $prefixLocale): VisualTestsSuite
    {
        $suite = new $class(loadGlobals: false, getBrowser: false);
        $suite->setGlobals([
            'FO' => ['URL' => 'http://localhost:8017/'],
            'PREFIX_LOCALE' => $prefixLocale,
        ]);

        return $suite;
    }

    private function cartUrl(VisualTestsSuite $suite, string $locale): string
    {
        foreach ($suite->checkpoints() as $cp) {
            if ($cp['name'] === 'cart-empty') {
                return $suite->resolveUrl((string) $suite->resolvePath($cp, $locale), $locale);
            }
        }

        $this->fail(sprintf('%s : pas de checkpoint « cart-empty »', $suite::class));
    }

    #[DataProvider('suites')]
    public function test_cart_is_opened_with_action_show_in_both_locales(string $class): void
    {
        $suite = $this->suite($class, prefixLocale: true);

        $this->assertSame('http://localhost:8017/en/cart?action=show', $this->cartUrl($suite, 'en'));
        $this->assertSame('http://localhost:8017/fr/panier?action=show', $this->cartUrl($suite, 'fr'));
    }

    #[DataProvider('suites')]
    public function test_cart_without_locale_prefix_keeps_the_query_string(string $class): void
    {
        $suite = $this->suite($class, prefixLocale: false);

        $this->assertSame('http://localhost:8017/cart?action=show', $this->cartUrl($suite, 'en'));
    }
}
```

- [ ] **Step 3: Vérifier qu'il échoue**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual && ./vendor/bin/phpunit tests/Unit/Visual/FrontOfficeSuitesTest.php
```

Attendu : `FAILURES! Tests: 4, … Failures: 4`, avec des diffs du type `-'http://localhost:8017/en/cart?action=show'` / `+'http://localhost:8017/en/cart'`.

- [ ] **Step 4: Modifier `FrontOfficeClassic`**

Dans `src/Tests/Suites/Visual/FrontOfficeClassic.php`, remplacer le docblock de classe et la ligne `cart-empty` :

```php
/**
 * Régression visuelle du front-office, thème Classic.
 *
 * Mêmes checkpoints que le modèle « Front-office essentiel » de l'app, en
 * anglais et en français. `path` reste le chemin anglais (comportement en
 * inchangé) ; `paths['fr']` donne la réécriture française installée par le
 * pack de langue (connexion, panier, nous-contacter, recherche). La CI installe
 * le français (.github/visual/install-french.sh) et lance avec
 * PRESTAFLOW_PREFIX_LOCALE=true : deux langues actives → URLs en /en/ et /fr/.
 *
 * Une seule suite pour toutes les versions : la CI la lance sur 1.7.8.11,
 * 8.2.8 et 9.2.0, dont le thème Classic a les mêmes sélecteurs (#header,
 * #footer, .cart-products-count, #carousel).
 *
 * Panier : `cart?action=show`, pas `cart`. En 1.7, /cart avec un panier vide
 * redirige vers l'accueil ; ?action=show affiche la page panier sur toutes les
 * versions.
 *
 * Une exécution = un device (PRESTAFLOW_DEVICE) × une locale (PRESTAFLOW_LOCALE).
 */
```

```diff
-        ['name' => 'cart-empty', 'path' => 'cart', 'paths' => ['fr' => 'panier'], 'scrollBelow' => '#header'],
+        ['name' => 'cart-empty', 'path' => 'cart?action=show', 'paths' => ['fr' => 'panier?action=show'], 'scrollBelow' => '#header'],
```

- [ ] **Step 5: Modifier `FrontOfficeHummingbird`**

Dans `src/Tests/Suites/Visual/FrontOfficeHummingbird.php`, ajouter un paragraphe au docblock, juste avant la ligne « Une exécution = … » :

```php
 * Panier : `cart?action=show`, comme FrontOfficeClassic (en 1.7, /cart avec
 * un panier vide redirige vers l'accueil). Cette suite ne tourne qu'en 9.2,
 * où les deux URL affichent le panier : le chemin est aligné par cohérence.
 *
```

et la ligne `cart-empty` :

```diff
-        ['name' => 'cart-empty', 'path' => 'cart', 'paths' => ['fr' => 'panier'], 'masks' => ['.header-block__badge']],
+        ['name' => 'cart-empty', 'path' => 'cart?action=show', 'paths' => ['fr' => 'panier?action=show'], 'masks' => ['.header-block__badge']],
```

Puis :

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual && ./vendor/bin/phpunit tests/Unit/Visual/FrontOfficeSuitesTest.php && ./vendor/bin/phpunit --testsuite Unit
```

Attendu : `OK (4 tests, 6 assertions)` puis `OK (469 tests, …)`. `git diff --stat` ne montre que les deux suites (le test est encore non suivi).

- [ ] **Step 6: Vérification locale sur la 1.7 (port 8017)**

La boutique locale n'a qu'une langue : EN, sans préfixe. On lance depuis un répertoire temporaire pour que `visual-baseline/` et `prestaflow/` (relatifs au répertoire courant) n'atterrissent pas dans le dépôt. Le `.env` éventuel du dépôt n'existe pas dans le worktree ; les variables passées en ligne gagnent de toute façon (Dotenv immuable).

```bash
bash <<'SH'
LIB=/Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
WORK=$(mktemp -d -t pf-visual17)
echo "WORK=$WORK"
cd "$WORK" || exit 1
for pass in 1 2; do
  for device in desktop mobile; do
    PRESTAFLOW_FO_URL=http://localhost:8017/ \
    PRESTAFLOW_BO_URL=http://localhost:8017/admin-dev/ \
    PRESTAFLOW_PS_VERSION=1.7.8.11 \
    PRESTAFLOW_THEME=classic \
    PRESTAFLOW_PREFIX_LOCALE=false \
    PRESTAFLOW_LOCALE=en \
    PRESTAFLOW_DEVICE=$device \
      php "$LIB/bin/prestaflow" run "$LIB/src/Tests/Suites/Visual/FrontOfficeClassic.php" > "run-$pass-$device.log" 2>&1
    echo "pass $pass $device exit=$?"
    echo "  diffs: $(find prestaflow/screens/diff -name '*.png' 2>/dev/null | wc -l | tr -d ' ')"
  done
done
echo "references: $(ls visual-baseline | grep -c -- '--auto-v1.7-')"
ls visual-baseline/*cart-empty*
SH
```

Attendu :
- quatre lignes `exit=0` ;
- `diffs: 0` au passage 1 (création des références), `diffs: 8` au passage 2 pour chaque device (comparaison effective) ;
- `references: 16` (8 checkpoints × 2 devices), tous en `--auto-v1.7-…-en.png` ;
- deux références `front-office-classic.cart-empty--auto-v1.7-…-en.png`.

Si un passage 2 échoue, lire `run-2-<device>.log` et l'image de diff du checkpoint (`prestaflow/screens/diff/`) : un écart d'un passage à l'autre est une instabilité à signaler, pas à masquer.

Ouvrir la référence desktop `cart-empty` avec l'outil Read (image) : on doit voir la page panier (titre « Shopping Cart » / « Cart », panier vide), **pas** le carrousel de l'accueil.

- [ ] **Step 7: Vérification locale sur la 9.2 (8092 Hummingbird, 8093 Classic), checkpoint panier seul**

La boutique 9.2 locale a le FR et le préfixe `/en/` : `PRESTAFLOW_PREFIX_LOCALE=true`.

```bash
bash <<'SH'
LIB=/Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
WORK=$(mktemp -d -t pf-visual92)
echo "WORK=$WORK"
cd "$WORK" || exit 1
run() { # port theme suite pass locale
  PRESTAFLOW_FO_URL=http://localhost:$1/ \
  PRESTAFLOW_BO_URL=http://localhost:$1/admin-dev/ \
  PRESTAFLOW_PS_VERSION=9.2.0 \
  PRESTAFLOW_THEME=$2 \
  PRESTAFLOW_PREFIX_LOCALE=true \
  PRESTAFLOW_VISUAL_ONLY=cart-empty \
  PRESTAFLOW_DEVICE=desktop \
  PRESTAFLOW_LOCALE=$5 \
    php "$LIB/bin/prestaflow" run "$LIB/src/Tests/Suites/Visual/$3.php" > "run-$1-$4-$5.log" 2>&1
  echo "$3 port $1 pass $4 $5 exit=$?"
}
for pass in 1 2; do
  for locale in en fr; do
    run 8092 hummingbird FrontOfficeHummingbird $pass $locale
    run 8093 classic FrontOfficeClassic $pass $locale
  done
done
ls visual-baseline
SH
```

Attendu : huit lignes `exit=0` ; `visual-baseline` contient 4 fichiers : `front-office-hummingbird.cart-empty--auto-v9-1920x1080-{en,fr}.png` et `front-office-classic.cart-empty--auto-v9-1920x1080-{en,fr}.png` (la taille exacte peut différer si le viewport réel diffère ; ce qui compte : 4 fichiers en `v9`, en/fr). Ouvrir la capture FR Classic avec Read : page « Panier » en français.

- [ ] **Step 8: Nettoyer les sorties locales**

```bash
rm -rf "${TMPDIR:-/tmp}"/pf-visual17.* "${TMPDIR:-/tmp}"/pf-visual92.*
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual && git status --short
```

Attendu : `git status --short` ne liste que `M src/Tests/Suites/Visual/FrontOfficeClassic.php`, `M src/Tests/Suites/Visual/FrontOfficeHummingbird.php`, `?? tests/Unit/Visual/FrontOfficeSuitesTest.php` (aucun `visual-baseline/`, `prestaflow/`).

(Sur macOS, `mktemp -d -t pf-visual17` crée `$TMPDIR/pf-visual17.XXXXXXXX` ; si le `WORK=` affiché à l'étape 6 / 7 est ailleurs, supprimer ce chemin-là.)

- [ ] **Step 9: Commit**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
git add src/Tests/Suites/Visual/FrontOfficeClassic.php src/Tests/Suites/Visual/FrontOfficeHummingbird.php tests/Unit/Visual/FrontOfficeSuitesTest.php
git commit -m "$(cat <<'EOF'
ci(visual): panier vide ouvert par cart?action=show

En 1.7, /cart avec un panier vide redirige vers l'accueil : la capture
cart-empty aurait été celle de la home. ?action=show affiche la page panier
sur 1.7, 8 et 9 (FR : panier?action=show). Hummingbird est aligné par
cohérence. Test unitaire sur l'URL du panier des deux suites livrées.

Vérifié en local : FrontOfficeClassic desktop + mobile ×2 sur la 1.7 (8017,
EN), et cart-empty EN/FR ×2 sur les deux boutiques 9.2 (8092, 8093).

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Workflow — matrice 1.7.8.11 / 8.2.8 / 9.2.0

**Goal:** `visual.yml` lance quatre jobs (ps17, ps82, ps92 Hummingbird, ps92 Classic), chacun avec sa version de PrestaShop, son artefact et son cache de références `v3`.

**Files:**
- Modify: `.github/workflows/visual.yml`

**Acceptance Criteria:**
- [ ] La matrice a 4 lignes `include` conformes au tableau de la spec (`ps`, `service`, `port`, `theme`, `suite`).
- [ ] Plus aucun `9.2.0` codé en dur hors de la matrice et de l'en-tête : nom du job `Visual (PrestaShop ${{ matrix.ps }}, ${{ matrix.theme }})`, `PRESTAFLOW_PS_VERSION: ${{ matrix.ps }}`, artefact `visual-${{ matrix.ps }}-${{ matrix.theme }}`.
- [ ] Clés de cache : restauration `visual-refs-v3-${{ matrix.ps }}-${{ matrix.theme }}-${{ github.run_id }}` + `restore-keys: visual-refs-v3-${{ matrix.ps }}-${{ matrix.theme }}-` ; sauvegarde (push sur `main` seulement) `visual-refs-v3-${{ matrix.ps }}-${{ matrix.theme }}-${{ github.run_id }}-${{ github.run_attempt }}`. Plus aucun `visual-refs-v2`.
- [ ] Les étapes Start / Wait / Install French / Check both languages / Run / Upload / Save sont inchangées hors des expressions ci-dessus.
- [ ] En-tête à jour : versions couvertes, raison du chemin de panier, cache `v3` par version × thème, statut requis réel (9.2 requis, 1.7 / 8.2 à ajouter après la release).
- [ ] YAML valide (`ruby -ryaml`) ; `actionlint` sans erreur s'il est disponible.
- [ ] Les deux noms de jobs 9.2 restent exactement `Visual (PrestaShop 9.2.0, classic)` et `Visual (PrestaShop 9.2.0, hummingbird)` (protection de `main`).

**Verify:**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual && ruby -ryaml -e 'y=YAML.load_file(".github/workflows/visual.yml"); j=y["jobs"]["visual"]; puts j["name"]; j["strategy"]["matrix"]["include"].each{|r| puts [r["ps"],r["service"],r["port"],r["theme"],r["suite"]].join(" ")}' && grep -c 'visual-refs-v3-${{ matrix.ps }}-${{ matrix.theme }}-' .github/workflows/visual.yml && ! grep -n 'visual-refs-v2\|PRESTAFLOW_PS_VERSION: .9\.2\.0\|visual-9\.2\.0' .github/workflows/visual.yml
```

Attendu :

```
Visual (PrestaShop ${{ matrix.ps }}, ${{ matrix.theme }})
1.7.8.11 ps17 8017 classic FrontOfficeClassic
8.2.8 ps82 8082 classic FrontOfficeClassic
9.2.0 ps92 8092 hummingbird FrontOfficeHummingbird
9.2.0 ps92 8093 classic FrontOfficeClassic
3
```

et code de sortie 0 (le `! grep` final ne trouve rien).

**Steps:**

- [ ] **Step 1: Réécrire l'en-tête (lignes 1 à 53)**

Remplacer tout le commentaire d'en-tête (de la ligne après `name: Visual` jusqu'à la ligne vide avant `on:`) par :

```yaml
# Visual regression of the front office against throwaway Flashlight shops,
# the same containers live-smoke.yml uses. One job per PrestaShop version ×
# theme:
#   - 1.7.8.11 (ps17, port 8017) and 8.2.8 (ps82, port 8082): Classic, the only
#     theme these versions ship, with src/Tests/Suites/Visual/FrontOfficeClassic.php;
#   - 9.2.0 (ps92): src/Tests/Suites/Visual/FrontOfficeHummingbird.php on shop 1
#     (port 8092) and FrontOfficeClassic.php on shop 2 (port 8093) of the same
#     container.
# One Classic suite serves every version: the selectors it relies on (#header,
# #footer, .cart-products-count, #carousel) are the same on 1.7, 8 and 9.
# Hummingbird does not exist before 9.
#
# The two 9.2.0 rows are required checks on main. The 1.7.8.11 and 8.2.8 rows
# are added to the branch protection once they have run green on main. Nothing
# here is `continue-on-error`: a red row stays red, and means "look at the
# uploaded diffs" first.
#
# The empty cart is opened with cart?action=show (panier?action=show in
# French), not cart: on 1.7, /cart with an empty cart redirects to the home
# page, so the "cart-empty" capture would have been the home page.
# ?action=show displays the cart page on every version.
#
# English and French. The Flashlight shops are installed with English only, so
# the job installs French itself once the shop is up, with
# .github/visual/install-french.sh (language pack downloaded and installed on
# every shop, URL prefix kept on the default language, caches cleared). It
# lives under .github/visual/ and NOT under docker/post-scripts/: the
# post-scripts run on every container of docker-compose.yml, and the smoke
# workflows must keep testing stock single-language shops. A failed install is
# fatal: the French checkpoints must never be captured on an English-only shop.
# With two active languages PrestaShop prefixes every URL (/en/..., /fr/...),
# hence PRESTAFLOW_PREFIX_LOCALE=true. 1.7 and 8 do it natively; 9 needs
# PS_DEFAULT_LANGUAGE_URL_PREFIX, which the script sets (unknown, and
# harmless, before 9). The suites keep the English paths as `path` and give
# the French rewrites (connexion, panier, nous-contacter, recherche,
# page-introuvable-prestaflow) through `paths['fr']`.
#
# Devices × locales: one `prestaflow run` is one device × one locale
# (VisualTestsSuite reads PRESTAFLOW_DEVICE and PRESTAFLOW_LOCALE), so the
# suite runs four times per job: desktop/mobile × en/fr. The reference files
# carry the PrestaShop major, the viewport size and the locale in their name
# (…--auto-v1.7-1920x1080-fr.png), so all four sets of references live side
# by side and two versions can never compare against each other.
#
# References: a CLI run reads and writes them in ./visual-baseline/ (relative
# to the working directory, see Utils\Screenshots::referencesBaseDir). That
# directory is cached per PrestaShop version × theme:
#   - restored on every run (push, pull_request, workflow_dispatch) from the
#     most recent `visual-refs-v3-<ps>-<theme>-*` entry;
#   - saved ONLY on push to main, under a key unique to the run, so a pull
#     request can never move the baseline; it only compares against it.
# A checkpoint with no reference yet becomes the reference and passes, so the
# first run (empty cache) creates the baselines and is green. To reset the
# baselines on purpose, bump the version in both keys. v3: the key gained the
# PrestaShop version (three rows are Classic now) and the cart checkpoint
# moved to cart?action=show, so the v2 references no longer apply.
#
# The run's captures and diffs (prestaflow/screens/actual and /diff) are
# uploaded on every run, one artifact per row, together with the references
# that were compared against.
```

- [ ] **Step 2: Nom du job et matrice**

```diff
   visual:
-    name: Visual (PrestaShop 9.2.0, ${{ matrix.theme }})
+    name: Visual (PrestaShop ${{ matrix.ps }}, ${{ matrix.theme }})
     runs-on: ubuntu-latest
 
     strategy:
       fail-fast: false
       matrix:
         include:
-          - service: ps92
+          - ps: '1.7.8.11'
+            service: ps17
+            port: 8017
+            theme: classic
+            suite: FrontOfficeClassic
+          - ps: '8.2.8'
+            service: ps82
+            port: 8082
+            theme: classic
+            suite: FrontOfficeClassic
+          - ps: '9.2.0'
+            service: ps92
             port: 8092
             theme: hummingbird
             suite: FrontOfficeHummingbird
           # Shop 2 of the same container, on its own port and its own theme.
-          - service: ps92
+          - ps: '9.2.0'
+            service: ps92
             port: 8093
             theme: classic
             suite: FrontOfficeClassic
```

Les versions sont entre quotes (comme dans `live-smoke.yml`) : `8.2.8` non quoté reste une chaîne en YAML, mais `9.2` ou `8.2` seuls deviendraient des flottants.

- [ ] **Step 3: Cache de restauration**

```diff
       # Restore only: saving is a separate step gated on push to main.
       # The primary key never matches (saved keys end with a run id), so the
-      # most recent entry for this theme is picked through the prefix.
+      # most recent entry for this version × theme is picked through the prefix.
       - name: Restore visual references
         id: refs
         uses: actions/cache/restore@v4
         with:
           path: visual-baseline
-          key: visual-refs-v2-${{ matrix.theme }}-${{ github.run_id }}
-          restore-keys: visual-refs-v2-${{ matrix.theme }}-
+          key: visual-refs-v3-${{ matrix.ps }}-${{ matrix.theme }}-${{ github.run_id }}
+          restore-keys: visual-refs-v3-${{ matrix.ps }}-${{ matrix.theme }}-
```

Aucun préfixe n'en recouvre un autre (`visual-refs-v3-9.2.0-classic-` n'est le début d'aucune autre clé), donc une ligne ne peut pas restaurer les références d'une autre.

- [ ] **Step 4: Commentaire de l'installation du FR**

```diff
-      # Once per job: each theme is its own job with its own container. Copied
+      # Once per job: each row is its own job with its own container. Copied
```

- [ ] **Step 5: Version passée à la suite, artefact, sauvegarde**

```diff
-          PRESTAFLOW_PS_VERSION: '9.2.0'
+          PRESTAFLOW_PS_VERSION: ${{ matrix.ps }}
```

```diff
-          name: visual-9.2.0-${{ matrix.theme }}
+          name: visual-${{ matrix.ps }}-${{ matrix.theme }}
```

```diff
-          key: visual-refs-v2-${{ matrix.theme }}-${{ github.run_id }}-${{ github.run_attempt }}
+          key: visual-refs-v3-${{ matrix.ps }}-${{ matrix.theme }}-${{ github.run_id }}-${{ github.run_attempt }}
```

- [ ] **Step 6: Valider**

Lancer la commande **Verify** ci-dessus. Puis :

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
command -v actionlint >/dev/null && actionlint .github/workflows/visual.yml || echo "actionlint absent : validation YAML seule"
git diff --stat
```

Attendu : actionlint sans sortie s'il est présent (ne pas télécharger d'image ni de binaire pour l'obtenir) ; `git diff --stat` : seul `.github/workflows/visual.yml` modifié.

Relire le diff complet (`git diff .github/workflows/visual.yml`) : hors en-tête, seules les lignes des étapes 2 à 5 changent.

- [ ] **Step 7: Commit**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
git add .github/workflows/visual.yml
git commit -m "$(cat <<'EOF'
ci(visual): régression visuelle aussi sur PrestaShop 1.7.8.11 et 8.2.8

La matrice porte la version de PrestaShop (ps) : deux lignes Classic
ajoutées (ps17 sur 8017, ps82 sur 8082), avec FrontOfficeClassic, en/fr ×
desktop/mobile comme en 9.2. matrix.ps remplace le 9.2.0 codé en dur dans
le nom du job, PRESTAFLOW_PS_VERSION et le nom de l'artefact ; les deux
noms de jobs 9.2 ne changent pas (checks requis sur main).

Cache des références en v3, par version × thème : trois lignes sont en
Classic, et le chemin du panier a changé. En-tête mis à jour.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Vérification en CI sur la PR (sans merge)

**Goal:** La PR vers `dev` montre les 4 jobs Visual verts, le FR installé et `/en/` `/fr/` à 200 sur 1.7 et 8.2, et des captures FR qui sont les vraies pages (pas des 404 ni l'accueil).

**Files:**
- Aucun en temps normal.
- Contingence seulement : `.github/visual/install-french.sh` et / ou `.github/workflows/visual.yml` (étape *Check both languages answer*).

**Acceptance Criteria:**
- [ ] Branche `ci/visual-older-ps` poussée, PR ouverte vers `dev` (titre et description en français, description terminée par `🤖 Generated with [Claude Code](https://claude.com/claude-code)`).
- [ ] Les 4 jobs `Visual (PrestaShop …)` sont verts, ainsi que les autres checks de la PR (PHPUnit, Smoke).
- [ ] Dans les logs des jobs 1.7.8.11 et 8.2.8 : `✅ French installed`, `/en/ answers 200`, `/fr/ answers 200`, et `no cached references: this run creates the baselines` (normal : cache `v3` neuf).
- [ ] Artefacts `visual-1.7.8.11-classic` et `visual-8.2.8-classic` : captures `-fr.png` de `login`, `cart-empty`, `contact`, `search` en français et sur la bonne page ; `not-found` montre la page 404 du thème ; `cart-empty` n'est pas l'accueil.
- [ ] La PR n'est **pas** mergée.

**Verify:**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual && gh pr checks "$(gh pr view ci/visual-older-ps --json number -q .number)"
```

Attendu : les quatre lignes `Visual (PrestaShop 1.7.8.11, classic)`, `Visual (PrestaShop 8.2.8, classic)`, `Visual (PrestaShop 9.2.0, classic)`, `Visual (PrestaShop 9.2.0, hummingbird)` en `pass`, comme tous les autres checks.

**Steps:**

- [ ] **Step 1: Pousser la branche**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
git log --oneline origin/dev..HEAD
git push -u origin ci/visual-older-ps
```

Attendu : 4 commits au-dessus de `origin/dev` (spec, plan, Tâche 1, Tâche 2). Si `origin/dev` a avancé entre-temps, `git fetch origin && git rebase origin/dev` avant de pousser (pas de force-push sur une branche déjà poussée sans prévenir).

- [ ] **Step 2: Ouvrir la PR vers `dev`**

```bash
BODY=$(mktemp -t pr-visual-older)
cat > "$BODY" <<'EOF'
## Pourquoi

Le workflow Visual ne vérifiait l'affichage du front-office que sur PrestaShop 9.2.0. La lib supporte aussi 1.7.8 et 8.2, que les smoke tests lancent déjà : on y vérifie maintenant l'affichage avec la même couverture (EN + FR × desktop + mobile).

Spec : `docs/superpowers/specs/2026-10-01-visual-older-prestashop-design.md` ; plan : `docs/superpowers/plans/2026-10-01-visual-older-prestashop.md`.

## Ce qui change

- **Suites `FrontOfficeClassic` / `FrontOfficeHummingbird`** : le checkpoint `cart-empty` ouvre `cart?action=show` (FR `panier?action=show`). En 1.7, `/cart` avec un panier vide redirige vers l'accueil ; `?action=show` affiche la page panier sur toutes les versions. Nouveau test `tests/Unit/Visual/FrontOfficeSuitesTest.php` sur l'URL du panier des deux suites.
- **`visual.yml`** :
  - matrice à 4 lignes avec la version (`ps`) : 1.7.8.11 (ps17, 8017, Classic), 8.2.8 (ps82, 8082, Classic), 9.2.0 Hummingbird (8092), 9.2.0 Classic (8093) ;
  - `matrix.ps` remplace le `9.2.0` codé en dur (nom du job, `PRESTAFLOW_PS_VERSION`, artefact) ; **les deux noms de jobs 9.2 sont inchangés** ;
  - cache des références `visual-refs-v3-<ps>-<theme>-…` (le premier run de chaque ligne recrée ses références) ;
  - en-tête mis à jour.
- `install-french.sh` : inchangé (il choisit déjà `AppKernel` en 1.7 / 8).

## Vérification

- `phpunit --testsuite Unit` : 469 tests OK.
- Local, 1.7 (8017, EN seul, sans préfixe) : `FrontOfficeClassic` desktop + mobile lancée deux fois, 2ᵉ passage en comparaison, tout vert ; `cart-empty` = page panier.
- Local, 9.2 (8092 / 8093, EN + FR) : `cart-empty` deux fois, tout vert.
- CI : voir les checks de cette PR (FR installé et `/en/` `/fr/` à 200 sur 1.7 et 8.2, captures FR contrôlées).

## Après la release

Ajouter « Visual (PrestaShop 1.7.8.11, classic) » et « Visual (PrestaShop 8.2.8, classic) » aux checks requis de `main` (commande fournie séparément).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
gh pr create --base dev --head ci/visual-older-ps \
  --title "ci(visual) : régression visuelle sur PrestaShop 1.7.8 et 8.2" \
  --body-file "$BODY"
rm -f "$BODY"
```

Attendu : l'URL de la PR. La noter.

- [ ] **Step 3: Attendre le run Visual**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
RUN=$(gh run list --workflow visual.yml --branch ci/visual-older-ps --event pull_request --limit 1 --json databaseId -q '.[0].databaseId')
echo "RUN=$RUN"
gh run watch "$RUN" --exit-status
```

Attendu : sortie 0, 4 jobs `✓`. (Un run dure plusieurs minutes : utiliser `gh run watch` en arrière-plan ou le Monitor plutôt qu'un `sleep`.)

- [ ] **Step 4: Lire les logs des nouvelles lignes**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
gh run view "$RUN" --log \
  | grep -E '^Visual \(PrestaShop (1\.7\.8\.11|8\.2\.8), classic\)' \
  | grep -E 'French installed|french id_lang|answers [0-9]{3}$|/(en|fr)/ answers|no cached references|references restored'
```

Attendu, pour chacune des deux lignes : `french id_lang=…`, `✅ French installed`, `/en/ answers 200`, `/fr/ answers 200`, `no cached references: this run creates the baselines`. Vérifier aussi que les 9.2 affichent `no cached references` (cache `v3` neuf, normal) et sont vertes.

- [ ] **Step 5: Télécharger les artefacts 1.7 et 8.2 et regarder les captures FR**

```bash
WORK=$(mktemp -d -t pf-visual-ci)
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
gh run download "$RUN" -n visual-1.7.8.11-classic -D "$WORK/v17"
gh run download "$RUN" -n visual-8.2.8-classic -D "$WORK/v82"
find "$WORK" -path '*desktop-fr*screens/actual*' -name '*.png' | sort
find "$WORK" -path '*visual-baseline*' -name '*.png' | sed 's#.*/##' | sort | head -40
```

Attendu : 8 captures par passage ; dans `visual-baseline/`, 32 références par artefact, en `--auto-v1.7-…` pour l'une et `--auto-v8-…` pour l'autre, en `-en.png` et `-fr.png`.

Ouvrir avec l'outil Read, pour **chacune** des deux versions, les captures desktop FR de `login`, `cart-empty`, `contact`, `search`, `not-found`, et une capture mobile FR (`home` ou `header`). Contrôler : textes en français (« Connexion », « Panier », « Contactez-nous », résultats de recherche), bonne page (pas un 404 à la place de `connexion`/`panier`/`nous-contacter`/`recherche`, pas l'accueil à la place du panier), sélecteur de langue présent dans le header.

Puis `rm -rf "$WORK"`.

- [ ] **Step 6: Contingences (seulement si l'une des vérifications échoue)**

Utiliser superpowers-extended-cc:systematic-debugging ; corriger **dans cette même PR**, un commit par correction, chemins explicites, trailer habituel.

1. **`/en/` ou `/fr/` ≠ 200 sur 1.7 ou 8.2** (étape *Check both languages answer* rouge). Diagnostiquer avec les logs du job (`docker compose logs` est déjà imprimé si la boutique ne monte pas ; sinon ajouter temporairement un `curl -sI` des deux URL). Pistes : langue non associée au shop de ce port, cache Smarty / routes non vidé, réécriture désactivée. Adapter `.github/visual/install-french.sh` (en gardant le comportement 9.2 intact) ou, si la boutique est correcte mais la sonde inadaptée, la vérification du workflow. Re-pousser et revenir à l'étape 3.
2. **Pages FR en 404 (réécritures non traduites par le pack 1.7 / 8)** alors que `/fr/` répond 200 : les références FR seraient des 404. Corriger l'installation (traduire les `meta` du FR) dans `install-french.sh`, **et** étendre l'étape *Check both languages answer* pour sonder aussi `/fr/connexion`, `/fr/panier?action=show`, `/fr/nous-contacter`, `/fr/recherche?s=test` (200 attendu) afin qu'une régression future soit fatale. Mettre l'en-tête du workflow à jour en conséquence.
3. **Un job vert mais une capture fausse** (ex. `cart-empty` = accueil) : c'est un défaut de suite ; corriger la suite et son test unitaire (Tâche 1), pas le workflow.
4. **Échec intermittent** sur une seule ligne : `gh run rerun "$RUN" --failed`, noter le résultat dans la PR ; ne pas masquer (pas de `continue-on-error`).

Après toute correction : les références de ce run PR ne sont jamais sauvegardées (PR), donc rien à purger.

- [ ] **Step 7: Compléter la PR et s'arrêter**

Ajouter à la description de la PR (`gh pr edit <n> --body-file …`, en conservant la ligne `🤖 Generated with …` à la fin) : l'id du run vérifié, les lignes de log relevées à l'étape 4, ce qui a été vu sur les captures FR 1.7 / 8.2, et toute contingence appliquée.

**Ne pas merger.** Rendre la main à l'utilisateur avec l'URL de la PR : le merge dans `dev` puis la release `dev` → `main` demandent son accord (`main` est protégée et exige les checks).

---

### Task 4: Après la release (sur accord de l'utilisateur) — références v3 sur `main`, stabilité, protection

**Goal:** Les références `v3` des 4 lignes sont sauvegardées par le push sur `main`, 2 ou 3 runs manuels comparent sans écart, et l'utilisateur reçoit la commande qui ajoute les deux nouveaux checks à la protection de `main`.

**Files:** aucun.

**Acceptance Criteria:**
- [ ] Le run `push` de `main` qui suit la release est vert sur les 4 lignes et a créé 4 entrées de cache `visual-refs-v3-<ps>-<theme>-<run_id>-<attempt>`.
- [ ] 2 ou 3 runs `workflow_dispatch` sur `main` : 4 jobs verts chacun, logs `references restored from visual-refs-v3-<ps>-<theme>-…`, et 32 images dans `visual-output/*/screens/diff/` de chaque artefact (preuve que tout a été comparé).
- [ ] La commande de mise à jour de la protection (13 checks) est donnée à l'utilisateur, pas exécutée sans son accord.

**Verify:**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual && gh cache list --key visual-refs-v3- --limit 20
```

Attendu : au moins 4 entrées, une par préfixe `visual-refs-v3-1.7.8.11-classic-`, `visual-refs-v3-8.2.8-classic-`, `visual-refs-v3-9.2.0-classic-`, `visual-refs-v3-9.2.0-hummingbird-`.

**Steps:**

- [ ] **Step 1: Attendre l'accord et la release**

Ne commencer qu'une fois la PR mergée dans `dev` **et** la release `dev` → `main` mergée par l'utilisateur (titre habituel : `Release: Visual sur PrestaShop 1.7.8 et 8.2 (#<n>)`).

- [ ] **Step 2: Run `push` sur `main`**

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
PUSH_RUN=$(gh run list --workflow visual.yml --branch main --event push --limit 1 --json databaseId -q '.[0].databaseId')
gh run watch "$PUSH_RUN" --exit-status
gh run view "$PUSH_RUN" --log | grep -E 'no cached references|references restored|Save visual references' | head -20
gh cache list --key visual-refs-v3- --limit 20
```

Attendu : 4 jobs verts, `no cached references` sur les 4 (aucune référence `v3` sur `main` avant ce run : les caches de la PR ne sont pas visibles depuis `main`), puis 4 caches `visual-refs-v3-…`.

- [ ] **Step 3: 2 ou 3 runs manuels qui comparent**

Pour chaque run (l'un après l'autre) :

```bash
cd /Users/jonathan/Documents/GitHub/PrestaFlow/php-library-visual
gh workflow run visual.yml --ref main
sleep 5   # le temps que le run apparaisse ; sinon relancer la commande suivante
RUN=$(gh run list --workflow visual.yml --branch main --event workflow_dispatch --limit 1 --json databaseId -q '.[0].databaseId')
gh run watch "$RUN" --exit-status
gh run view "$RUN" --log | grep -E 'references restored from visual-refs-v3-'
WORK=$(mktemp -d -t pf-visual-dispatch)
gh run download "$RUN" -D "$WORK"
for a in "$WORK"/visual-*; do echo "$(basename "$a"): $(find "$a" -path '*screens/diff/*' -name '*.png' | wc -l | tr -d ' ') diffs"; done
rm -rf "$WORK"
```

Attendu : sortie 0 ; 4 lignes `references restored from visual-refs-v3-<ps>-<theme>-…` (une par job, chacune avec **sa** version et **son** thème) ; `32 diffs` pour chacun des 4 artefacts (`visual-1.7.8.11-classic`, `visual-8.2.8-classic`, `visual-9.2.0-classic`, `visual-9.2.0-hummingbird`).

Si un run est rouge : télécharger son artefact, ouvrir les diffs du checkpoint en écart, et le signaler à l'utilisateur avec les images (instabilité à traiter avant de rendre les checks requis). Ne pas mettre à jour les références sans son accord.

- [ ] **Step 4: Donner à l'utilisateur la commande de protection de `main`**

Relire d'abord l'état courant (le `PUT` remplace **toute** la protection) :

```bash
gh api repos/PrestaFlow/php-library/branches/main/protection
```

Au 2026-10-01 : `strict: false`, 11 checks (app_id 15368), `enforce_admins: false`, pas de revue de PR requise, pas de restrictions, tous les autres réglages à `false`. Si cet état a changé, adapter le corps ci-dessous avant de le donner.

Commande à transmettre à l'utilisateur (à **ne pas** exécuter soi-même) — les 11 checks existants + les 2 nouveaux, 13 au total :

```bash
gh api -X PUT repos/PrestaFlow/php-library/branches/main/protection --input - <<'JSON'
{
  "required_status_checks": {
    "strict": false,
    "checks": [
      { "context": "PHPUnit (PHP 8.1)", "app_id": 15368 },
      { "context": "PHPUnit (PHP 8.2)", "app_id": 15368 },
      { "context": "PHPUnit (PHP 8.3)", "app_id": 15368 },
      { "context": "PHPUnit (PHP 8.4)", "app_id": 15368 },
      { "context": "PHPUnit (PHP 8.5)", "app_id": 15368 },
      { "context": "Smoke (PrestaShop 1.7.8.11, classic)", "app_id": 15368 },
      { "context": "Smoke (PrestaShop 8.2.8, classic)", "app_id": 15368 },
      { "context": "Smoke (PrestaShop 9.2.0, classic)", "app_id": 15368 },
      { "context": "Smoke (PrestaShop 9.2.0, hummingbird)", "app_id": 15368 },
      { "context": "Visual (PrestaShop 1.7.8.11, classic)", "app_id": 15368 },
      { "context": "Visual (PrestaShop 8.2.8, classic)", "app_id": 15368 },
      { "context": "Visual (PrestaShop 9.2.0, classic)", "app_id": 15368 },
      { "context": "Visual (PrestaShop 9.2.0, hummingbird)", "app_id": 15368 }
    ]
  },
  "enforce_admins": false,
  "required_pull_request_reviews": null,
  "restrictions": null,
  "required_linear_history": false,
  "allow_force_pushes": false,
  "allow_deletions": false,
  "block_creations": false,
  "required_conversation_resolution": false,
  "lock_branch": false,
  "allow_fork_syncing": false
}
JSON
```

Contrôle après exécution par l'utilisateur :

```bash
gh api repos/PrestaFlow/php-library/branches/main/protection --jq '.required_status_checks.contexts | length, .[]'
```

Attendu : `13`, puis les 13 noms ci-dessus.

Alternative plus étroite à proposer (ne touche que la liste des checks) : `gh api -X POST repos/PrestaFlow/php-library/branches/main/protection/required_status_checks/contexts -f 'contexts[]=Visual (PrestaShop 1.7.8.11, classic)' -f 'contexts[]=Visual (PrestaShop 8.2.8, classic)'`.

- [ ] **Step 5: Rapport final à l'utilisateur**

Ids des runs (push + dispatch), nombre de diffs par artefact, état des caches `v3`, et la commande de protection. Aucun commit dans cette tâche.

---

## Auto-revue contre la spec

| Spec | Où |
|---|---|
| §1 `cart-empty` → `cart?action=show` / `panier?action=show`, reste inchangé | Tâche 1, étapes 4-5 |
| §1 Hummingbird aligné par cohérence | Tâche 1, étape 5 |
| §2 matrice 4 lignes avec `ps` | Tâche 2, étape 2 |
| §2 `matrix.ps` dans nom du job, `PRESTAFLOW_PS_VERSION`, artefact | Tâche 2, étapes 2 et 5 |
| §2 cache `v3` (restore + restore-keys + save sur push `main`) | Tâche 2, étapes 3 et 5 |
| §2 étapes inchangées | Tâche 2, critères + relecture du diff (étape 6) |
| §2 en-tête mis à jour | Tâche 2, étape 1 |
| §3 script inchangé, adapté dans la même PR si `/en/` échoue | Tâche 3, étape 6 (contingence 1) |
| §4.1 local 1.7, EN sans préfixe, desktop + mobile ×2, sorties supprimées | Tâche 1, étapes 6 et 8 |
| §4.2 CI : 4 jobs verts, FR installé, `/en/` `/fr/` 200, captures FR 1.7 / 8.2 | Tâche 3, étapes 3-5 |
| §4.3 push sauvegarde `v3`, 2-3 `workflow_dispatch`, diffs dans l'artefact | Tâche 4, étapes 2-3 |
| §5 deux nouveaux checks, noms 9.2 inchangés, commande `gh api` | Tâche 2 (critère noms), Tâche 4 étape 4 |
| Livraison : PR vers `dev` puis release, rien dans l'app | Tâches 3-4 |
| Hors périmètre (Hummingbird 8.x, LCDN, back-office) | non traité |
