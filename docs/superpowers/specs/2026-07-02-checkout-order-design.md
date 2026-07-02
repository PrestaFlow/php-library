# Spec — Scénario Checkout / commande (FO → BO)

Date : 2026-07-02
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

La couche BackOffice (auth, navigation, CRUD produit) et un premier scénario
cross-page (`CreateProductAndVerify`, BO→FO) sont validés live contre une
PrestaShop 9 locale. Le socle FrontOffice existe partiellement : page `Product`
avec `addToCart()`, scénario `AddProductToCart` (catégorie → produit → panier),
pages `Cart` et `OrderHistory` à l'état de stubs vides.

Prochain incrément « métier » : un **scénario de commande de bout en bout** —
un client connecté ajoute un produit au panier, passe le tunnel de commande,
arrive sur la confirmation, puis on retrouve la commande dans le BackOffice.

## Objectif

Fournir un scénario réutilisable `CheckoutOrder` qui commande en tant que
**client existant connecté**, avec un **paiement hors-ligne**, et **vérifie la
commande côté FrontOffice (page de confirmation, référence) puis côté BackOffice
(commande retrouvée dans la liste)**. Le tout validé live contre la PS 9 locale.

### Hors périmètre (YAGNI)

Guest checkout ; création de compte/adresse à la volée ; multi-transporteurs ou
multi-paiements ; codes promo ; paiement en ligne réel (gateway) ; statuts de
commande avancés en BO ; historique de commande FO.

## Décisions

- **Client existant connecté** (choix utilisateur) : login FO avec un client
  démo *ayant déjà une adresse enregistrée*. Identifiants = params du scénario,
  valeurs par défaut confirmées en live. Tunnel plus court et stable (pas de
  création d'adresse).
- **Paiement** : le **premier module de paiement hors-ligne activé** (virement
  bancaire ou chèque). Déterminé/validé en live ; pas une décision produit.
- **Vérification complète FO → BO** (choix utilisateur) : confirmation FO
  (référence) *et* commande retrouvée en BackOffice.
- **Démarrage panier** : réutilise `addToCart()` sur un produit connu (params),
  plus stable que le parcours catégorie→listing.
- **Sélecteurs best-effort → corrigés en live**, méthode déjà éprouvée sur le
  CRUD produit (probes chrome-php, une suite à la fois, kill Chrome + `rm
  datas/.broswer*` entre runs).

## Architecture

Composants isolés, chacun une responsabilité et une interface claire. Chaque
nouvelle page Common a ses stubs `v7`/`v8`/`v9` (car `importPage($name)` construit
`Pages\v{N}\<name>\Page`).

### Pages FrontOffice

1. **`FrontOffice\Login`** (`Common/FrontOffice/Login`, stub existant à étoffer)
   - `login(string $email, string $password): void` — remplit email + mot de
     passe, soumet, attend la navigation.
   - Sélecteurs : `emailInput` `#login-form input[name="email"]`,
     `passwordInput` `#login-form input[name="password"]`, `submitButton`
     `#submit-login`.

2. **`FrontOffice\Cart`** (`Common/FrontOffice/Cart`, stub vide à étoffer)
   - `goToCart(): void` — navigue vers le contrôleur panier.
   - `proceedToCheckout(): void` — clique « Passer commande ».
   - Sélecteurs : `checkoutButton` `.cart-detailed-actions a.btn, .checkout a`.

3. **`FrontOffice\Checkout`** (`Common/FrontOffice/Checkout`, nouveau) — le tunnel
   PS 9 (contrôleur `order`, page unique à étapes repliables) :
   - `confirmAddresses(): void` — le client a une adresse → « Continuer ».
   - `chooseShipping(): void` — sélectionne un transporteur → « Continuer ».
   - `choosePaymentAndConfirm(): void` — coche un paiement hors-ligne + les CGV →
     « Commander ».
   - Sélecteurs : `addressesContinue`
     `#checkout-addresses-step [name="confirm-addresses"]` ;
     `shippingOption` `#checkout-delivery-step input[name^="delivery_option"]` ;
     `shippingContinue` `#checkout-delivery-step [name="confirmDeliveryOption"]` ;
     `paymentOption` `input[name="payment-option"]` ;
     `termsCheckbox` `#conditions-to-approve input[type="checkbox"]` ;
     `placeOrderButton` `#payment-confirmation button`.

4. **`FrontOffice\OrderConfirmation`** (`Common/FrontOffice/OrderConfirmation`,
   nouveau)
   - `isConfirmed(): bool` — présence du bloc de confirmation.
   - `getOrderReference(): string` — lit la référence de commande.
   - Sélecteurs : `confirmationBlock` `#content-hook_order_confirmation` ;
     `orderReference` (référence dans les détails — sélecteur exact validé live).

### Page BackOffice étendue

5. **`BackOffice\Orders`** — ajout, en réutilisant le pattern grille de
   `Products` :
   - `filterByReference(string $reference): void`
   - `getOrderReferenceInList(int $row = 1): string`
   - Sélecteurs grille : `filterReferenceInput`, `searchButton`, `listRowReference`
     (valeurs best-effort validées live).

### Scénario

6. **`CheckoutOrder`** (`src/Scenarios/CheckoutOrder.php`) — compose le flux :

```
FO Login (params: customerEmail, customerPassword)
  → addToCart (params: productId, cartQuantity) via Product.addToCart
  → Cart.goToCart() → Cart.proceedToCheckout()
  → Checkout.confirmAddresses() → chooseShipping() → choosePaymentAndConfirm()
  → OrderConfirmation.isConfirmed() ; store('orderReference', getOrderReference())
  → BO Login → Orders.goTo()
  → Orders.filterByReference(retrieve('orderReference'))
  → assert getOrderReferenceInList() contient la référence
```

- Params (défauts validés live) : `customerEmail`, `customerPassword`,
  `productId`, `cartQuantity`.
- `store()/retrieve()` fait passer la **référence de commande** FO → BO.

## Hypothèses à valider en live

- Un **client démo avec une adresse enregistrée** existe ; sinon on en crée un
  une fois (hors scénario) ou on ajuste les params.
- Au moins **un transporteur** et **un paiement hors-ligne** sont activés (défaut
  démo PS : OK).
- Les sélecteurs du tunnel sont la **zone à risque** (multi-étapes, config) ;
  ils seront corrigés au fil du live. Si le tunnel s'avère trop gros, repli
  possible sur un découpage « FO jusqu'à confirmation » puis « vérif BO ».

## Vérification

### Unitaire (browser-free, pattern `BackOfficeProductsTest`)

- Chaque nouvelle page déclare les clés de sélecteurs et méthodes d'action
  attendues (`method_exists`, présence des clés).
- Le scénario `CheckoutOrder` importe les bonnes pages et enchaîne les étapes
  dans l'ordre (vérif structurelle par réflexion si utile).
- `composer test-unit` reste vert.

### Live (le vrai critère — PS 9 locale)

Mécanique : `pkill -9 -i chrome` + `rm datas/.broswer*` entre runs, une suite à
la fois, `.env.local` configuré, `datas/` existe.

- La suite `CheckoutOrder` passe **de bout en bout** : login FO → panier →
  tunnel (adresse/livraison/paiement/CGV) → confirmation (référence non vide) →
  login BO → commande retrouvée par référence. Vert, reproductible.

## Critères d'acceptation

- [ ] Pages `FrontOffice\Login` (login), `Cart` (goToCart/proceedToCheckout),
      `Checkout` (confirmAddresses/chooseShipping/choosePaymentAndConfirm),
      `OrderConfirmation` (isConfirmed/getOrderReference) créées/étoffées, avec
      stubs v7/v8/v9.
- [ ] `BackOffice\Orders` étendue (`filterByReference`,
      `getOrderReferenceInList`).
- [ ] Scénario `CheckoutOrder` + suite d'exécution, params par défaut.
- [ ] `composer test-unit` vert (tests structurels + existants).
- [ ] **Live** : `CheckoutOrder` verte de bout en bout contre la PS 9 locale,
      reproductible.

## Fichiers touchés (prévision)

- Créés : `src/Pages/Common/FrontOffice/Checkout/Page.php` (+ stubs v7/v8/v9) ;
  `src/Pages/Common/FrontOffice/OrderConfirmation/Page.php` (+ stubs) ;
  `src/Scenarios/CheckoutOrder.php` ; `src/Tests/Suites/Scenarios/CheckoutOrder.php` ;
  tests unitaires associés.
- Modifiés : `src/Pages/Common/FrontOffice/Login/Page.php` (+ stubs si besoin) ;
  `src/Pages/Common/FrontOffice/Cart/Page.php` (+ stubs si besoin) ;
  `src/Pages/Common/BackOffice/Orders/Page.php`.
- Inchangés : le reste des pages, le cycle de vie navigateur, le CRUD produit.

## Commits

Approbation explicite requise avant tout `git commit`. Les subagents
implémenteurs ne committent pas ; le coordinateur regroupe après accord.

## Addendum — validation live (2026-07-02) : validé jusqu'au paiement

Reverse-engineering live contre la PS 9 locale (boutique **française**, config
`LOCALE=en` → mismatch d'URLs friendly). Résultats :

**Provisionnement env (fait) :** le client démo John DOE (`pub@prestashop.com`,
id 2) a des adresses. Mot de passe posé en base (bcrypt) :
`UPDATE okd6a_customer SET passwd=<password_hash('PrestaFlow2026!',BCRYPT)> WHERE id_customer=2`.

**Flux validé de bout en bout jusqu'au paiement (sélecteurs confirmés) :**
- Login : page `/connexion` (PAS `/login`), `#login-form input[name=email|password]`,
  `#submit-login`. La session **tient** jusqu'au checkout (`Déconnexion` présent).
- Produit : URL canonique `{id}-{...}-{rewrite}.html` (l'URL depuis l'id seul ne
  résout pas) ; `.add-to-cart` OK ; modale avec lien « Commander » (`a.btn-primary`).
- Panier : `/panier?action=show` ; bouton « Commander » (`a.btn.btn-primary`) → `/commande`.
- Checkout `/commande` (connecté) : étape adresses `#checkout-addresses-step`
  + `[name=confirm-addresses]` ✓ ; livraison `#checkout-delivery-step`
  `input[name^=delivery_option]` (`delivery_option_1/2`) + `[name=confirmDeliveryOption]` ✓ ;
  CGV `#conditions-to-approve input[type=checkbox]` (`conditions_to_approve[terms-and-conditions]`) ✓.

**Correctifs encodés :** `src/Urls/fr.json` (login→connexion, cart→panier?action=show,
order→commande) ; scénario `locale=fr` + param `productUrl` ; `Cart::goToCart()` ;
`FrontOffice\Product::goToProductPath()` ; assertion `equals(true)` (l'ancien
`isTrue()` n'existe pas sur `Expect`).

**Blocage restant (config env, en suivi) :** à l'étape paiement,
`input[name=payment-option]` est **vide** → aucun moyen de paiement ne s'affiche,
le bouton « Commander » reste `disabled`, la commande ne peut pas être passée.
Les 3 modules (`ps_checkpayment`, `ps_wirepayment`, `ps_cashondelivery`) sont
`active=1` en base mais **non configurés** (le virement/chèque se masquent tant
qu'ils n'ont pas leurs coordonnées) → ils n'apparaissent pas dans le tunnel.
**À faire pour le vert final :** configurer un module de paiement hors-ligne
(coordonnées factices) via le BO, puis valider le scénario complet (Chrome frais,
la boutique a beaucoup d'instances Chrome zombies après une longue session).
Les assertions par étape (login OK, panier non vide) restent aussi à ajouter une
fois le tunnel vert.
