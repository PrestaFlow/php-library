# Spec 1A — Prêt pour la CI (code de sortie + rapport JUnit)

Date : 2026-06-30
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

PrestaFlow est un framework de tests PHP pour PrestaShop, piloté par Chrome
headless via `chrome-php`. Les tests sont des classes `TestsSuite` exécutées par
la commande Symfony Console `run` (`src/Command/ExecuteSuite.php`,
binaire `bin/prestaflow`).

Deux limites bloquent un usage en intégration continue (GitLab CI, GitHub
Actions) :

1. **`run` retourne toujours `Command::SUCCESS`.** Même avec des tests rouges, le
   code de sortie du processus est `0`. Une CI ne peut donc pas détecter un
   échec. Chaque suite expose pourtant ses compteurs via `getStats()['failures']`,
   mais la commande ne les lit jamais après exécution.
2. **Aucun rapport au format standard.** La seule sortie machine est un
   `results.json` maison (mode `--output=json`). Les CI savent ingérer le format
   **JUnit XML** nativement (onglet « Tests » de GitLab/GitHub) — pas le JSON
   maison.

Cette première itération (1A) lève ces deux limites, sans toucher à
l'architecture des ~28 pages existantes (zéro risque de régression fonctionnelle).

## Objectif

Permettre l'invocation CI canonique :

```bash
bin/prestaflow run --output=compact --junit=junit.xml
```

→ progression lisible dans le log du job **et** fichier `junit.xml` parsable par
la CI, **et** code de sortie `1` si au moins un test échoue.

### Hors périmètre (itérations suivantes)

- Capture de vrais screenshots à l'échec (spec 1C).
- Factorisation des pages v7/v8/v9 (spec 1B).
- Comptage des suites qui échouent à l'instanciation (`new $className()` en erreur)
  comme « erreurs » CI — comportement actuel conservé (la suite est ignorée).

## Décisions

- **`--junit` est un flag séparé, orthogonal à `--output`** (modèle PHPUnit
  `--log-junit`), et non une valeur de `--output`. Raison : le cas CI veut
  *à la fois* une sortie console lisible et un fichier machine. Coupler JUnit à
  `--output` forcerait à choisir l'un ou l'autre (comme le fait `--output=json`
  aujourd'hui, qui supprime le rendu lisible).
- **Deux classes pures, isolées et testables sans navigateur**, plutôt que de la
  logique inline dans la commande :
  - `TestRunSummary` : décision du code de sortie.
  - `JUnitReport` : sérialisation XML.
- **PHPUnit en dépendance dev** pour tester ces deux classes (réutilisable pour
  les phases suivantes).

## Architecture

```
ExecuteSuite (commande)
  ├─ pour chaque suite exécutée :
  │     $stats   = $suite->getStats()
  │     $results = $suite->results(false)
  │     $summary->add($stats)                 // TestRunSummary
  │     $report->addSuite($results)           // JUnitReport (si --junit)
  │
  ├─ si --junit : filePutContents($path, $report->render())
  └─ return $summary->hasFailures() ? Command::FAILURE : Command::SUCCESS
```

### Composant 1 — `TestRunSummary` (nouveau, pur)

Fichier : `src/Reports/TestRunSummary.php`
Namespace : `PrestaFlow\Library\Reports`

Responsabilité unique : agréger les compteurs de plusieurs suites et répondre à
« y a-t-il eu un échec ? ».

API :

```php
final class TestRunSummary
{
    public function add(array $stats): void;   // $suite->getStats()
    public function totalFailures(): int;
    public function hasFailures(): bool;        // totalFailures() > 0
}
```

- N'accumule que ce dont la décision a besoin (au minimum `failures`). Robuste si
  une clé manque (`$stats['failures'] ?? 0`).
- Aucune dépendance, aucun I/O.

### Composant 2 — `JUnitReport` (nouveau, pur)

Fichier : `src/Reports/JUnitReport.php`
Namespace : `PrestaFlow\Library\Reports`

Responsabilité unique : transformer des résultats de suites en chaîne XML JUnit.
Construit le document avec `DOMDocument` (échappement XML correct et gratuit).

API :

```php
final class JUnitReport
{
    public function addSuite(array $results): void;  // $suite->results(false)
    public function render(): string;                // XML complet
}
```

Entrée par suite (structure déjà produite par `TestsSuite::results(false)`) :
`['suite' => FQCN, 'title' => string, 'stats' => [...], 'tests' => [ ... ]]`,
chaque test ayant au moins `title`, `state`, `time` (ms), `expect`.

**Mapping :**

| PrestaFlow                         | JUnit                                                                 |
|------------------------------------|----------------------------------------------------------------------|
| racine                             | `<testsuites>`                                                        |
| une `TestsSuite`                   | `<testsuite name title tests failures skipped time>`                 |
| `it()` état `pass`                 | `<testcase name classname time/>`                                    |
| `it()` état `fail`                 | `<testcase>` + `<failure message="…">` (texte = `implode` de `expect['fail']`) |
| état `skip` / `skipped` / `todo`   | `<testcase>` + `<skipped/>`                                          |

Attributs `<testsuite>` :

- `name` = `title` (fallback : `suite`).
- `tests` = nombre de `<testcase>` émis (= total des tests de la suite).
- `failures` = `stats['failures']`.
- `skipped` = `stats['skips'] + stats['skippeds'] + stats['todos']`.
- `time` = `stats['time'] / 1000` (ms → s, 3 décimales).
- `errors` = `0` (1A ne distingue pas erreurs/échecs).

Attributs `<testcase>` :

- `name` = `title` du test.
- `classname` = `suite` (FQCN de la suite).
- `time` = `test['time'] / 1000`.

Détails :

- Échappement assuré par `DOMDocument` (titres, messages d'échec).
- Document encodé UTF-8, `formatOutput = true`.
- Une suite sans test → `<testsuite>` avec `tests="0"` (valide).

### Composant 3 — Câblage CLI (`ExecuteSuite`)

- Nouvelle option dans `configure()` :
  ```php
  ->addOption('junit', null, InputOption::VALUE_OPTIONAL, 'Write a JUnit XML report (default path: prestaflow/junit.xml)', false)
  ```
  Convention (identique à `--draft`) :
  - option absente → valeur `false` → **aucun** fichier généré (comportement actuel préservé).
  - `--junit` seul → valeur `null` → chemin par défaut `prestaflow/junit.xml`.
  - `--junit=chemin` → ce chemin.
- Instancier `TestRunSummary` (toujours) et `JUnitReport` (toujours ; n'est
  rendu/écrit que si `--junit` est demandé) avant la boucle.
- Dans la boucle, après `$suite->run(...)` : `$summary->add($suite->getStats())`
  et `$report->addSuite($suite->results(false))`.
- Après la boucle : si `--junit` demandé, écrire via `filePutContents()` (déjà
  présent) et confirmer le chemin avec `success()`.
- Remplacer les `return Command::SUCCESS;` finaux par
  `return $summary->hasFailures() ? Command::FAILURE : Command::SUCCESS;`.
  - Cas « dossier vide » / « aucune suite exécutée » → `hasFailures()` est `false`
    → code `0` (rien à échouer n'est pas un échec). Comportement conservé.
  - Une `Error` fatale non rattrapée continue de remonter (code ≠ 0, inchangé).

Le `--junit` fonctionne avec n'importe quelle valeur de `--output` (y compris
`json`), puisqu'il écrit un fichier indépendant du rendu console.

## Dépendances dev & tests

- Ajouter `require-dev` : `phpunit/phpunit: ^10` (compatible PHP 8.1+, comme
  Symfony 6).
- `phpunit.xml.dist` à la racine, suite `Unit` pointant sur `tests/`.
- `autoload-dev` PSR-4 : `"PrestaFlow\\Tests\\": "tests/"` (séparé de
  `src/Tests/`, qui contient les *suites PrestaFlow*, pas des tests PHPUnit).
- Script composer : `"test-unit": "phpunit"` (ne pas écraser le script `tests`
  existant qui lance les suites PrestaFlow).

### Tests unitaires (sans navigateur)

`tests/Unit/Reports/TestRunSummaryTest.php` :
- `add()` cumulatif sur plusieurs suites ; `hasFailures()` vrai dès `failures>0`,
  faux sinon ; robustesse aux clés manquantes.

`tests/Unit/Reports/JUnitReportTest.php` :
- XML bien formé (`simplexml_load_string` ne renvoie pas `false`).
- Un `<testsuite>` par suite ajoutée, attributs corrects (tests/failures/skipped/time).
- `<testcase>` fail → contient `<failure>` avec le message attendu.
- `<testcase>` skip/skipped/todo → contient `<skipped>`.
- Échappement : un titre contenant `<`, `&`, `"` produit un XML toujours valide.

### Vérification d'intégration (manuelle, documentée)

Avec une instance PrestaShop joignable (hors CI unitaire) :
```bash
bin/prestaflow run --junit=/tmp/junit.xml src/Tests/Suites/Fails ; echo "exit=$?"
```
Attendu : `exit=1`, et `/tmp/junit.xml` contient un `<failure>`. Sur un dossier
100 % vert : `exit=0`, aucun `<failure>`. Validation : `xmllint --noout`.

## Critères d'acceptation

- [ ] `run` retourne `1` quand au moins un test échoue, `0` sinon (dossier vide
      inclus → `0`).
- [ ] `--junit` (seul) écrit `prestaflow/junit.xml` ; `--junit=chemin` écrit au
      chemin donné ; option absente n'écrit aucun fichier.
- [ ] Le XML produit est un JUnit bien formé : `<testsuites>` > `<testsuite>` >
      `<testcase>`, avec `<failure>` sur les échecs et `<skipped>` sur
      skip/skipped/todo.
- [ ] `--junit` cohabite avec n'importe quel `--output` (la console reste rendue).
- [ ] `composer test-unit` (PHPUnit) passe au vert pour `TestRunSummary` et
      `JUnitReport`.
- [ ] Aucun changement de comportement des ~28 pages ni des suites existantes.

## Fichiers touchés

- Créés :
  - `src/Reports/TestRunSummary.php`
  - `src/Reports/JUnitReport.php`
  - `tests/Unit/Reports/TestRunSummaryTest.php`
  - `tests/Unit/Reports/JUnitReportTest.php`
  - `phpunit.xml.dist`
- Modifiés :
  - `src/Command/ExecuteSuite.php` (option `--junit`, agrégation, écriture, code de sortie)
  - `composer.json` (`require-dev`, `autoload-dev`, script `test-unit`)
