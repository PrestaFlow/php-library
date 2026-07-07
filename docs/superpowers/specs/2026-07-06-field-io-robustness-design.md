# Spec — Robustesse des I/O de champ (CommonPage)

Date : 2026-07-06
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

Au fil des validations live, deux primitives de `CommonPage` se sont révélées
défaillantes et ont dû être contournées à la main dans plusieurs pages :

- **`getInputValue($selector)`** lit l'**attribut** `value`
  (`$element->getAttribute('value')`). Cassé pour les `<textarea>` (dont la valeur
  est le contenu texte, pas un attribut) et pour tout champ dont la valeur
  courante diffère de l'attribut initial. Contourné par des lectures JS `.value`
  (OrderView `readValue`, Products `getFormPrice`).
- **`selectOption`/`selectValue`** ne gèrent qu'un sélecteur `#id`/`.class` simple
  (elles transforment le CSS en XPath `select[@id="…"]`), donc un sélecteur
  **composé** produit un faux XPath → « Option non trouvée ». De plus elles posent
  `selected` par attribut sans événement `change` (ne pilotent pas les select2).
  Contournées en JS (statut commande OrderView, pays invité Checkout).
- `selectOption` contient un **`file_put_contents('temp.log', json_encode(...))`**
  de debug qui pollue le repo à chaque appel.

Le pattern « poser une valeur en JS » est en plus **dupliqué** (`Products::jsSetValue`,
JS inline OrderView/Checkout).

## Objectif

Corriger ces primitives au cœur, ajouter un helper partagé de pose de valeur en
JS, et supprimer les contournements ad hoc — sans changer les signatures ni le
comportement des flux déjà verts.

### Hors périmètre (YAGNI)

Ne pas modifier `setValue()` (garde le click+type réaliste pour les champs
visibles) ; pas de refonte plus large des helpers ; pas de nouvelle API publique
au-delà de `setValueByJs`.

## Décisions

- **Sur-ensembles, pas de restriction** : les nouveaux comportements gèrent au
  moins tous les cas actuels — ils élargissent la couverture, ne la réduisent pas.
- **On bascule** les contournements select (statut OrderView, pays invité) sur le
  `selectValue()` corrigé — même mécanisme (querySelector + `.value` + `change`),
  juste centralisé.
- **Validation** : filet principal = tests unitaires structurels ; plus un
  **smoke live léger** (une passe des suites représentatives) quand le Chrome
  local le permet. Chrome est instable en fin de session ; les changements étant
  des sur-ensembles, le risque de régression est faible.

## Architecture

### 1. `CommonPage::getInputValue()` — lecture `.value` en JS

Remplacer la lecture d'attribut par une lecture de la **propriété `.value`** via
`evaluate`, en gardant la signature et le retour `string` (chaîne trimée, `''` si
absent). Corrige `<textarea>` et valeurs dynamiques. `setValue()`, qui appelle
`getInputValue()` en interne pour décider s'il faut vider le champ, en bénéficie.

### 2. `CommonPage::selectOption()` / `selectValue()` — querySelector + change

Réécrire `selectOption($selector, $value)` :
- `querySelector($selector)` (gère les sélecteurs composés) pour trouver le
  `<select>` ;
- trouver l'`<option>` dont le libellé (trim) vaut `$value` ;
- poser `select.value = option.value` et dispatcher `new Event('change', {bubbles:true})`
  (pilote les select2) ;
- si l'option est absente, conserver l'échec explicite (`Expect` « Option "X" not
  found for selector "Y" »).
- **Supprimer** la ligne `file_put_contents('temp.log', …)`.
`selectValue()` reste un alias.

### 3. `CommonPage::setValueByJs()` — helper partagé

Ajouter `setValueByJs(string $selector, string $value): void` : pose
`element.value` + dispatch `input` et `change` en JS. C'est la version fiable pour
les champs d'onglets inactifs/cachés (là où le click+type de `setValue` échoue).

### 4. Factorisation des pages

- `Products::jsSetValue` → supprimée ; les appels (`createProduct`, `updatePrice`)
  utilisent `setValueByJs`.
- `OrderView::readValue` → supprimée ; `getInternalNote`/`getTracking` utilisent
  `getInputValue` (désormais correct) ; les blocs JS de `updateStatus` repassent
  sur `selectValue()`.
- `Checkout::fillNewAddress` → le bloc JS pays repasse sur `selectValue()`.
- (Les autres usages JS spécifiques — cases de consentement, note pilotée par
  submit de form — restent tels quels : ils ne sont pas de simples set/select.)

## Vérification

### Unitaire (browser-free)

- `CommonPage::getInputValue` : son corps lit `.value` via `evaluate`
  (`ReflectionMethod` + lecture source) et ne lit plus `getAttribute('value')`.
- `CommonPage::selectOption` : son corps utilise `querySelector` + `change` et ne
  contient plus `file_put_contents`.
- `CommonPage` a la méthode `setValueByJs`.
- Les pages ne référencent plus `jsSetValue`/`readValue` (méthodes supprimées).
- `composer test-unit` reste vert.

### Live (smoke léger — quand Chrome le permet)

- `EditProduct` (exerce `setValueByJs` via createProduct/updatePrice) vert.
- `ManageOrder`/`OrderLifecycle` (exerce `selectValue` corrigé pour le statut) et
  `GuestCheckout` (exerce `selectValue` pour le pays) verts.
Reproductible ; un échec pointe une régression à corriger avant de committer.

## Critères d'acceptation

- [ ] `getInputValue` lit `.value` en JS.
- [ ] `selectOption`/`selectValue` gèrent les sélecteurs composés + `change` ;
      plus de `file_put_contents`.
- [ ] `setValueByJs` présent ; `Products::jsSetValue` et `OrderView::readValue`
      supprimées, appelants basculés.
- [ ] `composer test-unit` vert (structurels + existants).
- [ ] **Live smoke** : `EditProduct` + un scénario à `selectValue`
      (OrderLifecycle ou GuestCheckout) verts, quand Chrome le permet.

## Fichiers touchés

- Modifiés : `src/Pages/CommonPage.php` (getInputValue, selectOption, +setValueByJs) ;
  `src/Pages/Common/BackOffice/Products/Page.php` ;
  `src/Pages/Common/BackOffice/OrderView/Page.php` ;
  `src/Pages/Common/FrontOffice/Checkout/Page.php` ;
  tests unitaires (`tests/Unit/Pages/CommonPageIoTest.php` nouveau + ajustements).
- Inchangés : `setValue()`, les scénarios/suites, le reste des pages.

## Commits

Approbation explicite requise avant tout `git commit`. Les subagents
implémenteurs ne committent pas ; le coordinateur regroupe après accord.

## Addendum — livré (2026-07-06)

Unitaires **69/355** verts. **Smoke live 3/3** — du premier coup, aucune
régression :
- `EditProduct` **4/4** (exerce `setValueByJs` via `createProduct`/`updatePrice`).
- `OrderLifecycle` **9/9** (exerce `selectValue` corrigé pour le statut, et
  `getInputValue` corrigé pour la note et le tracking).
- `GuestCheckout` **4/4** (exerce `selectValue` pour le pays).

`setValue` inchangé. `getCurrentStatus` conservé tel quel (lecture de la
propriété `.text` de l'option sélectionnée, pas un `.value` plat).
