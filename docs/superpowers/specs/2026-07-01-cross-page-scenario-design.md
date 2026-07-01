# Spec #2 — Scénario cross-page : créer un produit en BO → le vérifier en FO

Date : 2026-07-01
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

Les pages BO savent naviguer (Phase 3) et la page Products sait créer/lister/
supprimer (#3). Il manque un **scénario** qui compose plusieurs pages de bout en
bout — la valeur métier visée. L'infra existe : classe de base
`Scenario` (composée via `$testSuite->scenario($class, $params)`, ex.
`AddProductToCart`), et les helpers `store($key, $value)` / `retrieve($key)` de
`TestsSuite` (1.2.4) pour passer une valeur entre étapes.

Ce spec livre un premier scénario cross-page : **créer un produit dans le
BackOffice, puis vérifier qu'il est visible dans le FrontOffice**, en réutilisant
les pages existantes.

### Contrainte sémantique (importante)

Un produit créé avec seulement nom/prix/quantité est **désactivé par défaut** →
invisible en FO. Le scénario doit donc **activer** le produit après création,
puis le retrouver en FO. Le moyen le plus fiable de le retrouver : **capturer son
ID** (l'URL de la fiche devient `.../products/{id}/edit` après enregistrement) et
naviguer directement via `FrontOffice\Product::goToProduct($id)` — plutôt qu'une
recherche FO (aucune page Search n'existe).

## Contrainte de vérification (identique aux lots BO)

Sans boutique joignable, seule la **structure** est vérifiable sans navigateur
(le scénario/la suite/les ajouts existent et composent les bonnes pages). Le
**comportement** (create → enable → capture ID → FO) reste `@unverified` → check
live requis. Le scénario est **auto-nettoyant** (il supprime le produit qu'il crée).

## Objectif

Fournir un scénario réutilisable et paramétrable démontrant le flux cross-page,
et compléter la page BO Products avec les deux actions qu'il requiert.

### Hors périmètre
Autres scénarios ; page FO Search ; champs produit avancés ; overrides form v7/v8
(toujours différés).

## Architecture

### Composant 1 — Compléments BO Products (`Common/BackOffice/Products/Page.php`)
Deux méthodes + un sélecteur (`@unverified`, best-effort PS 9) :
- `enableProduct(): void` — clique le switch « en ligne » de la fiche produit.
  Nouveau sélecteur `productOnlineToggle` (best-effort `#product_header_active_1`).
- `getCreatedProductId(): int` — extrait l'ID depuis l'URL courante après save :
  ```php
  public function getCreatedProductId(): int
  {
      if (preg_match('#/products/(\d+)#', $this->getPage()->getCurrentUrl(), $m)) {
          return (int) $m[1];
      }
      return 0;
  }
  ```
  (`getPage()` et `getCurrentUrl()` existent déjà — CommonPage + chrome-php.)

### Composant 2 — Scénario `CreateProductAndVerify` (`src/Scenarios/`)
Classe étendant `Scenario`, sur le modèle d'`AddProductToCart`. Paramètres :
`productName`, `productPrice`, `productQuantity`. `steps($testSuite)` importe
`BackOffice\Login`, `BackOffice\Products`, `FrontOffice\Product`, puis enchaîne
via `$testSuite->it(...)` :
1. **login BO** : `goToPage('index')` + `login()`.
2. **create + enable** : `goTo()`, `createProduct(name, price, qty)`,
   `enableProduct()`, `$this->store('productId', $backOfficeProductsPage->getCreatedProductId())`.
3. **vérif FO** : `$frontOfficeProductPage->goToProduct((int) $this->retrieve('productId'))`,
   puis `Expect::that($frontOfficeProductPage->getTitle())->contains($this->getParam('productName'))`.
4. **cleanup BO** : `goTo()`, `filterByName(name)`, `deleteProduct(1)`.

(`$this` dans les closures = la `TestsSuite` — `store`/`retrieve`/`getParam` y
sont définis, comme dans `AddProductToCart`.)

### Composant 3 — Suite d'invocation (`src/Tests/Suites/Scenarios/CreateProductAndVerify.php`)
```php
class CreateProductAndVerify extends TestsSuite
{
    public function init()
    {
        $this->describe('Create a product in the BO and verify it in the FO')
             ->scenario(\PrestaFlow\Library\Scenarios\CreateProductAndVerify::class);
    }
}
```
(Même nom de classe que le scénario mais namespace distinct
`…Tests\Suites\Scenarios` vs `…Scenarios`.) Non exécutée en CI unitaire.

### Composant 4 — Vérification structurelle (sans navigateur)
`tests/Unit/Scenarios/CreateProductAndVerifyTest.php` :
- `Scenarios\CreateProductAndVerify` existe et étend `Scenarios\Scenario`.
- `Tests\Suites\Scenarios\CreateProductAndVerify` existe et étend `TestsSuite`.
- La page BO Products (instanciée browser-free en v9) expose `enableProduct` et
  `getCreatedProductId`, et déclare la clé `productOnlineToggle`.

Le comportement réel = check live (Composant 3).

## Vérification

- `composer test-unit` vert (nouveau test + existants).
- `php -l` propre sur les fichiers touchés.
- **Check live** (boutique requise) :
  `bin/prestaflow run src/Tests/Suites/Scenarios/CreateProductAndVerify.php` —
  corriger les sélecteurs `@unverified` jusqu'au vert.

## Critères d'acceptation

- [ ] BO Products porte `enableProduct()` et `getCreatedProductId()` + la clé de
      sélecteur `productOnlineToggle`.
- [ ] `Scenarios\CreateProductAndVerify` étend `Scenario`, compose BO Login + BO
      Products + FO Product, et enchaîne login → create+enable → store id → FO
      goToProduct + assert → delete (auto-nettoyant).
- [ ] La suite `Tests\Suites\Scenarios\CreateProductAndVerify` invoque le
      scénario via `->scenario(...)`.
- [ ] Test structurel couvre l'existence + composition ; `composer test-unit`
      vert.
- [ ] Rien de cassé ailleurs.

## Fichiers touchés

- Modifiés : `src/Pages/Common/BackOffice/Products/Page.php` (2 méthodes + 1
  sélecteur).
- Créés : `src/Scenarios/CreateProductAndVerify.php`,
  `src/Tests/Suites/Scenarios/CreateProductAndVerify.php`,
  `tests/Unit/Scenarios/CreateProductAndVerifyTest.php`.
- Inchangés : la base `Scenario`, `FrontOffice\Product`, `importPage`, les autres
  pages/suites.
