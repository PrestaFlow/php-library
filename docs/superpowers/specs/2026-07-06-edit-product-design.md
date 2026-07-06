# Spec — Édition d'un produit existant (BackOffice)

Date : 2026-07-06
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

La page `BackOffice\Products` gère la création (`createProduct`, flux PS 9
multi-étapes validé live) et la suppression (`deleteProduct`) de produits, plus
le filtrage et les lectures de liste. Elle expose déjà les sélecteurs du
formulaire d'édition (`formNameInput`, `formPriceInput`, `formQuantityInput`,
`formSaveButton`). Il manque le **« update »** : ouvrir un produit existant depuis
la liste et modifier un champ.

Premier volet du CRUD avancé : **éditer le prix d'un produit existant** (ouvrir
depuis la liste → changer le prix → sauvegarder → vérifier).

## Objectif

Fournir de quoi ouvrir un produit depuis la grille, modifier son prix et vérifier
la valeur enregistrée, via un scénario `EditProduct` **auto-nettoyant** (crée le
produit, l'édite, vérifie, puis le supprime). Validé live contre la PS 9 locale.

### Hors périmètre (YAGNI)

Édition du nom/description/images/catégories/déclinaisons/SEO ; édition en masse ;
duplication ; stock avancé (volets suivants).

## Décisions

- **Scénario auto-nettoyant** (choix utilisateur) : create → open → edit → verify
  → delete, déterministe et sans pollution (comme `ProductsCrud`).
- **On édite le prix** (champ simple, déjà connu du formulaire) ; vérification par
  **`contains`** car le champ prix rend souvent une valeur formatée (« 19.990000 »).
- **Lecture du prix en JS** (`.value`) — robuste (le `getInputValue` de la lib lit
  l'attribut `value`, peu fiable pour un champ dont la valeur courante diffère de
  l'attribut initial).
- **Sélecteurs best-effort → corrigés en live** (le lien d'édition de la ligne est
  le point à valider ; prix/save déjà éprouvés).

## Architecture

### Page `BackOffice\Products` étoffée

Sélecteur ajouté (best-effort, corrigé live) :
- `listRowLink` `#product_grid_table tbody tr:nth-child(${row}) a[href*="/products/"][href*="/edit"]`
  (le lien d'édition de la ligne ; scopé à `/products/…/edit`).

Méthodes ajoutées :
- `openProduct(int $row = 1): void` — clique `listRowLink` puis `waitForPageReload`
  (ouvre `/products/{id}/edit`), comme `Orders::openOrder`.
- `updatePrice(float $price): void` — `setValue(formPriceInput, (string) $price)`
  puis `click(formSaveButton)` puis `waitForPageReload`.
- `getFormPrice(): string` — lit la `.value` de `formPriceInput` via JS.

### Scénario `EditProduct`

`src/Scenarios/EditProduct.php`. Params (défauts) : `locale` (non requis pour le
BO, mais cohérent), `productName` (« PF Edit Test »), `initialPrice` (9.99),
`newPrice` (19.99), `quantity` (10).

```
BO Login (goToPage('index') + login())
  → Products.goTo() → createProduct(productName, initialPrice, quantity)
  → Products.goTo() → filterByName(productName) → openProduct(1)
  → updatePrice(newPrice)
  → assert getFormPrice() contient (string) newPrice   (ex. "19.99")
  → Products.goTo() → filterByName(productName) → deleteProduct(1)   (nettoyage)
```

### Suite `EditProduct`

`src/Tests/Suites/Scenarios/EditProduct.php` — lance le scénario.

## Hypothèses à valider en live

- Le **lien d'édition de la ligne** de la grille produit ouvre bien
  `/products/{id}/edit` ; sélecteur corrigé live si besoin.
- Après `updatePrice`, le formulaire recharge avec le prix enregistré et
  `formPriceInput`.value le contient (valeur possiblement formatée à 6 décimales).

## Vérification

### Unitaire (browser-free, pattern `BackOfficeProductsTest`)

- `Products` (v9) déclare `listRowLink` et a les méthodes `openProduct`,
  `updatePrice`, `getFormPrice`.
- Le scénario `EditProduct` étend `Scenario` et déclare
  `productName`/`initialPrice`/`newPrice`/`quantity` ; la suite `EditProduct`
  étend `TestsSuite`.
- `composer test-unit` reste vert.

### Live (le vrai critère — PS 9 locale)

- La suite `EditProduct` passe : create → liste → open → updatePrice →
  `getFormPrice()` contient le nouveau prix → delete. Vert, reproductible.

## Critères d'acceptation

- [ ] `Products` : `listRowLink` + `openProduct`/`updatePrice`/`getFormPrice`.
- [ ] Scénario `EditProduct` + suite, params.
- [ ] `composer test-unit` vert (structurels + existants).
- [ ] **Live** : `EditProduct` verte de bout en bout, reproductible.

## Fichiers touchés (prévision)

- Modifiés : `src/Pages/Common/BackOffice/Products/Page.php`.
- Créés : `src/Scenarios/EditProduct.php` ;
  `src/Tests/Suites/Scenarios/EditProduct.php` ; tests unitaires associés.
- Inchangés : le reste des pages, les autres scénarios.

## Commits

Approbation explicite requise avant tout `git commit`. Les subagents
implémenteurs ne committent pas ; le coordinateur regroupe après accord.

## Addendum — VERT complet (2026-07-06)

`EditProduct` passe **4/4 en live, reproductible** : login → create → ouverture
depuis la liste → prix passé à 19.99 (`getFormPrice()` = « 19.990000 » contient
« 19.99 ») → delete. `ProductsCrud` reste **4/4** après le fix ci-dessous.

**Bug latent corrigé (découvert par cet incrément) :** `createProduct` faisait
`setValue` sur le prix (onglet Pricing) et la quantité (onglet Stocks), or ces
champs sont sur des **onglets inactifs** — le clic ne peut pas les focus et le
`typeText` fuit dans le champ nom (produit nommé « PF Edit Test9.9910 », prix à 0).
`createProduct` **et** `updatePrice` posent désormais ces valeurs en **JS**
(`.value` + input/change) via un helper privé `jsSetValue()` ; le `openProduct`
(lien `a[href*="/products/"][href*="/edit"]`) fonctionne du premier coup. `listRowLink`
validé. `getFormPrice` lit `.value` en JS.
