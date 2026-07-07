# Spec — Cycle de vie commande (Checkout → gestion BO)

Date : 2026-07-06
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

Le scénario `CheckoutOrder` crée une commande de bout en bout (validé live 5/5).
La couche BO Orders sait retrouver une commande par référence
(`filterByReference`/`getOrderReferenceInList`). Il n'existe pas encore de page
de **détail commande** ni de gestion du cycle de vie (statut, note, suivi).

Prochain incrément : piloter le cycle de vie d'une commande en BackOffice —
changer son statut, ajouter une note interne, saisir un numéro de suivi — en
**chaînant depuis le Checkout** (un scénario crée la commande, un autre la gère).

## Objectif

Fournir une page BO `OrderView` (détail commande) et un scénario `ManageOrder`
qui, sur la commande créée par `CheckoutOrder`, **change le statut** (vérifié via
l'historique), **pose une note interne** et **renseigne un numéro de suivi**,
chaque action vérifiée. Une suite `OrderLifecycle` compose les deux scénarios.
Validé live contre la PS 9 locale.

### Hors périmètre (YAGNI)

Remboursements, avoirs/factures, expéditions partielles, création de transporteur,
workflows multi-statuts, emails de commande.

## Décisions

- **Deux scénarios composés par une suite** (choix utilisateur) : `CheckoutOrder`
  (FO, existant, crée et `store('orderReference')`) puis `ManageOrder` (BO,
  nouveau, `retrieve('orderReference')`). `store`/`retrieve` sont partagés au
  niveau de la suite ; le run résout `scenarioName` par test (via la classe de la
  closure) donc chaque scénario garde ses propres params — vérifié dans le code.
- **Cycle complet** (choix utilisateur) : statut + note interne + suivi.
- **Vérification du statut via l'historique** (plus robuste qu'un badge).
- **Sélecteurs best-effort → corrigés en live**, méthode éprouvée (probes
  chrome-php, une suite à la fois, `pkill -9 -i chrome` + `rm datas/.broswer*`
  entre runs).
- `ManageOrder` réutilisable seul si `orderReference` est fourni en param
  (sinon `retrieve`).

## Architecture

### Page BO `OrderView` (nouvelle)

`Common/BackOffice/OrderView` + stubs `v7`/`v8`/`v9` (car `importPage` construit
`Pages\v{N}\BackOffice\OrderView\Page`). Détail commande `/sell/orders/{id}/view`.

Sélecteurs best-effort (corrigés live) :
- `statusSelect` `#update_order_status_action_input` (select2)
- `updateStatusButton` `#update_order_status_action_btn`
- `historyRows` `#orderHistoryTable tbody tr`
- `internalNoteTextarea` `#order_internal_note`
- `internalNoteSaveButton` (bouton de sauvegarde de la note — sélecteur exact
  validé live)
- `trackingNumberInput` (champ n° de suivi du bloc transporteur — validé live)
- `trackingSaveButton` (validé live)

Méthodes :
- `getCurrentStatus(): string`
- `updateStatus(string $status): void` — sélectionne le statut (via le `<select>`
  sous-jacent + `change`) puis clique « Mettre à jour ».
- `hasStatusInHistory(string $status): bool` — le nom apparaît dans une ligne
  d'historique.
- `setInternalNote(string $note): void` / `getInternalNote(): string`
- `addTracking(string $number): void` / `getTracking(): string`

### Page BO `Orders` (étendue)

- `openOrder(int $row = 1): void` — clique le lien de la ligne
  (`#order_grid_table tbody tr:nth-child(${row}) a`) pour ouvrir le détail.

### Scénario `ManageOrder` (nouveau, BO)

`src/Scenarios/ManageOrder.php`. Params (défauts validés live) :
`orderStatus` (nom FR, ex. « Paiement accepté »), `internalNote`,
`trackingNumber`, `locale` (fr).

```
$ref = retrieve('orderReference') (ou getParam('orderReference'))
→ Orders.goTo() → Orders.filterByReference($ref) → Orders.openOrder(1)
→ updateStatus(orderStatus) ; assert hasStatusInHistory(orderStatus)
→ setInternalNote(internalNote) ; assert getInternalNote() contient internalNote
→ addTracking(trackingNumber) ; assert getTracking() contient trackingNumber
```

Propage la locale à la suite (`$testSuite->params['locale']`) comme
`CheckoutOrder`, pour les libellés/URLs FR.

### Suite `OrderLifecycle` (nouvelle)

`src/Tests/Suites/Scenarios/OrderLifecycle.php` :
```php
$this
->describe('Create an order then manage its lifecycle in the BackOffice')
->scenario(\PrestaFlow\Library\Scenarios\CheckoutOrder::class)
->scenario(\PrestaFlow\Library\Scenarios\ManageOrder::class);
```

## Hypothèses à valider en live

- La commande créée par le checkout est ouvrable depuis la liste et sa page de
  détail expose le select de statut, l'historique, la note et le bloc transporteur.
- Le **tracking** est la zone à risque (bloc transporteur, possiblement une
  modale) ; sélecteurs corrigés live. Si trop fragile, repli possible : livrer
  statut + note d'abord, tracking ensuite.
- Le select de statut est un **select2** ; interaction via le `<select>` + `change`.

## Vérification

### Unitaire (browser-free, pattern `BackOfficeOrdersTest`)

- `OrderView` (v9) déclare les clés de sélecteurs et les méthodes attendues.
- `Orders` a `openOrder`.
- `ManageOrder` étend `Scenario` et déclare `orderStatus`/`internalNote`/
  `trackingNumber` ; la suite `OrderLifecycle` étend `TestsSuite` et compose les
  deux scénarios (référence les deux classes).
- `composer test-unit` reste vert.

### Live (le vrai critère — PS 9 locale)

- La suite `OrderLifecycle` passe **de bout en bout** : le checkout crée la
  commande, puis `ManageOrder` change le statut (présent dans l'historique), pose
  la note (relue), renseigne le suivi (relu). Vert, reproductible.

## Critères d'acceptation

- [ ] Page `OrderView` (getCurrentStatus/updateStatus/hasStatusInHistory/
      setInternalNote/getInternalNote/addTracking/getTracking) + stubs v7/8/9.
- [ ] `Orders::openOrder()`.
- [ ] Scénario `ManageOrder` + suite `OrderLifecycle` composant les 2 scénarios.
- [ ] `composer test-unit` vert (structurels + existants).
- [ ] **Live** : `OrderLifecycle` verte de bout en bout, reproductible.

## Fichiers touchés (prévision)

- Créés : `src/Pages/Common/BackOffice/OrderView/Page.php` (+ stubs v7/8/9) ;
  `src/Scenarios/ManageOrder.php` ; `src/Tests/Suites/Scenarios/OrderLifecycle.php` ;
  tests unitaires associés.
- Modifiés : `src/Pages/Common/BackOffice/Orders/Page.php` (`openOrder`).
- Inchangés : `CheckoutOrder` et les pages FO ; le reste.

## Commits

Approbation explicite requise avant tout `git commit`. Les subagents
implémenteurs ne committent pas ; le coordinateur regroupe après accord.

## Addendum — VERT complet (2026-07-06)

`OrderLifecycle` passe **9/9 en live, reproductible** : checkout → commande
retrouvée → ouverte → **statut changé** → **note interne** → **suivi**, chaque
étape vérifiée. Correctifs live (order-view PS 9 en debug mode) :
- **openOrder** : lien de la grille scopé `a[href*="/orders/"][href*="/view"]`
  (le lien client finit aussi en `/view`).
- **Statut** : le BO admin est en **anglais** (« Payment accepted ») ; le champ
  est un **select2** — on pose `select.value` + `change` (le `selectOption` de la
  lib ne le pilote pas), et on vérifie via l'option sélectionnée
  (`#historyTabContent` entre en collision avec l'historique du profiler de debug).
- **Note** : `#private_note_note` (note client, panneau replié) — pilotée en JS
  (valeur + submit du form) ; **lecture via `.value` en JS** (l'ancien
  `getInputValue` lit l'attribut `value`, inexistant sur un `<textarea>`).
- **Suivi** : le champ vit dans une **modale d'édition transporteur** qui doit
  être **ouverte** pour peupler le form (carrier id + token) — un submit direct
  ne sauve rien. On ouvre la modale, pose la valeur en JS, clique « Update » ;
  la **lecture ré-ouvre la modale** (son champ se pré-remplit avec la valeur
  enregistrée — la cellule d'affichage de l'onglet Carriers est lazy et peu
  fiable). Helper privé `openShippingModal()`.
- Env : mot de passe du client démo posé en base (cf. checkout) ; France ajoutée
  aux modules de paiement (cf. checkout).
