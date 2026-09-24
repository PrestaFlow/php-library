# Theme-aware selectors (Classic / Hummingbird) — Design

Date: 2026-09-23
Status: approved, pending implementation plan

## Context

PrestaFlow's FrontOffice page objects were written against PrestaShop's Classic
theme, and nothing says so. PrestaShop 9 ships **hummingbird** as its default
theme, so any suite run against a default 9.x shop hits selectors that do not
exist there.

This is not hypothetical. Validating the One Page Checkout work against a live
9.2.0 shop found four such defects in the existing library, three of them
failing silently:

| Selector | Problem |
|---|---|
| `Product::addToCartButton` = `.add-to-cart` | Renamed `.product__add-to-cart-button` in hummingbird |
| `OrderConfirmation::confirmationBlock` / `orderReference` | Classic-only ids |
| `Login` (FO) `logoutLink` | Classic header markup; hummingbird uses `#signout_link` |
| `Cart::hasItems` (new) | Matched the header cart preview from any page |

They were patched with union selectors (`'.add-to-cart, .product__add-to-cart-button'`)
to unblock that work. A union is a stopgap, not a strategy: it does not say
which theme it targets, the first match wins arbitrarily, and — worst — a
selector broken on *both* themes is indistinguishable from a correct one.

**Scale of the real problem:** 33 directories exist under
`src/Pages/v9/FrontOffice`, but only **11 declare selectors** — the rest are
empty shells. Those 11 pages hold **63 selectors** between them, and they are
exactly the pages the existing suites and scenarios import. The audit is a day's
work, not a rewrite.

## Decisions

| Question | Decision |
|---|---|
| Scope | Mechanism **and** a full audit of the 63 selectors against both themes |
| Where variants live | One mechanism: a JSON file per theme. No PHP per theme |
| Theme resolution | Explicit configuration only — no auto-detection |
| Default | Classic, structurally rather than by rule |

**Why JSON and not a `defineThemeSelectors()` method.** The criterion is not our
own readability, it is the cost for someone *using* the library. With variants in
PHP, supporting a third-party theme (Panda, classic-rocket) means forking the
library or subclassing eleven page classes. With a file, it means dropping in a
`.json` and nothing else.

**Why no auto-detection.** The theme is readable from any FrontOffice page's
asset URLs (`/themes/hummingbird/assets/...`), so detection is technically cheap.
It is left out anyway: selectors are resolved in `importPage()`, before any
navigation has happened, so detection would have to run late and retrofit
already-built pages. Configuration is explicit, predictable, and reproducible —
and a wrong value can be forced deliberately to reproduce a bug.

## Component: theme resolution

The theme follows the exact path the **locale** already takes, so no new concept
is introduced:

```
PRESTAFLOW_LOCALE  ->  globals['LOCALE']  ->  params['locale']  ->  page
PRESTAFLOW_THEME   ->  globals['THEME']   ->  params['theme']   ->  page
```

- `TestsSuite::loadGlobals()` adds `'THEME' => Env::get('PRESTAFLOW_THEME', 'classic')`,
  next to the existing `'LOCALE'` entry.
- `ImportPage::importPage()` lets `$this->params['theme']` override the global
  before building the page, mirroring what it already does for `locale`.
- The page reads it from `$this->globals['THEME']`, and exposes `getTheme()` /
  `setTheme()` over that. Deliberately NOT a static `Theme` trait mirroring
  `Locale`: the page constructors call `getSelectors()` *before* `importPage()`
  could call a setter, so a trait-based theme would always arrive too late.
  `$this->globals` is assigned on the constructor's first line, which sidesteps
  the ordering trap entirely.
- Theme files resolve from the same two roots the existing selector overrides
  use: the library's own directory for `src/Themes/`, and the consuming
  project's `Tests/` directory for `Tests/Themes/` — the same path
  `getSelectors()` already walks for `Tests/Selectors/<locale>.json`.

This also answers **multistore**, where two shops of one installation can run
different themes: a scenario targeting the Classic shop sets
`$testSuite->params['theme'] = 'classic'`, exactly as the current scenarios set
`$testSuite->params['locale']`.

## Component: selector merge

`CommonPage::getSelectors()` gains one tier, inserted after the base map:

```
defineSelectors()                 <- base map, which IS Classic
  <- src/Themes/<theme>.json      <- shipped by the library
  <- Tests/Themes/<theme>.json    <- supplied by the project, wins over the above
  <- Tests/Selectors/<locale>.json  <- existing tier, unchanged
  <- customs['selectors']           <- existing tier, unchanged
```

Classic gets no special case anywhere in the code. The loader looks for
`classic.json` like it would for any theme, does not find one, and the base map
applies unchanged. That is what makes "Classic by default" a structural property
rather than a conditional branch — the fallback code does not exist.

The library ships `src/Themes/hummingbird.json` and **no** `classic.json`: the
base maps already are Classic, and duplicating them would create two sources of
truth. A project that needs to adjust Classic itself — a child theme, a local
override — may still drop in `Tests/Themes/classic.json`, which works with no
special handling.

## Component: theme file format

The same page-keyed nesting as the existing `Tests/Selectors/<locale>.json`, so
no second format is invented. Only diverging keys appear:

```json
{
  "FrontOffice": {
    "Product": {
      "addToCartButton": ".product__add-to-cart-button"
    },
    "OrderConfirmation": {
      "confirmationBlock": "body#order-confirmation"
    }
  }
}
```

The context level (`FrontOffice`) is part of the shape, not decoration:
`getSelectors()` walks the JSON following `getPageName()`, which yields
`FrontOffice\Product\Page`, descending one level per segment except `Page`.

## Loud fallback

When `PRESTAFLOW_THEME` names a theme with no matching file, the run prints one
line saying so and continues on the Classic base. A silent fallback would
reproduce exactly the failure mode this work exists to remove: a suite that
looks like it ran and proved nothing.

## The audit

Every one of the 63 selectors is checked against both themes and, where they
differ, the Classic form stays in `defineSelectors()` while the hummingbird form
moves to `src/Themes/hummingbird.json`. The union selectors introduced as
stopgaps are undone in the process, so each theme gets its exact selector back.

The reference container already hosts both: **shop 1 runs hummingbird, shop 2
runs classic**, so verification costs a suite run per theme rather than a second
environment.

The 11 pages: Cart, Category, Checkout, Home, Listing, Login, OnePageCheckout,
OrderConfirmation, PricesDrop, Product, Registration.

## Testing

- Unit tests, no browser: theme resolution precedence (env, then suite/scenario
  param), the merge order including a project file beating a library file, the
  absence of a Classic special case, and the warning on a missing theme file.
- End to end: the existing suites run against shop 1 (hummingbird) and shop 2
  (classic). That is the only result that settles whether the audit is right.

## Out of scope

- Auto-detection of the theme from asset URLs.
- The BackOffice, which has a single theme.
- Migrating the base selectors out of PHP into a `classic.json`. The mechanism
  allows it; doing it now would move 63 selectors across 11 classes, disturb the
  v7/v8 delegates and the `parent::defineSelectors()` inheritance `Product`
  relies on, for no functional gain — while the point of this cycle is for the
  audit to be trustworthy.
