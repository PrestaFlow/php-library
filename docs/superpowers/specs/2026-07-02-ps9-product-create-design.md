# Spec 1.3.1-d — `createProduct` pour le flux produit PS 9

Date : 2026-07-02
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

La couche BackOffice (auth + navigation menu + listes) est validée live (6/6 en
série, cf. passes 1-4). Le dernier maillon avant une release « 1.3.1 BackOffice
validated » est le **CRUD produit** (`ProductsCrud` + scénario
`CreateProductAndVerify`).

Le `createProduct` actuel suppose un **formulaire classique unique** (name +
price + quantity + save sur la même page). Ce n'est pas le fonctionnement de
PrestaShop 9 : la création est un **flux JS/AJAX** — on choisit d'abord un type
de produit, un brouillon est instancié, puis le formulaire d'édition complet
s'affiche. Les sélecteurs best-guess (`#product_pricing_price_tax_excluded`,
`#product_stock_quantities_delta_quantity`) ne correspondaient pas au DOM réel.

## Reverse-engineering live (2026-07-02, PS 9.0.0-rc.1 locale)

Carte confirmée en sondant la boutique locale :

- **Page de création** : `/sell/catalog/products/create?shopId=1`.
- **Tuiles de type** : `<button class="product-type-choice ...">` (pas
  d'attribut `data-product-type`). « Standard product » est la **première tuile**
  et est **pré-sélectionnée par défaut** (`btn-primary` ; les autres sont
  `btn-outline-secondary`).
- **Bouton de création du brouillon** : `#create_product_create` (name
  `create_product[create]`, libellé « Add new product ») — déjà activé.
- Après création du brouillon, le **formulaire d'édition complet s'affiche
  inline**. Tous les panneaux d'onglets restent dans le DOM (pas de lazy-load) :
  les champs sont adressables même quand leur onglet n'est pas actif.
- Onglets : Description / Details / **Stocks** (`#product_stock-tab`) / Shipping /
  **Pricing** (`#product_pricing-tab`) / SEO / Options.
- Champs éditables (remplissables par sélecteur, sans changer d'onglet) :
  - **Nom** : `#product_header_name_1` (multilingue `[1]`..`[4]`).
  - **Prix HT** : `#product_pricing_retail_price_price_tax_excluded`.
  - **Quantité** : `#product_stock_quantities_delta_quantity_delta` (l'input
    « delta » visible et éditable ; les frères `_initial_quantity` / `_quantity`
    sont cachés).
  - **Actif** : radios `#product_header_active_0` / `#product_header_active_1`
    (c'est un **radio**, pas un toggle → on **clique**).
  - **Sauvegarde** : `#product_footer_save` (« Save and publish »).
- L'**id du produit** apparaît dans l'URL `/products/{id}/` après la sauvegarde.
- **Caveat** : un `evaluate().click()` brut sur `#create_product_create` ne
  redirige PAS — le submit réel est AJAX/JS. La lib doit utiliser son `click()`
  robuste (clic par coordonnées + fallback JS) suivi de `waitForPageReload()`.
- **Piège** : le produit de démo id=1 possède des **déclinaisons** (onglet
  Combinations, pas de champ quantité unique) — ne pas s'en servir comme
  référence pour un produit standard.

## Objectif

`createProduct($name, $price, $quantity)` crée et publie un produit **standard**
en pilotant le vrai flux PS 9, de bout en bout, de façon fiable contre la
boutique locale.

### Hors périmètre

Types Combinations / Pack / Virtual ; édition d'un produit existant ; gestion
avancée du stock (seuils bas, emplacements, ruptures) ; onglets Shipping/SEO/
Options.

## Décisions

- **Quantité incluse** (choix utilisateur), mais **sans piloter l'onglet Stock** :
  comme tous les panneaux sont dans le DOM, un `setValue` sur
  `#product_stock_quantities_delta_quantity_delta` suffit. On évite la fragilité
  d'un clic d'onglet + attente asynchrone.
- **Pas de clic sur la tuile de type** : « Standard product » étant déjà par
  défaut, on clique directement `#create_product_create`. On n'introduit pas de
  sélecteur fragile pour la tuile (les `<button>` n'ont pas d'id/valeur ;
  matcher par texte serait dépendant de la locale). Le support d'autres types
  est explicitement hors périmètre.
- **Robustesse du submit AJAX** : `click()` (coordonnées + fallback JS) +
  `waitForPageReload()` borné, déjà éprouvés sur la nav BO.

## Architecture

Tout se passe dans `src/Pages/Common/BackOffice/Products/Page.php`.

### Composant 1 — `goToNewProduct()`

Instancie le brouillon éditable, **en cliquant des liens** (fidèle à la
philosophie de la lib : on ne construit pas d'URL admin à la main, le href porte
la session/le contexte). Deux clics :

```php
public function goToNewProduct(): void
{
    // Le bouton "Add new product" de la liste ; son href pointe vers
    // /sell/catalog/products/create?shopId=1 (confirmé live, sans token).
    $this->click($this->getSelector('newProductButton'));
    $this->waitForPageReload();
    // Sur la page /create, "Standard product" est déjà pré-sélectionné
    // (btn-primary) → on clique "Add new product" pour instancier le brouillon.
    // Le submit est AJAX : le formulaire éditable complet apparaît inline.
    $this->click($this->getSelector('createProductButton'));
    $this->waitForPageReload();
}
```

- `newProductButton` (`#page-header-desc-configuration-add`) est **confirmé live**
  sur la liste PS 9 (href `.../products/create?shopId=1`).
- Aucune construction d'URL, aucun `navigateTo`/token : deux clics de liens
  éprouvés par le `click()` robuste (coordonnées + fallback JS) + le
  `waitForPageReload()` borné.

### Composant 2 — `createProduct($name, $price, $quantity)`

Ouvre le brouillon, remplit le formulaire inline puis publie. La signature et le
`goToNewProduct()` interne sont **inchangés** (les appelants — `ProductsCrud`,
scénario — restent identiques) :

```php
public function createProduct(string $name, float $price = 0, int $quantity = 0): void
{
    $this->goToNewProduct();
    $this->setValue($this->getSelector('formNameInput'), $name);
    $this->setValue($this->getSelector('formPriceInput'), (string) $price);
    $this->setValue($this->getSelector('formQuantityInput'), (string) $quantity);
    $this->click($this->getSelector('productOnlineToggle'));  // radio "actif" = oui
    $this->click($this->getSelector('formSaveButton'));       // "Save and publish"
    $this->waitForPageReload();
}
```

- Ordre : create draft (`goToNewProduct`) → fill → activer → save.
- Différences vs l'existant : ajout du 2e clic dans `goToNewProduct`
  (`createProductButton`), sélecteurs prix/quantité corrigés, et **ajout du clic
  `productOnlineToggle`** avant le save (le produit doit être actif pour être
  visible en FO — l'ancien code laissait ça à un `enableProduct()` séparé).

### Composant 3 — sélecteurs corrigés / ajoutés

Dans `defineSelectors()` :

| clé | valeur |
|---|---|
| `createProductButton` | `#create_product_create` *(nouveau)* |
| `formNameInput` | `#product_header_name_1` *(inchangé)* |
| `formPriceInput` | `#product_pricing_retail_price_price_tax_excluded` *(corrigé)* |
| `formQuantityInput` | `#product_stock_quantities_delta_quantity_delta` *(corrigé)* |
| `productOnlineToggle` | `#product_header_active_1` *(inchangé)* |
| `formSaveButton` | `#product_footer_save` *(inchangé)* |

`getCreatedProductId()` (regex `#/products/(\d+)#` sur l'URL courante) reste
valable : l'id est dans l'URL après la sauvegarde.

## Vérification

### Unitaire (browser-free)

`tests/Unit/Pages/ProductCreateSelectorsTest.php` (nom indicatif) :

- `defineSelectors()` de la page Products **contient** les clés/valeurs corrigées
  ci-dessus (`createProductButton`, `formPriceInput` =
  `#product_pricing_retail_price_price_tax_excluded`, `formQuantityInput` =
  `#product_stock_quantities_delta_quantity_delta`).
- Le corps de `createProduct()` remplit nom/prix/quantité puis clique actif puis
  save (ordre create→fill→save), via `ReflectionMethod` + lecture du fichier.
- `composer test-unit` reste vert (dont les tests existants).

### Live (le vrai critère — boutique PS 9 locale)

Mécanique : `pkill -f "Google Chrome" ; rm -f datas/.broswer datas/.broswer-options`,
une suite à la fois, `.env.local` configuré, `datas/` existe.

- **`ProductsCrud`** : login → liste → `goToNewProduct` → `createProduct` →
  retour liste, filtre par nom → le produit créé est présent → `deleteProduct`
  (auto-nettoyant). Vert.
- **Scénario `CreateProductAndVerify`** : create en BO → produit **visible en
  FO** (titre attendu) → cleanup (delete). Vert.

## Critères d'acceptation

- [ ] `defineSelectors()` utilise les 6 sélecteurs de la table (2 corrigés, 1
      ajouté).
- [ ] `goToNewProduct()` clique `newProductButton` (→ `/products/create`) puis
      `createProductButton` (pas de clic sur tuile de type), chacun suivi de
      `waitForPageReload()`.
- [ ] `createProduct()` remplit nom/prix/quantité, active le produit, sauvegarde,
      dans cet ordre, avec `waitForPageReload()`.
- [ ] `composer test-unit` vert (test structurel + existants).
- [ ] **Live** : `ProductsCrud` et `CreateProductAndVerify` passent au vert
      contre la PS 9 locale, de façon reproductible.

## Fichiers touchés

- Modifiés :
  - `src/Pages/Common/BackOffice/Products/Page.php` (`defineSelectors`,
    `goToNewProduct`, `createProduct`).
  - `src/Scenarios/CreateProductAndVerify.php` : **retirer l'appel
    `enableProduct()` désormais redondant** (l'activation est faite dans
    `createProduct` avant le save ; un `enableProduct()` après le save ne
    persisterait pas — bug latent de l'ancien flux). `enableProduct()` reste
    disponible comme méthode autonome de la page.
- Créés : `tests/Unit/Pages/ProductCreateSelectorsTest.php`.
- Inchangés : `getCreatedProductId`, `deleteProduct`, `filterByName`,
  `getListCount`, `enableProduct` (la méthode) ; la suite `ProductsCrud` ; les
  autres pages ; le cycle de vie navigateur.

## Commits

Le propriétaire du dépôt exige une **approbation explicite avant tout
`git commit`**. Les subagents implémenteurs ne lancent PAS `git commit`/`git add` ;
ils laissent les changements dans le working tree. Le coordinateur regroupe les
commits une fois l'utilisateur d'accord.

## Addendum — validation live (2026-07-02)

Les deux suites passent **4/4 en live** contre la PS 9 locale (`ProductsCrud`
reproductible ×3 ; `CreateProductAndVerify` ×2). La validation a révélé quatre
écarts par rapport au design initial, tous corrigés :

1. **Le bouton « Add new product » ouvre une MODALE JS** (tuiles de type en
   overlay sur la liste), il ne navigue pas vers `/create`. `goToNewProduct()`
   lit donc le href de `#page-header-desc-configuration-add` et **navigue
   directement** vers `/products/create?shopId=1` (contourne la modale), puis
   clique `#create_product_create`.
2. **Toast de succès** : `.alert-success` matche d'abord un template caché vide ;
   le vrai toast porte `role="alert"`. Sélecteur corrigé →
   `.alert-success[role="alert"]`.
3. **Suppression** : le modal de confirmation (`#product-grid-confirm-modal
   .btn-confirm-submit`) apparaît en fondu et `click()` n'attend pas →
   `deleteProduct()` fait `waitUntilContainsElement(deleteConfirmButton, 10000)`
   avant de cliquer.
4. **Vérif FO** : l'URL non-friendly `index.php?controller=product&id_product=N`
   rend une page **vide** sur cette boutique, et deviner `{id}-{slug}.html` est
   fragile. On lit donc l'**URL canonique depuis le BO** (lien Preview
   `#product_footer_actions_preview`) via un nouveau `getCreatedProductUrl()`, et
   on navigue le FO via un nouveau `FrontOfficePage::goToUrl($url)`. Le scénario
   stocke `productUrl` et vérifie via cette URL.

Fichiers réellement touchés (au-delà du design) : `src/Pages/FrontOfficePage.php`
(`goToUrl`), et dans la page Products : `getCreatedProductUrl` + sélecteur
`productPreviewLink` + attente dans `deleteProduct` + sélecteur `successAlert`.
Tests unitaires : 44/248.
