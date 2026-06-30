# Spec 1B — Factorisation des pages v7/v8/v9 (Common + stubs)

Date : 2026-06-30
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

Les pages de PrestaFlow sont organisées par version PrestaShop sous
`src/Pages/v7`, `src/Pages/v8`, `src/Pages/v9` (où `v7` = PrestaShop 1.7). Chaque
page « feuille » (ex. `v9/FrontOffice/Category/Page.php`) ne contient que des
**données spécifiques** : `defineSelectors()`, `$url`, `$pageTitle`, et
occasionnellement une méthode dédiée. Toute la logique réelle vit déjà dans les
bases partagées `FrontOfficePage` / `BackOfficePage` / `CommonPage`.

Conséquence : chaque feuille existe en **3 exemplaires** (v7/v8/v9), et **26 des
28** pages FrontOffice sont **byte-identiques** entre versions (hors namespace).
Une modification de contenu doit aujourd'hui être répétée 3 fois — un commit
récent (`Add inequality expectations…`) a dû modifier `Product` une fois par
version.

### Mécanisme de résolution (à préserver)

`importPage($pageName)` (trait `src/Traits/ImportPage.php`) construit le nom de
classe **selon la version détectée** :

```php
$pageClass = '\PrestaFlow\Library\Pages\v'.$majorVersion.'\'.$pageName.'\Page';
$pageInstance = new $pageClass(...);   // instanciation directe, sans fallback
```

**Contrainte forte : la classe `Pages\vN\<name>\Page` DOIT exister pour la
version courante**, sinon erreur fatale. Le présent refactor ne doit jamais
casser cette garantie.

## Objectif

Supprimer la triplication du **contenu** des pages tout en conservant un point
d'entrée `Pages\vN\…\Page` pour **chaque page et chaque version supportée** :

- Le contenu canonique de chaque page migre dans un arbre versionless
  `src/Pages/Common/`.
- Chaque `Pages\vN\…\Page` devient un **stub mince** qui étend la page Common,
  n'overridant que les différences réelles de cette version.
- `importPage()` reste **inchangé** : les stubs vN existent toujours, la
  résolution actuelle fonctionne telle quelle.

Résultat : une modification de contenu se fait **une seule fois** (dans Common),
les 3 stubs en héritent. La duplication de logique disparaît ; les points
d'entrée par version sont tous préservés.

### Décision : existence sur toutes les versions

Aujourd'hui certaines pages n'existent que pour une partie des versions (ex.
`BackOffice\Customer` = v9 uniquement ; `importPage('BackOffice\Customer')` sur
une 1.7 plante déjà). 1B crée des **stubs pour toutes les versions supportées**
(v7/v8/v9) de **chaque** page de l'union. Ainsi `importPage` ne plante plus
jamais sur une page manquante, quelle que soit la version.

**Risque assumé et tracé :** une page jusqu'ici spécifique à v9 (ex. Customer)
deviendra résolvable en 1.7/8 avec les **sélecteurs v9**, potentiellement faux
sur ces admins. Pour rendre l'échec clair plutôt que silencieux, chaque stub
ainsi « étendu à une version non validée » porte un commentaire de tête
`@unverified <version>` signalant que ses sélecteurs n'ont pas été validés sur
cette version (validation = effort séparé, hors 1B).

## Architecture cible

```
src/Pages/
  FrontOfficePage.php  BackOfficePage.php  CommonPage.php   (logique partagée — INCHANGÉS)

  Common/
    FrontOffice/
      Page.php                 → extends FrontOfficePage           (base canonique FO)
      Category/Page.php        → extends Common\FrontOffice\Page    (contenu canonique)
      Product/Page.php  …                                          (toutes les feuilles FO)
    BackOffice/
      Page.php                 → extends BackOfficePage
      Login/Page.php  Dashboard/Page.php  Customer/Page.php

  v7/FrontOffice/
      Page.php                 → extends Common\FrontOffice\Page    (stub de base)
      Category/Page.php        → extends Common\FrontOffice\Category\Page {}   (stub pur)
      … (un stub par page de l'union)
  v8/…  (stubs)
  v9/FrontOffice/
      PricesDrop/Page.php      → extends Common\…\PricesDrop\Page + override ($url='promotions')
      … (stubs purs ailleurs)
```

L'autoload PSR-4 existant (`PrestaFlow\Library\` → `src/`) couvre déjà
`PrestaFlow\Library\Pages\Common\…` → **aucun changement de `composer.json`**.

## Règle de migration (déterministe)

Soit U l'**union** de toutes les pages présentes dans v7 ∪ v8 ∪ v9. Pour chaque
page de U :

1. **Calculer la matrice des différences** entre les versions qui la possèdent
   (diff de `defineSelectors()`, `$url`, `$pageTitle`, méthodes).
2. Le contenu **canonique** = la valeur partagée par la **majorité** des versions
   (pour une page mono-version, sa seule définition). Il va dans `Common\…\Page`.
3. Chaque version supportée (v7/v8/v9) reçoit un **stub** :
   - valeur = canonique → stub pur : `class Page extends Common\…\Page {}` ;
   - valeur ≠ canonique → stub **override** : étend la page Common et ne
     redéfinit que le delta (ex. `$url`/`$pageTitle` dans le constructeur, ou un
     `defineSelectors()` fusionnant les sélecteurs spécifiques) ;
   - version qui ne possédait pas la page → stub pur + commentaire `@unverified`.
4. Les bases `vN\FrontOffice\Page` et `vN\BackOffice\Page` deviennent des stubs
   `extends Common\FrontOffice\Page` / `extends Common\BackOffice\Page` (BC).

### Deltas connus à ce stade

- `FrontOffice\PricesDrop` : v9 = `promotions` / `Promotions` ; v7/v8 =
  `prices-drop` / `Prices drop`. → Common = `prices-drop` (majorité), v9 override.
- `FrontOffice\Category` : différence **cosmétique** seulement (même URL
  `{index}-category`, propriété vs constructeur). → une seule définition Common,
  3 stubs purs.
- `BackOffice\Customer` : v9 uniquement. → Common = définition v9, stub v9 pur,
  stubs v7/v8 `@unverified`.

La matrice complète est calculée à l'implémentation (diff exhaustif v7/v8/v9),
pas devinée.

## Vérification (sans navigateur, PHPUnit)

Instancier une page est **browser-free** (`CommonPage::__construct` ne fait que
poser globals/locale/customs). Le refactor est donc entièrement testable sans
Chrome.

1. **Aucun point d'entrée perdu, existence totale :** pour **chaque** page de U
   et **chaque** version supportée, `class_exists('…\Pages\vN\<name>\Page')` est
   vrai — exactement ce que `importPage` instancie.
2. **Deltas préservés :** instancier chaque page avec des globals factices et
   asserter `url` / `pageTitle` par version (ex. v9 `PricesDrop` → `promotions`,
   v7 → `prices-drop`).
3. **Anti-régression de contenu :** pour les pages identiques entre versions,
   asserter que les 3 versions exposent les **mêmes** `selectors` (héritées de
   Common).
4. **Chargement global :** `composer dump-autoload` puis chargement de toutes les
   classes Page → zéro erreur fatale.

Un helper de test énumère les pages depuis l'arborescence `src/Pages/Common/`
pour piloter ces assertions sans liste codée en dur.

## Périmètre

- **Inclus :** FrontOffice (28 pages) + BackOffice (Login, Dashboard, Customer).
- **Exclus :** validation réelle des sélecteurs `@unverified` sur 1.7/8 (effort de
  parité séparé) ; toute modification de `importPage`, des bases partagées
  (`FrontOfficePage`/`BackOfficePage`/`CommonPage`), ou des Resolvers.

## Critères d'acceptation

- [ ] `src/Pages/Common/` contient la définition canonique de chaque page de U.
- [ ] Pour chaque page de U et chaque version v7/v8/v9, `Pages\vN\…\Page` existe
      (stub) et `class_exists` est vrai.
- [ ] Aucune page ne contient de `defineSelectors()`/`$url`/`$pageTitle` dupliqué :
      le contenu n'existe qu'une fois (dans Common ou dans l'override de delta).
- [ ] Les deltas connus sont préservés (test : v9 PricesDrop = `promotions`,
      v7/v8 = `prices-drop`).
- [ ] `importPage()` est inchangé ; `composer.json` est inchangé.
- [ ] Les bases partagées et les Resolvers sont inchangés.
- [ ] `composer test-unit` au vert (tests de factorisation + 9 tests 1A existants).

## Fichiers touchés

- Créés : `src/Pages/Common/FrontOffice/**`, `src/Pages/Common/BackOffice/**`
  (bases + ~31 feuilles canoniques) ; `tests/Unit/Pages/PageFactorizationTest.php`.
- Modifiés : tous les `src/Pages/v{7,8,9}/**/Page.php` → réduits à des stubs
  (purs ou override de delta).
- Inchangés : `composer.json`, `src/Traits/ImportPage.php`, `FrontOfficePage`,
  `BackOfficePage`, `CommonPage`, `src/Resolvers/**`.
