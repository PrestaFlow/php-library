# Version de PrestaShop portée par chaque objet : design

Date : 2026-10-02
Statut : validé en conversation, en attente de relecture

## Problème

La version de PrestaShop (majeure, mineure, patch) vit dans un attribut
**statique** du trait `Traits\Version` (`public static $versions`).

- **Partage selon la version de PHP.** Un statique déclaré dans un trait
  appartient à chaque classe qui fait `use` du trait.
  - `CommonPage` l'obtient via `Translations` ; `BackOfficePage` et les pages
    v7/v8/v9 partagent donc le statique de `CommonPage`.
  - `FrontOfficePage` refait `use Translations` : avant PHP 8.3 il partage le
    statique de `CommonPage`, à partir de 8.3 il a le sien.
  - La lib déclare `php ^8.1` : le comportement diffère entre la CI (8.1 à 8.5)
    et un poste en 8.4.
- **Argument ignoré.** Le constructeur d'une page reçoit `patchVersion` mais
  ne l'utilise jamais. `BackOfficePage` et `FrontOfficePage` calculent leurs
  sélecteurs et messages (`getSelectors()`, `getMessages()` →
  `getPageName()` → `getMajorVersion(namespace: true)`) **avant**
  `parent::__construct`, avec la version en cache, sinon `PS_VERSION` des
  globals, sinon `'8'`.
- **Repli silencieux mis en cache.** Ce `'8'` est mémorisé dans le statique et
  l'emporte ensuite sur la vraie version des pages suivantes. Constaté le
  2026-10-02 : un test construisant une page sans version faisait lire des
  sélecteurs v8 à une page v9 suivante, en PHP 8.1 et 8.2 seulement.
- **Bugs voisins.**
  - `exctractVersions()` lève `InvalidVersionException` sans l'importer :
    on obtient « classe introuvable » au lieu du message prévu.
  - `Scenario` fait `setVersions($globals['PATCH_VERSION'] ?? …)` : une chaîne
    peut remplacer le tableau des versions.

## Décisions

| Sujet | Décision |
|---|---|
| Où vit la version | Sur chaque objet (page, suite, scénario). Plus de statique. |
| Page sans version | `InvalidVersionException` explicite, plus de repli sur `'8'`. |
| Statique `$versions` | Supprimé. |
| Formats acceptés | Inchangés (le contrat avec l'app, qui refuse `8.1.10`, ne bouge pas). |
| API publique | Noms et signatures des getters/setters conservés (l'app les liste par leur nom). |

## 1. Trait `Version`

- `public static $versions` est remplacé par une propriété d'instance
  `protected array $versions = ['patchVersion' => null, 'minorVersion' => null, 'majorVersion' => null]`.
  Toutes les lectures et écritures passent par `$this->versions`.
- `getVersions()` / `setVersions(array $versions)` : `setVersions` est typé
  `array` et ne garde que les trois clés connues.
- `getMajorVersion()` :
  - renvoie la majeure de l'objet si elle est connue ;
  - sinon, si `globals['PS_VERSION']` est présent, l'analyse
    (`exctractVersions`) et renvoie la majeure ;
  - sinon lève `InvalidVersionException` (« version PrestaShop inconnue »).
  - Le repli sur `'8'` disparaît.
- `exctractVersions()` importe `PrestaFlow\Library\Exceptions\InvalidVersionException`.
  Formats acceptés inchangés.
- `resolveVersion()` (suites) garde son ordre de priorité et son défaut
  `'8.1.0'` : c'est le seul endroit avec un défaut.

## 2. Pages

- Nouvelle méthode `CommonPage::initVersion(string $patchVersion, array $globals): void` :
  - `$patchVersion` non vide → `exctractVersions($patchVersion)` ;
  - sinon `globals['PS_VERSION']` non vide → `exctractVersions(...)` ;
  - sinon `InvalidVersionException`.
- Appelée **en premier** dans `CommonPage::__construct`, `BackOfficePage::__construct`
  et `FrontOfficePage::__construct`, avant `getSelectors()` / `getMessages()`.
- `ImportPage::importPage()` continue de passer `patchVersion` et les globals ;
  la recopie des versions après construction est supprimée : la page a pris
  sa version dans son constructeur, et `initTranslations()` la recalcule déjà
  à partir du même patch.

## 3. Scénarios

- `Scenario` reprend `$testSuite->getVersions()` (un tableau).
- Si `globals['PATCH_VERSION']` est présent, il est analysé par
  `exctractVersions()` au lieu d'être passé tel quel à `setVersions()`.

## 4. Nettoyage

- `BackOfficeGoToMenuTest` : suppression de la sauvegarde / restauration de
  `CommonPage::$versions` (setUp / tearDown).
- `CommonPage::visualCheckpoint()` : la majeure est lue par
  `getMajorVersion()`, sans le contournement « globals d'abord ».
- Tests qui lisent ou écrivent le statique (`VersionOverrideTest`,
  `TranslationsCatalogTest`, `BackOfficeGoToMenuTest`) : adaptés aux
  accesseurs d'instance.
- Pages factices de tests construites sans version (ex. `makePage` des
  tests `VisualCheckpoint*`) : elles reçoivent un `PS_VERSION` ou un
  `patchVersion`.

## Tests

- Une page `BackOfficePage` 8.1 puis une page `FrontOfficePage` 9.0
  construites l'une après l'autre : chacune garde sa majeure et ses
  sélecteurs (et l'inverse).
- Une page construite avec `patchVersion: '1.7.8.11'` et sans `PS_VERSION`
  a la majeure `1.7`.
- Une page sans aucune version lève `InvalidVersionException`, avec son
  message (preuve que la classe est bien importée).
- `Scenario` avec `PATCH_VERSION` garde un tableau de versions cohérent.
- `resolveVersion()` des suites : comportement inchangé (tests existants).
- Suite unitaire complète verte sous **PHP 8.1, 8.2, 8.3 et 8.4**.
- CI de la PR : 13 checks verts (PHPUnit, Smoke, Visual).

## Livraison

- PR lib vers `dev`, puis release vers `main`.
- Puis mise à jour de la lib dans l'app (`composer.lock` seul) ; tests de
  l'app verts.

## Hors périmètre

- Élargir les formats de version acceptés (ex. `8.1.10`).
- Revoir `resolveVersion()` et son défaut `'8.1.0'`.
