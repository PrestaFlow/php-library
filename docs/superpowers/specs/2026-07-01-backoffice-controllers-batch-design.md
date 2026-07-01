# Spec Phase 3 (lot 2) — Contrôleurs BackOffice : Categories, Customers, Modules, Carriers

Date : 2026-07-01
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

Le lot 1 de la Phase 3 a posé la fondation de navigation BackOffice
(`BackOfficePage::goToSubMenu()` + propriétés `$menuSelector`/
`$parentMenuSelector`) et deux pages témoins (`Products`, `Orders`). Le pattern
est établi : ajouter un contrôleur admin = une page canonique
`Common/BackOffice/<Nom>` + trois stubs de version + une suite, en réutilisant
`goToSubMenu`.

Ce lot applique le pattern à **quatre contrôleurs** supplémentaires, couvrant
quatre sections de menu distinctes.

## Contrainte de vérification (identique au lot 1)

Le comportement réel (login → clic menu → atterrissage) ne se valide que contre
une PrestaShop qui tourne, indisponible ici. On vérifie la **structure**
(résolution v7/v8/v9, sélecteurs de menu déclarés) sans navigateur ; les
sélecteurs `#subtab-Admin*` sont des **conventions PrestaShop à valider en live**
(esprit `@unverified` de 1B). En cas de divergence entre versions, override dans
le stub concerné.

## Objectif

Rendre importables et navigables `BackOffice\Categories`, `BackOffice\Customers`,
`BackOffice\Modules`, `BackOffice\Carriers`, chacune via son menu.

### Hors périmètre
Toute manipulation de données (créer/éditer/supprimer, filtres de liste) ; les
autres contrôleurs (lots suivants) ; la validation live des sélecteurs.

## Pages à ajouter

Chaque page est un `Common/BackOffice/<Nom>/Page.php` étendant
`Common\BackOffice\Page`, plus trois stubs purs
`v{7,8,9}/BackOffice/<Nom>/Page.php` (`class Page extends Common\…\<Nom>\Page {}`).

| Page | `menuSelector` | `parentMenuSelector` | `pageTitle` | `defineSelectors()` |
|------|----------------|----------------------|-------------|---------------------|
| `Categories` | `#subtab-AdminCategories` | `#subtab-AdminCatalog` | `Categories` | `pageHeading` → `.page-title`, `newCategoryButton` → `#page-header-desc-configuration-add` |
| `Customers` | `#subtab-AdminCustomers` | `#subtab-AdminParentCustomer` | `Customers` | `pageHeading` → `.page-title`, `newCustomerButton` → `#page-header-desc-configuration-add` |
| `Modules` | `#subtab-AdminModulesManage` | `#subtab-AdminParentModulesSf` | `Modules` | `pageHeading` → `.page-title` |
| `Carriers` | `#subtab-AdminCarriers` | `#subtab-AdminParentShipping` | `Carriers` | `pageHeading` → `.page-title`, `newCarrierButton` → `#page-header-desc-configuration-add` |

Chaque page définit aussi :
```php
    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
```

Notes :
- `Customers` (liste, pluriel) est **distinct** de la page `Customer` (édition,
  singulier) déjà présente → variables `$backOfficeCustomersPage` vs
  `$backOfficeCustomerPage`.
- Le `pageTitle` de `Modules` peut valoir « Module Manager » en réel ; il est
  utilisé avec `contains()`, donc à ajuster au check live si besoin.

## Suites de test (browser, doc exécutable / check manuel)

Une suite par page sous `src/Tests/Suites/BackOffice/<Nom>.php` : importe
`BackOffice\Login` + la page, login, `goTo()`, asserte que `getPageTitle()`
contient `pageTitle()`. Non exécutées en CI unitaire (navigateur requis).

## Vérification (sans navigateur)

Étendre `tests/Unit/Pages/BackOfficeNavigationTest.php` :
- Les 4 pages **résolvent** pour v7/v8/v9 (`class_exists`).
- Chaque page instanciée expose le `menuSelector`/`parentMenuSelector` attendu
  (valeurs exactes du tableau) et un `pageTitle` non vide, et a une méthode
  `goTo()`.

Le clic réel n'est pas unit-testable → `php -l` + revue + check manuel.

## Critères d'acceptation

- [ ] `BackOffice\{Categories,Customers,Modules,Carriers}` résolvent pour
      v7/v8/v9 et déclarent leurs sélecteurs de menu + `goTo()`.
- [ ] `Customers` (liste) coexiste avec `Customer` (édition) sans collision.
- [ ] Une suite par contrôleur (login → goTo → assert titre).
- [ ] `BackOfficeNavigationTest` couvre les 4 nouvelles pages ; `composer
      test-unit` vert.
- [ ] `php -l` propre sur tous les fichiers ; rien de cassé par ailleurs.

## Fichiers touchés

- Créés :
  - `src/Pages/Common/BackOffice/{Categories,Customers,Modules,Carriers}/Page.php`
  - `src/Pages/v{7,8,9}/BackOffice/{Categories,Customers,Modules,Carriers}/Page.php` (12 stubs)
  - `src/Tests/Suites/BackOffice/{Categories,Customers,Modules,Carriers}.php`
- Modifiés : `tests/Unit/Pages/BackOfficeNavigationTest.php` (couvre les 4).
- Inchangés : `BackOfficePage` (helper déjà en place), les pages/suites
  existantes, `importPage`, les Resolvers.
