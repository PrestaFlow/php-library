# Spec 1C — Fiabilisation du screenshot-à-l'échec

Date : 2026-06-30
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

La capture d'écran à l'échec d'un test a été ajoutée en amont (commits
`Add clickable screenshot links…` et la capture dans
`Expect::getExceptionConstructor`). Aujourd'hui : à la première assertion
échouée, un screenshot full-page est pris via `$page->screenshot(...)` et sauvé
dans `screens/errors/<fichier>.png` ; `Expect::$latestError` porte le nom de
fichier ; `TestsSuite::attachScreen()` le pose dans `$test['screen']` et
`results.json` ; `Output::expects()` affiche un lien cliquable « Open
screenshot ».

Cette base est correcte mais fragile. Audit du code actuel :

1. **Bug — le dossier `prestaflow/screens/errors/` n'est jamais créé.**
   `ExecuteSuite::handleDir()` ne crée que `prestaflow/` (pour `results.json`).
   En standalone (sans `storage_path()`), `saveToFile('./prestaflow/screens/errors/…')`
   lève `FilesystemException`, attrapée en `$latestError = null` → **le
   screenshot est perdu silencieusement**. La fonctionnalité ne marche pas hors
   contexte Laravel.
2. **Bug — lien cassé.** `Output` calcule le chemin via
   `realpath('./prestaflow/screens/errors')`, qui renvoie `false` si le dossier
   n'existe pas → lien `file:///<fichier>.png`. La logique de chemin est de plus
   **dupliquée** (et divergente) entre `Expect.php` et `Output.php`.
3. **`sleep(3)` en dur** avant chaque capture → chaque assertion rouge attend
   3 s, sans moyen de régler ou désactiver.
4. **Exceptions avalées.** `catch (...) { $latestError = null; }` ne laisse
   aucune trace : un screenshot manquant est indiscernable d'un échec de capture.
5. **Pas d'intégration CI.** Le rapport JUnit (spec 1A) ne référence pas le
   screenshot. Les reporters CI (GitHub Actions, GitLab, Jenkins) ne peuvent pas
   relier l'artefact à l'échec.

## Objectif

Fiabiliser la capture existante et la rendre **exploitable en CI** (GitHub
Actions en priorité, GitLab/Jenkins aussi) : dossier garanti, lien correct,
latence maîtrisée, échecs de capture visibles, et screenshot référencé dans le
rapport JUnit.

### Hors périmètre
La capture elle-même (déjà codée), la comparaison visuelle de référence
(`FrontOfficePage::compare`), le rendu hyperlien terminal (déjà fonctionnel).

## Décisions

- **Latence configurable** : `sleep(Screenshots::captureDelay())` où
  `captureDelay()` lit `PRESTAFLOW_SCREENSHOT_DELAY` (secondes), **défaut 3**
  (préserve le comportement actuel), `0` désactive l'attente.
- **Référence JUnit portable** : le chemin du screenshot apparaît **à deux
  endroits** pour couvrir tous les CI —
  - dans le **texte du `<failure>`** (les reporters GitHub Actions affichent ce
    texte) ;
  - dans un **`<system-out>`** au format `[[ATTACHMENT|<chemin relatif>]]`
    (convention lue par GitLab/Jenkins).
- **Chemin relatif stable** dans le rapport :
  `prestaflow/screens/errors/<fichier>` (pratique à uploader via
  `actions/upload-artifact`).
- **Source de vérité unique** du chemin : un helper `Screenshots`, utilisé par
  la capture (Expect), le lien (Output) et le rapport (JUnitReport).

## Architecture

### Composant 1 — `Screenshots` (nouveau, pur/filesystem)
Fichier : `src/Utils/Screenshots.php` — namespace `PrestaFlow\Library\Utils`.
Responsabilité unique : résoudre le dossier d'erreurs (et le créer), le chemin
d'un screenshot, et la latence de capture. Centralise la logique aujourd'hui
dupliquée. Aucun navigateur requis → testable unitairement.

```php
final class Screenshots
{
    // storage_path().'/screens/errors' si la fonction existe, sinon
    // getcwd().'/prestaflow/screens/errors'. mkdir récursif (0777) si $create et absent.
    public static function errorsDir(bool $create = false): string;

    // errorsDir($create) . '/' . $filename
    public static function errorPath(string $filename, bool $create = false): string;

    // Chemin relatif portable pour les rapports : 'prestaflow/screens/errors/<filename>'.
    // Vise le layout standalone (= celui des CI, qui ne tournent pas sous Laravel),
    // indépendamment de storage_path().
    public static function relativeErrorPath(string $filename): string;

    // (int) ($_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] ?? 3) ; borné à >= 0
    public static function captureDelay(): int;
}
```

### Composant 2 — Durcir la capture (`Expect::getExceptionConstructor`)
- `sleep(3)` → `sleep(Screenshots::captureDelay())`.
- `saveToFile(...)` → `saveToFile(Screenshots::errorPath($fileName, create: true))`
  (le dossier est créé avant écriture — corrige le bug #1, supprime la
  duplication de chemin). Vaut pour les deux branches actuelles (storage_path et
  fallback), désormais unifiées dans le helper.
- Dans les `catch`, en plus de `$latestError = null`, poser
  `Expect::$latestScreenshotError = $e->getMessage()` (nouvelle propriété
  statique, réinitialisée à `null` au début de la tentative).

### Composant 3 — Corriger le lien (`Output::expects`)
Remplacer le bloc qui calcule `$screenPath` (avec `realpath`) par
`$screenPath = Screenshots::errorPath($test['screen']);`. Le reste (affichage
debug + lien `<href=file://…>` si terminal compatible) est inchangé. Corrige le
bug #2 et aligne le chemin sur celui de la capture.

### Composant 4 — Screenshot dans le rapport JUnit (`JUnitReport::buildCase`)
Quand un test est en état `fail` et que `$test['screen']` est une chaîne
non vide :
- Append au **message** du `<failure>` : `"\nScreenshot: <relativeErrorPath>"`.
- Ajouter un enfant `<system-out>` au `<testcase>` contenant
  `[[ATTACHMENT|<relativeErrorPath>]]`.
`relativeErrorPath = Screenshots::relativeErrorPath($test['screen'])`. Pas de
`screen` → aucun ajout. `JUnitReport` reste pur (construction de chaîne via
`DOMDocument`, échappement gratuit). `results(false)` fournit déjà `screen` par
test.

### Composant 5 — Diagnostic d'échec de capture (`TestsSuite::attachScreen`)
Après avoir posé `$test['screen'] = Expect::$latestError`, si
`Expect::$latestScreenshotError` est non vide, ajouter une ligne au tableau
`$test['debug']` : `"Screenshot capture failed: <message>"`. Ainsi un échec de
capture est visible dans la sortie (le bloc `debug` est déjà rendu par
`Output::expects`) au lieu de disparaître. Réinitialiser
`Expect::$latestScreenshotError` après lecture.

## Vérification

### Tests unitaires (sans navigateur)
`tests/Unit/Utils/ScreenshotsTest.php` :
- `errorsDir(create: true)` crée le dossier (tester dans un cwd temporaire) ;
  `errorsDir()` ne le crée pas.
- `errorPath()` concatène correctement.
- `relativeErrorPath('x.png')` === `'prestaflow/screens/errors/x.png'`.
- `captureDelay()` lit l'env (poser `$_ENV['PRESTAFLOW_SCREENSHOT_DELAY']`),
  défaut 3, jamais négatif.

`tests/Unit/Reports/JUnitReportTest.php` (étendre) :
- Un testcase `fail` avec `screen` produit `<system-out>` contenant
  `[[ATTACHMENT|prestaflow/screens/errors/<fichier>]]`, et le message du
  `<failure>` contient `Screenshot: prestaflow/screens/errors/<fichier>`.
- Un testcase `fail` **sans** `screen` ne produit ni `system-out` ni mention
  screenshot.
- XML toujours bien formé.

### Vérification d'intégration (manuelle, navigateur)
Sur une boutique joignable avec un test volontairement rouge : confirmer que
`prestaflow/screens/errors/` est créé, que le `.png` y est écrit, que le lien
terminal s'ouvre, et que `junit.xml` contient `<system-out>` + la mention dans
`<failure>`. À défaut de boutique : revue de code de `Expect` + les helpers
unit-testés.

## Critères d'acceptation

- [ ] En standalone, un échec crée `prestaflow/screens/errors/` et y écrit le
      `.png` (plus de perte silencieuse).
- [ ] Le lien terminal pointe vers le bon fichier (plus de `realpath` faux).
- [ ] La logique de chemin n'existe qu'à un endroit (`Screenshots`) — Expect,
      Output et JUnitReport l'utilisent.
- [ ] `PRESTAFLOW_SCREENSHOT_DELAY` règle la latence (défaut 3, `0` désactive).
- [ ] Un échec de capture produit une ligne `debug` visible (plus d'avalement
      muet).
- [ ] Le `junit.xml` d'un test rouge avec screenshot contient la référence dans
      `<failure>` ET un `<system-out>` `[[ATTACHMENT|…]]`.
- [ ] `composer test-unit` au vert (nouveaux tests + tous les existants).

## Fichiers touchés

- Créés : `src/Utils/Screenshots.php`, `tests/Unit/Utils/ScreenshotsTest.php`.
- Modifiés : `src/Expects/Expect.php` (capture : helper + delay + diagnostic),
  `src/Utils/Output.php` (lien via helper), `src/Reports/JUnitReport.php`
  (référence screenshot), `src/Tests/TestsSuite.php` (`attachScreen` diagnostic),
  `tests/Unit/Reports/JUnitReportTest.php` (étendu).
- Inchangés : la logique de capture `$page->screenshot(...)`, la comparaison
  visuelle, le code de détection terminal.
