# Spec — Checkout invité (guest)

Date : 2026-07-06
Statut : design validé, en attente de relecture utilisateur
Branche cible : `1.2.x`

## Contexte

Le scénario `CheckoutOrder` valide un tunnel de commande **client connecté**
(login → panier → adresse existante → livraison → paiement → confirmation, +
vérif BO), 5/5 live. Le tunnel FO (`Checkout` page) expose `confirmAddresses`
(adresse existante), `chooseShipping`, `choosePaymentAndConfirm`.

Prochain incrément : le **checkout invité** — commander sans compte, en
saisissant email + nom puis une nouvelle adresse à la volée. C'est la variante du
tunnel que le flux connecté sautait (pas d'infos perso, adresse existante).

## Objectif

Fournir un scénario `GuestCheckout` qui commande **en invité pur** (sans création
de compte) : ajout panier → étape infos personnelles (email + nom) → nouvelle
adresse → livraison → paiement → **confirmation FrontOffice** (référence non
vide). Validé live contre la PS 9 locale.

### Hors périmètre (YAGNI)

Création de compte, carnet d'adresses, adresse de facturation ≠ livraison, bons de
réduction, vérification BackOffice (déjà couverte par `CheckoutOrder`).

## Décisions

- **Invité pur** (choix utilisateur) : email + prénom + nom, **sans mot de passe**
  (pas de compte).
- **Vérification = confirmation FO seule** (choix utilisateur) : la partie
  nouvelle est le tunnel invité ; la vérif BO est déjà prouvée par `CheckoutOrder`.
- **Scénario autonome `GuestCheckout`** (pas d'ajout de mode invité à
  `CheckoutOrder`, qui resterait le parcours connecté).
- **Réutilisation** de `chooseShipping` / `choosePaymentAndConfirm` (déjà
  validés) ; on n'ajoute que les deux étapes invité.
- **Sélecteurs best-effort → corrigés en live** (probes chrome-php, une suite à la
  fois, `pkill -9 -i chrome` + `rm datas/.broswer*` entre runs).
- Adresse en **France** (les modules de paiement sont activés pour FR+BE).

## Architecture

### Page `FrontOffice\Checkout` étoffée

`Common/FrontOffice/Checkout/Page.php` (les stubs v7/8/9 existent déjà). Deux
méthodes ajoutées :

- `checkoutAsGuest(string $email, string $firstName, string $lastName): void` —
  à l'étape `#checkout-personal-information-step`, cible le formulaire invité
  (bascule « Commander en tant qu'invité » si présente), remplit email + prénom +
  nom, clique « Continuer ».
- `fillNewAddress(array $address): void` — à l'étape `#checkout-addresses-step`,
  remplit le formulaire de nouvelle adresse (adresse, ville, code postal, pays,
  téléphone) puis « Continuer ».

Sélecteurs best-effort ajoutés (corrigés live) :
- `guestToggle` (bouton/onglet « Commander en tant qu'invité », si présent)
- `personalEmailInput` `#checkout-personal-information-step input[name="email"]`
- `personalFirstNameInput` `... input[name="firstname"]`
- `personalLastNameInput` `... input[name="lastname"]`
- `personalContinueButton` `#checkout-personal-information-step button[type="submit"]`
- `addressStreetInput` `#checkout-addresses-step input[name="address1"]`
- `addressCityInput` `... input[name="city"]`
- `addressPostcodeInput` `... input[name="postcode"]`
- `addressCountrySelect` `... select[name="id_country"]`
- `addressPhoneInput` `... input[name="phone"]`
- (le bouton continuer d'adresse réutilise `addressesContinueButton` existant,
  `[name="confirm-addresses"]`)

### Scénario `GuestCheckout`

`src/Scenarios/GuestCheckout.php`. Params (défauts validés live) : `locale=fr`,
`productUrl`, `cartQuantity`, `guestEmail`, `firstName`, `lastName`,
`addressStreet`, `addressCity`, `addressPostcode`, `addressCountry` (nom du pays
pour le select, ex. « France »), `addressPhone`.

```
propage locale à la suite (comme CheckoutOrder)
addToCart (Product.goToProductPath + addToCart)
  → Cart.goToCart() → Cart.proceedToCheckout()
  → Checkout.checkoutAsGuest(guestEmail, firstName, lastName)
  → Checkout.fillNewAddress([street, city, postcode, country, phone])
  → Checkout.chooseShipping() → Checkout.choosePaymentAndConfirm()
  → assert OrderConfirmation.isConfirmed() == true
```

### Suite `GuestCheckout`

`src/Tests/Suites/Scenarios/GuestCheckout.php` — lance le scénario.

## Hypothèses à valider en live

- L'étape infos perso propose un mode **invité** (email + nom sans mot de passe) ;
  le bouton exact et l'éventuel basculement invité/connexion sont corrigés live.
- Le formulaire d'adresse invité expose les champs ci-dessus ; le pays est un
  select (France), le téléphone possiblement requis.
- **Les deux formulaires invité sont la zone à risque** (multi-champs) ; si trop
  fragiles, repli possible : livrer infos-perso d'abord, adresse ensuite.

## Vérification

### Unitaire (browser-free, pattern `FrontOfficeCheckoutTest`)

- La page `Checkout` (v9) déclare les nouveaux sélecteurs (personal-info +
  adresse) et a les méthodes `checkoutAsGuest`, `fillNewAddress` (en plus des
  existantes).
- Le scénario `GuestCheckout` étend `Scenario` et déclare les params invité ;
  la suite `GuestCheckout` étend `TestsSuite`.
- `composer test-unit` reste vert.

### Live (le vrai critère — PS 9 locale)

- La suite `GuestCheckout` passe : panier → infos perso invité → nouvelle adresse
  → livraison → paiement → CGV → **confirmation FO** (référence non vide). Vert,
  reproductible.

## Critères d'acceptation

- [ ] `Checkout` étoffée : `checkoutAsGuest`, `fillNewAddress` + sélecteurs
      personal-info/adresse.
- [ ] Scénario `GuestCheckout` + suite, params invité + adresse.
- [ ] `composer test-unit` vert (structurels + existants).
- [ ] **Live** : `GuestCheckout` verte de bout en bout (confirmation FO),
      reproductible.

## Fichiers touchés (prévision)

- Modifiés : `src/Pages/Common/FrontOffice/Checkout/Page.php`.
- Créés : `src/Scenarios/GuestCheckout.php` ;
  `src/Tests/Suites/Scenarios/GuestCheckout.php` ; tests unitaires associés.
- Inchangés : `CheckoutOrder`, les autres pages, le cycle de vie navigateur.

## Commits

Approbation explicite requise avant tout `git commit`. Les subagents
implémenteurs ne committent pas ; le coordinateur regroupe après accord.

## Addendum — VERT complet (2026-07-06)

`GuestCheckout` passe **4/4 en live, reproductible** : panier → infos perso invité
→ nouvelle adresse → livraison → paiement → **confirmation FO**. Deux correctifs
live sur `checkoutAsGuest`/`fillNewAddress` :
1. **Cases de consentement requises** : l'étape infos perso a deux cases
   **required** (`psgdpr` « J'accepte les CGV… » et `customer_privacy`) qui
   bloquent « Continuer ». `checkoutAsGuest` coche désormais toutes les
   `#checkout-personal-information-step input[type=checkbox][required]` en JS
   avant de continuer. (Le formulaire invité est l'onglet actif par défaut, le
   `guestToggle` est donc un no-op sans dommage ; le titre M./Mme est optionnel.)
2. **Pays via JS** : `selectValue`/`selectOption` de la lib ne gère qu'un
   sélecteur `#id`/`.class` simple (il transforme le CSS en un faux XPath), pas un
   sélecteur composé comme `#checkout-addresses-step select[name="id_country"]`.
   `fillNewAddress` sélectionne donc le pays par libellé en JS (`option.text` →
   `value` + `change`). France est de toute façon pré-sélectionné.
Les champs nom/prénom/email/adresse/CP/ville/téléphone passent bien via `setValue`.
