# Spec #3 — Enrichir la page BO Products : actions liste + CRUD

Date : 2026-07-01
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

Les pages BackOffice savent aujourd'hui **naviguer** (via `goToSubMenu`) mais ne
portent aucune **action**. Ce spec donne à `Common/BackOffice/Products` de vraies
actions (liste, création, suppression), pour débloquer les scénarios cross-page
(#2 : créer un produit en BO → le vérifier en FO).

## Contrainte de vérification (critique ici)

Les sélecteurs du **formulaire produit** de l'admin PrestaShop diffèrent
fortement entre 1.7 / 8 / 9 (page réécrite), et aucune boutique n'est joignable
dans cet environnement. **Tous les nouveaux sélecteurs BO sont donc des
conventions best-effort marquées `@unverified`.** Conséquence explicite :

- La vérification **structurelle** (les méthodes existent, les clés de
  sélecteurs sont déclarées) est garantie sans navigateur.
- La suite de test browser (`ProductsCrud`) **ne passera pas** tant qu'elle
  n'aura pas été **corrigée contre une vraie boutique**. C'est attendu et
  documenté, pas un échec du lot.

## Décisions

- **Canonique = PS 9** (runtime, dernière version) pour les sélecteurs du
  formulaire. La **logique** des méthodes vit sur `Common` (DRY) ; seuls les
  **sélecteurs** (`defineSelectors()`) divergent. Les overrides `defineSelectors()`
  **v7/v8 sont différés** (spec de suivi, à faire en live) — on ne devine pas
  trois formulaires à l'aveugle.
- **Champs cœur** à la création : nom + prix + quantité (YAGNI ; pas d'images,
  déclinaisons, SEO, prix spécifiques).

## Architecture

### Composant 1 — Actions de liste (`Common/BackOffice/Products/Page.php`)
- `filterByName(string $name): void` — saisit le filtre nom, soumet.
- `resetFilter(): void` — réinitialise les filtres de la grille.
- `getListCount(): int` — nombre de lignes/résultats affichés.
- `getProductNameInList(int $row = 1): string` — nom de la ligne `row`.
- `goToNewProduct(): void` — clique « Ajouter » (ouvre le formulaire).

### Composant 2 — Create / Delete (mêmes fichiers)
- `createProduct(string $name, float $price = 0, int $quantity = 0): void` —
  `goToNewProduct()`, remplit nom/prix/quantité, enregistre.
- `deleteProduct(int $row = 1): void` — ouvre le menu d'actions de la ligne,
  clique Supprimer, confirme la modale.
- `getSuccessMessage(): string` — texte de l'alerte de succès (pour assertion).

### Composant 3 — Jeu de sélecteurs (déclarés, `@unverified`)
`defineSelectors()` de `Common/BackOffice/Products` s'étend (best-effort PS 9,
tokens `${row}` résolus via `getSelector('key', ['row' => $n])`) :

| clé | sélecteur (best-effort PS 9, `@unverified`) |
|-----|---------------------------------------------|
| `pageHeading` | `.page-title` (déjà présent) |
| `newProductButton` | `#page-header-desc-configuration-add` (déjà présent) |
| `filterNameInput` | `#product_grid_table th input[name="product[name]"]` |
| `searchButton` | `#product_grid_search_form button.grid-search-button` |
| `resetButton` | `#product_grid_search_form .grid-reset-button` |
| `listRow` | `#product_grid_table tbody tr:nth-child(${row})` |
| `listRowName` | `#product_grid_table tbody tr:nth-child(${row}) .column-name` |
| `resultCount` | `.pagination-total, #product_grid_panel .card-header .badge` |
| `rowActionsToggle` | `#product_grid_table tbody tr:nth-child(${row}) .dropdown-toggle` |
| `rowDeleteLink` | `#product_grid_table tbody tr:nth-child(${row}) a.grid-delete-row-link` |
| `deleteConfirmButton` | `.modal.show .btn-confirm-submit` |
| `formNameInput` | `#product_header_name_1` |
| `formPriceInput` | `#product_pricing_price_tax_excluded` |
| `formQuantityInput` | `#product_stock_quantities_delta_quantity` |
| `formSaveButton` | `#product_footer_save` |
| `successAlert` | `.alert-success, .growl-success` |

Un commentaire de tête sur `defineSelectors()` signale que ces sélecteurs sont
des conventions PS 9 non validées.

### Composant 4 — Suite auto-nettoyante
`src/Tests/Suites/BackOffice/ProductsCrud.php` :
login → `createProduct('PrestaFlow Test Product', 9.99, 10)` → assert succès →
`filterByName('PrestaFlow Test Product')` + assert `getProductNameInList()`
contient le nom → `deleteProduct()` → assert succès. La suite crée puis supprime
son propre produit (auto-nettoyage, sûr). Non exécutée en CI unitaire.

### Composant 5 — Vérification structurelle (sans navigateur)
Étendre `tests/Unit/Pages/BackOfficeNavigationTest.php` (ou un nouveau
`BackOfficeProductsTest.php`) :
- `Common/BackOffice/Products` (via v9) déclare toutes les clés de sélecteurs du
  tableau (instanciation browser-free, lecture de `->selectors`).
- Les méthodes `filterByName`, `resetFilter`, `getListCount`,
  `getProductNameInList`, `goToNewProduct`, `createProduct`, `deleteProduct`,
  `getSuccessMessage` **existent** (`method_exists`).

Le comportement réel = check live (Composant 4).

## Vérification

- `composer test-unit` vert (nouveau test structurel + existants).
- `php -l` propre sur les fichiers touchés.
- **Check live** (boutique requise) : `bin/prestaflow run
  src/Tests/Suites/BackOffice/ProductsCrud.php` — corriger les sélecteurs
  `@unverified` jusqu'au vert.

## Critères d'acceptation

- [ ] `Common/BackOffice/Products` porte les 8 méthodes (5 liste + create +
      delete + getSuccessMessage) avec les bonnes signatures.
- [ ] Toutes les clés de sélecteurs du tableau sont déclarées.
- [ ] `defineSelectors()` porte le commentaire `@unverified` (conventions PS 9).
- [ ] Suite `ProductsCrud` présente (create → filter → assert → delete),
      auto-nettoyante.
- [ ] Test structurel couvre méthodes + clés déclarées ; `composer test-unit`
      vert.
- [ ] Rien de cassé ailleurs ; overrides v7/v8 explicitement différés.

## Fichiers touchés

- Modifiés : `src/Pages/Common/BackOffice/Products/Page.php` (méthodes +
  sélecteurs).
- Créés : `src/Tests/Suites/BackOffice/ProductsCrud.php`,
  `tests/Unit/Pages/BackOfficeProductsTest.php`.
- Inchangés : les autres pages/suites, `BackOfficePage`, `importPage`, les
  Resolvers. Les stubs v7/v8/v9 de Products restent purs (overrides form v7/v8
  différés).
