# Scénarios de commande sur Classic : design

Date : 2026-11-22
Statut : implémenté (branche fix/checkout-scenarios-classic)

## Contexte

Trois défauts constatés le 21 novembre 2026 sur une boutique PrestaShop 9.2.1,
thème Classic, une seule langue (fr), avec `GuestCheckout` et
`OnePageCheckoutGuest`.

## 1. La commande en une page ne doit jamais casser le tunnel

Le module `ps_onepagecheckout` n'a de gabarit que pour les thèmes Bootstrap 5.
Sur Classic, une fois la boutique basculée, `/commande` ne rend plus que
« Hello world! This is HTML5 Boilerplate. » et la boutique reste ainsi, puisque
les scénarios One Page Checkout ne restaurent pas la mise en page.

La garde sur le thème (`OnePageCheckout\Page::requireCompatibleTheme()`, nom du
dossier lu dans les feuilles de style du front) existe déjà sur `dev` et saute
la suite sur Classic avant toute bascule : le run constaté utilisait une
version antérieure de la librairie. Elle ne reconnaît pas pour autant un thème
enfant de Classic, ni un thème dont les feuilles ne pointent pas dans
`themes/<nom>/assets/`.

Changement, en plus de la garde :
- `OnePageCheckout\Page::requireRenderedOrRestore()` juge sur ce que voit le
  client : à l'étape « reach the one page checkout », si `#opc-form` n'est pas
  affiché, la boutique est remise en quatre étapes aussitôt (et cela est
  vérifié), puis la suite est sautée avec `ONE_PAGE_CHECKOUT_UNAVAILABLE`. Si la
  remise en quatre étapes échoue, l'étape échoue franchement ;
- dans `OnePageCheckoutGuest` et `OnePageCheckoutOrder`, l'ajout au panier
  passe avant la bascule : un saut « aucun produit commandable » ne peut plus
  arriver entre la bascule et la vérification du front.

## 2. Formulaire invité rempli trop tôt

Sur un Chrome Linux (conteneur, Cloudflare Browser Run), le formulaire
« Informations personnelles » partait parfois entièrement vide.
`Cart::proceedToCheckout()` rendait la main après au plus 10 s
(`waitForPageReload()`), page de commande chargée ou non, et
`Checkout::checkoutAsGuest()` tapait au clavier sans rien vérifier.

Changement :
- `proceedToCheckout()` attend la page de commande (`body#checkout`, les deux
  tunnels) ; si le clic n'a pas navigué, il suit le lien du bouton ;
- `checkoutAsGuest()` attend le formulaire (échec explicite sinon), active
  l'onglet invité, remplit puis relit les valeurs, ressaisit en JS ce qui n'a
  pas tenu (`FrontOfficePage::pfFillInputsVerified()`), coche les
  consentements obligatoires en le vérifiant, puis attend l'étape des adresses ;
- `fillNewAddress()` attend le formulaire d'adresse et vérifie sa saisie de la
  même façon. Un champ absent de la page (téléphone masqué) n'est pas exigé.

## 3. Aucun moyen de paiement : sauté, pas en échec

Sans module de paiement, l'étape de paiement n'affiche aucun
`input[name="payment-option"]` et `hasReachedPaymentStep()` répondait `false`.

Changement : `hasReachedPaymentStep()` répond aussi `true` quand l'étape de
paiement est l'étape courante (`#checkout-payment-step.js-current-step`,
`.-current`). `choosePayment()` saute alors la suite avec
`pfNoPaymentMethodReason()`. `GuestCheckout` et `CheckoutOrder` en profitent ;
les scénarios One Page Checkout sautaient déjà (`selectPayment()`).

## Tests

- `tests/Unit/Pages/CheckoutRobustnessTest.php` : comparaison des valeurs
  saisies, étape de paiement sans option, saut avec la raison prévue ;
- `tests/Unit/Scenarios/OnePageCheckoutRenderGuardTest.php` : remise en quatre
  étapes puis saut, échec franc si la remise échoue, ordre des étapes.
