# One Page Checkout (PrestaShop 9.2) — Design

Date: 2026-09-23
Status: approved, pending implementation plan

## Context

PrestaShop 9.2 ships a new **One Page Checkout** (OPC) delivered as a module,
`ps_onepagecheckout`. PrestaFlow has no page object for it: the existing
`FrontOffice\Checkout` page targets the historical 5-step tunnel, whose DOM the
OPC replaces entirely.

Findings from the live 9.2.0 shop (docker container `ps92rc1-web-1`, port 8092,
module installed and active):

- **The URL does not change.** OPC still runs on the `order` controller. The
  module takes over through `actionCheckoutBuildProcess` and
  `displayOverrideTemplate`. There is no new front route.
- **OPC is off by default.** `PS_ONE_PAGE_CHECKOUT_ENABLED` defaults to `0`.
  An active module is not enough: the layout must be switched in the module's
  back-office configuration (`AdminPsOnePageCheckout`).
- **The DOM is entirely new**: `#opc-form`, sections `.js-opc-contact-section`,
  `.js-opc-addresses-section`, `.js-opc-delivery-section`,
  `.js-opc-payment-section`; addresses as radio cards `.js-opc-address-radio`
  plus modals `#modal-delivery` / `#modal-invoice`; carriers under
  `#opc-delivery-methods` with ids `opc_delivery_option_<id>`; payments under
  `#opc-payment-methods`; terms in `#conditions-to-approve`; final button
  `#opc-pay-button`, rendered `disabled`.
- **Heavy AJAX.** The module ships ~18 JSON front controllers (`carriers`,
  `paymentmethods`, `selectaddress`, `saveaddress`, `carttotals`, `opcsubmit`,
  …). Sections refresh without a page reload, so `waitForPageReload()` is the
  wrong tool for most OPC interactions.
- **Guest checkout is a separate setting.** `PS_GUEST_CHECKOUT_ENABLED` lives in
  Shop Parameters > Order Settings (`#configuration_general_form`, field
  `order_preferences_general[enable_guest_checkout]`), not in the module config.

## Scope

1. A front-office page object for the OPC.
2. Two back-office page objects to drive the shop into the required state.
3. Two scenarios (logged-in customer and guest) with their test suites.
4. Unit tests following the existing convention.

## Decisions

| Question | Decision |
|---|---|
| Version gating | Guard in the scenario (skip below 9.2) **and** an availability check on the page |
| Checkout paths covered | Both logged-in customer and guest |
| Configuration teardown | None — each scenario sets the state it needs, so execution order does not matter |

## Files

```
src/Pages/v9/FrontOffice/OnePageCheckout/Page.php     (new)
src/Pages/v9/BackOffice/CheckoutLayout/Page.php       (new — module configuration)
src/Pages/v9/BackOffice/OrderSettings/Page.php        (new — guest checkout switch)
src/Scenarios/OnePageCheckoutOrder.php                (new — logged-in path)
src/Scenarios/OnePageCheckoutGuest.php                (new — guest path)
src/Tests/Suites/Scenarios/OnePageCheckoutOrder.php   (new)
src/Tests/Suites/Scenarios/OnePageCheckoutGuest.php   (new)
tests/Unit/Scenarios/OnePageCheckoutOrderTest.php     (new)
tests/Unit/Scenarios/OnePageCheckoutGuestTest.php     (new)
src/Pages/CommonPage.php                              (modified — waitForCondition helper)
```

**No v7/v8 delegates.** The existing delegate convention exists for pages that
span versions; the OPC does not exist before 9.2, so a delegate would be false.
`FrontOffice\Checkout\Page` is left untouched — the 5-step tunnel remains the
default behaviour.

## Component: FrontOffice\OnePageCheckout\Page

`url = 'order'` (same controller as the classic tunnel), `pageTitle = 'Checkout'`.

Selectors, taken from the module templates:

| Role | Selector |
|---|---|
| OPC root | `#opc-form` |
| guest email | `#field-email` |
| address card | `.opc-address-item[data-id-address="%s"]` |
| address radio | `.js-opc-address-radio` |
| inline address form | `#opc-delivery-address-fields` |
| address modals | `#modal-delivery`, `#modal-invoice` |
| carriers container | `#opc-delivery-methods` |
| carrier radio | `#opc_delivery_option_%s`, `input[name="delivery_option"]` |
| payments container | `#opc-payment-methods` |
| payment radio | `input[name="payment-option"]` |
| terms | `#conditions-to-approve input[type="checkbox"]` |
| pay button | `#opc-pay-button` |

Public API:

```php
isOnePageCheckoutActive(): bool          // presence of #opc-form
continueAsGuest(string $email): void
selectAddress(int $idAddress): void
fillNewAddress(array $address): void
selectFirstCarrier(): void
selectFirstPayment(): void
acceptTerms(): void
placeOrder(): void
```

`isOnePageCheckoutActive()` serves two purposes: asserting that the back-office
switch took effect, and letting a caller branch without catching a selector
error.

Each method that triggers an AJAX refresh waits on the resulting state rather
than on a fixed delay:

- `selectAddress` / `fillNewAddress` wait for the carrier list to render inside
  `#opc-delivery-methods`.
- `selectFirstCarrier` waits for the payment list to render inside
  `#opc-payment-methods`.
- `acceptTerms` waits for `#opc-pay-button` to lose its `disabled` attribute.
- `placeOrder` clicks the button and then waits for the navigation to the order
  confirmation page.

## Component: CommonPage::waitForCondition

The existing helpers cover presence (`elementIsVisible`, backed by
`waitUntilContainsElement`) and navigation (`waitForPageReload`), but not
*state*. The OPC needs "the pay button is no longer disabled" and "the carrier
list has been rendered".

```php
waitForCondition(string $jsExpression, int $timeout = 10000, int $interval = 200): bool
```

Polls `evaluate()` until the expression returns `true` or the timeout expires;
returns whether the condition was met, so callers decide between asserting and
branching. Roughly twenty lines, reusable well beyond the OPC, and it keeps
`sleep()` calls out of page objects.

## Component: BackOffice\CheckoutLayout\Page

Targets the module's legacy admin controller, reached with
`goToPage('AdminPsOnePageCheckout')`.

```php
switchToOnePageCheckout(): void
switchToFourPageCheckout(): void
```

Both follow the same sequence: select the radio
(`#PS_ONE_PAGE_CHECKOUT_ENABLED_one_page` or `_four_page`), click
`#psopc-save-btn`, then confirm in the modal `#psopc-confirmation-modal` via
`#psopc-confirm-btn`. The confirmation modal is mandatory — the form is not
submitted without it. The modal's maintenance-mode checkbox
(`#psopc-maintenance-toggle`) is left untouched, so the shop stays reachable for
the front-office steps that follow.

## Component: BackOffice\OrderSettings\Page

```php
setGuestCheckout(bool $enabled): void
```

Drives the switch `order_preferences_general[enable_guest_checkout]` inside
`#configuration_general_form` and saves. Switch markup renders as a pair of
radio inputs; their exact ids are confirmed against the live shop during
implementation rather than guessed here.

## Scenarios

Both share the same skeleton:

1. Back-office login.
2. Switch the shop to the one-page layout (`CheckoutLayout`).
3. *Guest scenario only:* enable guest checkout (`OrderSettings`).
4. *Logged-in scenario only:* front-office login.
5. Add a product to the cart.
6. Go through the OPC.
7. Assert the order confirmation page.

Neither scenario restores the configuration it changed. Each one sets the state
it requires up front, so scenarios stay independent of execution order.

Parameters follow the existing scenarios: `locale`, `productUrl`,
`cartQuantity`, customer credentials for the logged-in path, guest identity
fields for the guest path.

### Version guard

At the top of `steps()`, both scenarios compare `getMinorVersion()` against
`9.2` and skip with an explicit message ("One Page Checkout requires PrestaShop
9.2+") when the shop is older. Without this, a run against the 9.0 container in
the repository's `docker-compose.yml` fails on a missing selector, which says
nothing about the real cause.

## Testing

- Unit tests mirror `tests/Unit/Scenarios/GuestCheckoutTest.php`: class
  existence and scenario structure, no browser.
- End-to-end validation runs both suites against the live 9.2.0 shop on
  `localhost:8092` before the work is handed back.

## What the live validation changed

The design above was written from reading the module's templates. Running it
against the real 9.2.0 shop invalidated several assumptions, all recorded here
so the document stays trustworthy.

**Corrections to this spec:**

- `PS_ONE_PAGE_CHECKOUT_ENABLED` was already `1` on the reference shop, not
  unset. An earlier probe searched for `%ONEPAGE%`, which does not match the
  underscored key. The scenarios set the state they need regardless, so nothing
  in the design depends on the starting value.
- `continueAsGuest()` takes more than an e-mail. The module's guest-init is
  gated on the **required consent checkboxes of the contact section**
  (`customer_privacy`, `psgdpr`): until they are ticked, no guest customer is
  created, no address is persisted, and the delivery and payment sections stay
  on "Please accept the required terms above". The method now ticks every
  required — and only required — checkbox there. The optional `optin` and
  `newsletter` boxes are deliberately left alone.
- Order Settings has no `AdminOrderPreferences` sidebar entry in 9.2. The item
  that opens it is `AdminParentOrderPreferences`.
- The module configuration renders nothing outside a **single shop context**.
  The reference shop has multistore enabled, so `CheckoutLayout::goTo()` sets
  the context first, through a new reusable `BackOfficePage::setSingleShopContext()`.
- Every scenario step asserts its own effect. Steps that only click reported
  success while doing nothing, because `click()` returns `false` on a missing
  selector instead of raising — a run against a shop with wrong back-office
  credentials showed four passing steps before the first real assertion failed.
  A unit test now fails if any step lacks an assertion.

**Pre-existing library defects this work uncovered** (not caused by the OPC, but
fixed here because they blocked it):

| Selector / behaviour | Problem |
|---|---|
| `Product::addToCartButton` = `.add-to-cart` | Renamed to `.product__add-to-cart-button` in the 9.2 theme |
| `OrderConfirmation::confirmationBlock` / `orderReference` | Classic-theme ids; the shop runs **hummingbird**, PS 9's default |
| `Login` (FO) `logoutLink` | Classic header markup; hummingbird exposes `#signout_link` |
| `Login` (BO) `headerEmployeeContainer` | 9.0-era id, absent from 9.2 |
| `OrderConfirmation::isConfirmed()` | Relied on `isVisible()`'s 1 second default, too tight after an async redirect |

The theme-related entries mean any front-office suite was already failing on a
hummingbird shop. They are patched here with union selectors covering both
themes; a proper theme-aware selector layer deserves its own spec.

**Validated:** the guest scenario passes end to end against the 9.2.0 shop,
with the resulting order confirmed in `ps_orders`. The logged-in scenario is
still unvalidated past the front-office login: the demo customer's password is
unknown on that shop. Consequently the widened `logoutLink` selector is derived
from the theme templates but **not yet exercised**.

## Out of scope

- Express checkout / wallet buttons (`displayExpressCheckout` hook).
- Billing address distinct from the delivery address
  (`#opc-use-same-address` unchecked), gift wrapping, delivery messages.
- Address editing and deletion from the card dropdown.
- Virtual carts, which skip the delivery section entirely.
- Multistore.
