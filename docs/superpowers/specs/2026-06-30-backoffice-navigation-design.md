# Spec Phase 3 (premier lot) — Fondation de navigation BackOffice + pages témoins

Date : 2026-06-30
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

Le BackOffice de PrestaFlow est squelettique : seules `Login`, `Dashboard` et
`Customer` existent (Customer = 2 sélecteurs, sans suite). La cause structurelle :
la navigation BO se fait par URL (`BO_URL + $url`) et **ne gère pas les tokens**
de sécurité admin, alors qu'atteindre un contrôleur (`AdminProducts`,
`AdminOrders`…) en direct exige `index.php?controller=Admin…&token=…` avec un
token dynamique.

**Solution retenue** : naviguer **via le menu admin**. Après login, le lien du
menu latéral porte déjà le token de session — un simple clic y navigue, sans
calcul de token. Le code possède déjà les helpers de clic (`click`, `leftClick`,
`waitForPageReload`, `isVisible`).

Ce premier lot pose la **fondation réutilisable** (un helper de navigation menu)
et l'illustre sur **2 pages** de sections distinctes (`Products` = Catalogue,
`Orders` = Commandes), pour prouver la généralité. Les contrôleurs suivants
seront des specs de suivi réutilisant le helper.

## Contrainte de vérification

Une page BO et sa suite ne se valident **réellement** que contre une PrestaShop
qui tourne (login → naviguer → asserter). Cet environnement n'en a pas de
joignable. On vérifie donc la **structure** sans navigateur (la page résout pour
v7/v8/v9, déclare ses sélecteurs de menu) ; le **comportement** (le clic navigue
bien) est un **check manuel documenté**. Les sélecteurs sont des **conventions
PrestaShop** à valider en live (esprit `@unverified` de la factorisation 1B).

## Objectif

Permettre d'écrire une suite BO ainsi :
```php
$this->importPage('BackOffice\Products');
$productsPage->goTo();                 // navigue via le menu (token de session)
Expect::that($productsPage->getPageTitle())->contains($productsPage->pageTitle());
```

### Hors périmètre
Les autres contrôleurs admin (specs de suivi) ; la navigation par URL+token
(écartée) ; toute manipulation de données (créer/éditer un produit, filtrer une
liste).

## Architecture

### Composant 1 — Helper de navigation menu (`BackOfficePage`)

Ajouts à `src/Pages/BackOfficePage.php` :

```php
    public string $menuSelector = '';
    public string $parentMenuSelector = '';

    public function goToSubMenu(string $parentSelector, string $linkSelector): void
    {
        if ($parentSelector !== '' && $this->isVisible($parentSelector) !== false) {
            $this->leftClick($parentSelector);
        }

        $this->leftClick($linkSelector);
        $this->waitForPageReload();
    }
```

- Les propriétés `$menuSelector`/`$parentMenuSelector` sont déclarées sur la base
  (obligatoire en PHP 8.4 : pas de propriété dynamique). Chaque page concrète
  les redéfinit.
- `goToSubMenu` : ouvre la section parente si présente et visible, clique le
  sous-lien, attend le rechargement. Responsabilité unique, réutilise les helpers
  existants. Reçoit des **sélecteurs CSS bruts** (les pages passent leurs
  propriétés).

### Composant 2 — Pages témoins (pattern Common + stubs de 1B)

Pour `Products` et `Orders` : une page canonique sous
`src/Pages/Common/BackOffice/<Name>/Page.php` + trois stubs purs
`src/Pages/v{7,8,9}/BackOffice/<Name>/Page.php` (`class Page extends
Common\…\<Name>\Page {}`), exactement comme les pages migrées en 1B.

`Common/BackOffice/Products/Page.php` :
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Products;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Products';
    public string $menuSelector = '#subtab-AdminProducts';
    public string $parentMenuSelector = '#subtab-AdminCatalog';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newProductButton' => '#page-header-desc-configuration-add',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
```

`Common/BackOffice/Orders/Page.php` (même forme) :
```php
    public string $pageTitle = 'Orders';
    public string $menuSelector = '#subtab-AdminOrders';
    public string $parentMenuSelector = '#subtab-AdminParentOrders';
    // defineSelectors: 'pageHeading' => '.page-title', 'newOrderButton' => '#page-header-desc-order-new_order'
    // goTo(): identique
```

Sélecteurs de menu = conventions PS 1.7+ (`#subtab-AdminProducts` /
`#subtab-AdminCatalog`, `#subtab-AdminOrders` / `#subtab-AdminParentOrders`), à
valider en live. Si le menu diffère entre v7/v8/v9, on override la valeur dans le
stub de la version concernée (le pattern stub/override de 1B le permet sans
changement d'architecture).

### Composant 3 — Suites de test (browser, doc exécutable / check manuel)

`src/Tests/Suites/BackOffice/Products.php` et `.../Orders.php` :
login (via `BackOffice\Login`), puis `goTo()`, puis asserter que le titre de page
contient le `pageTitle()` déclaré. Non exécutées en CI unitaire (navigateur
requis) — elles servent de documentation exécutable et de procédure de check
manuel.

### Composant 4 — Vérification structurelle (sans navigateur)

`tests/Unit/Pages/BackOfficeNavigationTest.php` :
- Les 2 pages **résolvent** pour v7/v8/v9 (`class_exists`).
- Instanciées (browser-free, comme prouvé en 1B/1C), elles exposent un
  `menuSelector` et un `parentMenuSelector` **non vides** et un `pageTitle`
  non vide.
- `Products` → `menuSelector === '#subtab-AdminProducts'` ; `Orders` →
  `'#subtab-AdminOrders'` (verrouille les conventions).

Le clic réel (`goToSubMenu`) n'est pas unit-testable (navigateur) → `php -l` +
revue de code + check manuel.

## Vérification

- `composer test-unit` au vert (nouveau test structurel + tous les existants).
- `php -l` sur `BackOfficePage.php` et les nouvelles pages.
- **Check manuel** (boutique requise) : une suite BO fait login → `goTo()` →
  atterrit sur la bonne page (titre attendu). Procédure dans les suites du
  Composant 3.

## Critères d'acceptation

- [ ] `BackOfficePage::goToSubMenu()` existe et clique parent (si visible) puis
      lien, avec attente de rechargement.
- [ ] `$menuSelector`/`$parentMenuSelector` déclarés sur `BackOfficePage`
      (base) — aucune propriété dynamique.
- [ ] `BackOffice\Products` et `BackOffice\Orders` résolvent pour v7/v8/v9 et
      déclarent leurs sélecteurs de menu + `goTo()`.
- [ ] Suites `BackOffice/Products` et `BackOffice/Orders` présentes (login →
      goTo → assert titre).
- [ ] `composer test-unit` vert ; `php -l` propre sur les fichiers touchés.
- [ ] Rien de cassé sur les pages/suites existantes.

## Fichiers touchés

- Modifiés : `src/Pages/BackOfficePage.php` (props + `goToSubMenu`).
- Créés : `src/Pages/Common/BackOffice/Products/Page.php`,
  `src/Pages/Common/BackOffice/Orders/Page.php`,
  `src/Pages/v{7,8,9}/BackOffice/{Products,Orders}/Page.php` (6 stubs),
  `src/Tests/Suites/BackOffice/Products.php`,
  `src/Tests/Suites/BackOffice/Orders.php`,
  `tests/Unit/Pages/BackOfficeNavigationTest.php`.
- Inchangés : `importPage`, les Resolvers, les pages existantes.
