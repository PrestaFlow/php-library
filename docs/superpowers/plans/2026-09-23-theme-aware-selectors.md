# Theme-aware selectors Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let PrestaFlow target Classic and Hummingbird (and any third-party theme) by resolving a configured theme name and merging a per-theme selector file over each page's base map.

**Architecture:** The theme travels the same path the locale already does — `PRESTAFLOW_THEME` → `globals['THEME']` → a suite/scenario `params['theme']` override → the page. `CommonPage::getSelectors()` gains one merge tier that loads `<theme>.json`, first from the library's `src/Themes/`, then from the project's `Tests/Themes/`. Classic needs no file and no branch: the loader looks for `classic.json`, finds nothing, and each page's `defineSelectors()` — which already is Classic — applies unchanged.

**Tech Stack:** PHP 8.2+, PHPUnit 10 for unit tests, the `bin/prestaflow` runner for end-to-end suites. Reference container `ps92rc1-web-1` (PrestaShop 9.2.0) on `http://localhost:8092`, whose **shop 1 runs hummingbird and shop 2 runs classic**.

**Spec:** `docs/superpowers/specs/2026-09-23-theme-aware-selectors-design.md`

**On commits:** each task ends with a suggested commit boundary. Per the project's standing rule, **do not run `git commit` without the user's explicit instruction**.

---

## File Structure

| File | Responsibility |
|---|---|
| `src/Tests/TestsSuite.php` (modify) | Add `'THEME'` to the globals, next to `'LOCALE'` |
| `src/Traits/ImportPage.php` (modify) | Let `params['theme']` override the global before the page is built |
| `src/Pages/CommonPage.php` (modify) | `getTheme()` / `setTheme()`, the theme merge tier, and the missing-file warning |
| `src/Themes/hummingbird.json` (create) | The only theme file the library ships |
| `tests/Unit/Pages/ThemeSelectorsTest.php` (create) | Resolution, merge order, Classic-has-no-special-case, warning |
| The 11 FrontOffice page objects (modify) | Audit: Classic form stays in PHP, hummingbird form moves to the JSON |

---

### Task 1: Theme resolution

**Goal:** A configured theme name reaches every page object, overridable per suite or scenario.

**Files:**
- Modify: `src/Tests/TestsSuite.php` (the `$this->globals = [...]` block, around line 796)
- Modify: `src/Traits/ImportPage.php:17-24`
- Modify: `src/Pages/CommonPage.php` (add `getTheme()` / `setTheme()`)
- Test: `tests/Unit/Pages/ThemeSelectorsTest.php`

**Acceptance Criteria:**
- [ ] `PRESTAFLOW_THEME` populates `globals['THEME']`, defaulting to `classic`
- [ ] `$testSuite->params['theme']` overrides the global, the way `params['locale']` already does
- [ ] `CommonPage::getTheme()` returns the resolved theme, `classic` when nothing is set
- [ ] No static state: the theme is read from `$this->globals`

**Why not a `Theme` trait:** `Locale` keeps its state in a static property and is initialised by the page constructor *before* `getSelectors()` runs. A setter called from `importPage()` after construction would arrive too late to affect the selector merge. `$this->globals` is assigned on the constructor's first line, so reading from it has no ordering trap.

**Verify:** `vendor/bin/phpunit --testsuite Unit --filter ThemeSelectors` → `OK (4 tests)`

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Pages/ThemeSelectorsTest.php`:

```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;

/**
 * Test double: bypasses the parent constructor so the selector merge can be
 * exercised without a browser, a locale catalog or a live shop.
 */
final class FakeThemePage extends CommonPage
{
    public array $baseSelectors = [];

    public function __construct(array $globals = [], array $baseSelectors = [])
    {
        $this->globals = $globals;
        $this->baseSelectors = $baseSelectors;
        $this->customs = ['selectors' => []];
    }

    public function defineSelectors()
    {
        return $this->baseSelectors;
    }

    // getPageName() normally derives from the class namespace; pin it so the
    // JSON walk has a predictable path to follow.
    public function getPageName(): string
    {
        return 'FrontOffice\\Product\\Page';
    }
}

final class ThemeSelectorsTest extends TestCase
{
    public function testThemeDefaultsToClassicWhenNothingIsSet(): void
    {
        $page = new FakeThemePage([]);

        $this->assertSame('classic', $page->getTheme());
    }

    public function testThemeComesFromTheGlobals(): void
    {
        $page = new FakeThemePage(['THEME' => 'hummingbird']);

        $this->assertSame('hummingbird', $page->getTheme());
    }

    public function testSetThemeOverridesTheGlobals(): void
    {
        $page = new FakeThemePage(['THEME' => 'hummingbird']);
        $page->setTheme('panda');

        $this->assertSame('panda', $page->getTheme());
    }

    public function testClassicLeavesTheBaseMapUntouched(): void
    {
        $page = new FakeThemePage(['THEME' => 'classic'], ['addToCartButton' => '.add-to-cart']);

        $this->assertSame('.add-to-cart', $page->getSelectors()['addToCartButton']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit --testsuite Unit --filter ThemeSelectors
```

Expected: FAIL — `Call to undefined method ... ::getTheme()`.

- [ ] **Step 3: Add the global**

In `src/Tests/TestsSuite.php`, inside the `$this->globals = [` array, immediately after the `'LOCALE'` line:

```php
            'LOCALE' => Env::get('PRESTAFLOW_LOCALE', 'en'),
            // Selector variants are merged per theme. Classic is the default
            // because it is the library's historical baseline — see
            // CommonPage::getSelectors().
            'THEME' => Env::get('PRESTAFLOW_THEME', 'classic'),
```

- [ ] **Step 4: Let a suite or scenario override it**

In `src/Traits/ImportPage.php`, right after the existing `locale` override block (around line 17-19), add:

```php
        if (isset($this->params['theme']) && is_string($this->params['theme'])) {
            // Mirrors the locale override above. Matters on multistore, where
            // two shops of one installation can run different themes.
            $globals['THEME'] = $this->params['theme'];
        }
```

- [ ] **Step 5: Expose it on the page**

In `src/Pages/CommonPage.php`, next to the other globals accessors (after `getGlobal()`), add:

```php
    /**
     * The theme whose selector variants apply to this page.
     *
     * Read from the globals rather than from a static trait like Locale: page
     * constructors call getSelectors() before importPage() could invoke a
     * setter, so trait-held state would always arrive too late to affect the
     * merge. $this->globals is assigned on the constructor's first line.
     */
    public function getTheme(): string
    {
        $theme = $this->globals['THEME'] ?? '';

        return is_string($theme) && $theme !== '' ? $theme : 'classic';
    }

    public function setTheme(string $theme): void
    {
        $this->globals['THEME'] = $theme;
    }
```

- [ ] **Step 6: Run the test**

```bash
vendor/bin/phpunit --testsuite Unit --filter ThemeSelectors
```

Expected: `OK (4 tests, 4 assertions)`.

- [ ] **Step 7: Run the whole unit suite**

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: green. Baseline before this task is `204 tests, 489 assertions`.

- [ ] **Step 8: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Tests/TestsSuite.php src/Traits/ImportPage.php src/Pages/CommonPage.php tests/Unit/Pages/ThemeSelectorsTest.php
git commit -m "feat(pages): resolve a theme name the way the locale is resolved"
```

---

### Task 2: The theme merge tier

**Goal:** `getSelectors()` merges `<theme>.json` over the base map, project file beating library file, with a warning when a named theme has no file.

**Files:**
- Modify: `src/Pages/CommonPage.php` — `getSelectors()`, currently around lines 566-600
- Test: `tests/Unit/Pages/ThemeSelectorsTest.php` (extend)

**How the existing walk works — read this before writing code.** `getSelectors()` already loads `Tests/Selectors/<locale>.json` and descends into it following `$pageNames = explode('\\', $this->getPageName())`, which for a FrontOffice product page yields `['FrontOffice', 'Product', 'Page']`. It descends one level per segment, skipping `Page`. So the file shape is `{"FrontOffice": {"Product": {"key": "selector"}}}` — the context level is part of the structure, not decoration. The theme tier reuses exactly this walk; do not invent a second shape.

**Acceptance Criteria:**
- [ ] A `<theme>.json` under `src/Themes/` is merged over the base map
- [ ] A `<theme>.json` under `Tests/Themes/` is merged over that, so a project wins over the library
- [ ] Merge order overall: base → library theme → project theme → `Tests/Selectors/<locale>.json` → `customs['selectors']`
- [ ] `classic` needs no file and no branch: nothing special appears in the code for it
- [ ] A named theme with no file anywhere warns once and falls back to the base map

**Verify:** `vendor/bin/phpunit --testsuite Unit --filter ThemeSelectors` → `OK (8 tests)`

**Steps:**

- [ ] **Step 1: Write the failing tests**

Append these four tests to `tests/Unit/Pages/ThemeSelectorsTest.php`, inside the `ThemeSelectorsTest` class. They write real files into a temporary directory and point the page at it, so the walk is exercised for real rather than mocked:

```php
    private string $tmpThemes = '';

    protected function tearDown(): void
    {
        if ($this->tmpThemes !== '' && is_dir($this->tmpThemes)) {
            foreach (glob($this->tmpThemes . '/*.json') as $file) {
                @unlink($file);
            }
            @rmdir($this->tmpThemes);
        }
    }

    private function writeThemeFile(string $theme, array $selectors): string
    {
        if ($this->tmpThemes === '') {
            $this->tmpThemes = sys_get_temp_dir() . '/pf-themes-' . bin2hex(random_bytes(6));
            mkdir($this->tmpThemes, 0777, true);
        }

        file_put_contents(
            $this->tmpThemes . '/' . $theme . '.json',
            json_encode(['FrontOffice' => ['Product' => $selectors]])
        );

        return $this->tmpThemes;
    }

    public function testThemeFileOverridesTheBaseMap(): void
    {
        $dir = $this->writeThemeFile('hummingbird', ['addToCartButton' => '.product__add-to-cart-button']);

        $page = new FakeThemePage(['THEME' => 'hummingbird'], ['addToCartButton' => '.add-to-cart']);
        $page->themeDirs = [$dir];

        $this->assertSame('.product__add-to-cart-button', $page->getSelectors()['addToCartButton']);
    }

    public function testKeysAbsentFromTheThemeFileKeepTheirBaseValue(): void
    {
        $dir = $this->writeThemeFile('hummingbird', ['addToCartButton' => '.product__add-to-cart-button']);

        $page = new FakeThemePage(
            ['THEME' => 'hummingbird'],
            ['addToCartButton' => '.add-to-cart', 'quantityWantedInput' => '#quantity_wanted']
        );
        $page->themeDirs = [$dir];

        $this->assertSame('#quantity_wanted', $page->getSelectors()['quantityWantedInput']);
    }

    public function testTheLastDirectoryWins(): void
    {
        $libraryDir = $this->writeThemeFile('hummingbird', ['addToCartButton' => '.from-library']);

        $projectDir = sys_get_temp_dir() . '/pf-themes-project-' . bin2hex(random_bytes(6));
        mkdir($projectDir, 0777, true);
        file_put_contents(
            $projectDir . '/hummingbird.json',
            json_encode(['FrontOffice' => ['Product' => ['addToCartButton' => '.from-project']]])
        );

        $page = new FakeThemePage(['THEME' => 'hummingbird'], ['addToCartButton' => '.add-to-cart']);
        $page->themeDirs = [$libraryDir, $projectDir];

        $this->assertSame('.from-project', $page->getSelectors()['addToCartButton']);

        @unlink($projectDir . '/hummingbird.json');
        @rmdir($projectDir);
    }

    public function testAMissingThemeFileWarnsAndKeepsTheBaseMap(): void
    {
        $page = new FakeThemePage(['THEME' => 'panda'], ['addToCartButton' => '.add-to-cart']);
        $page->themeDirs = [sys_get_temp_dir()];

        $selectors = $page->getSelectors();

        $this->assertSame('.add-to-cart', $selectors['addToCartButton']);
        $this->assertNotSame('', $page->lastThemeWarning, 'a named theme with no file must say so');
    }
```

Add the two properties the tests reach for to `FakeThemePage`:

```php
    public array $themeDirs = [];
    public string $lastThemeWarning = '';
```

- [ ] **Step 2: Run them to verify they fail**

```bash
vendor/bin/phpunit --testsuite Unit --filter ThemeSelectors
```

Expected: FAIL. Before Step 3 exists, `getThemeSelectors()` is undefined, so the
run errors rather than asserting — that is a valid red. The two properties added
to `FakeThemePage` are redeclared identically on `CommonPage` in Step 3; PHP
allows that, and it keeps this red phase readable.

- [ ] **Step 3: Implement the tier**

In `src/Pages/CommonPage.php`, add these members next to `getTheme()`:

```php
    /** Set by tests to point the theme loader at fixtures. */
    public array $themeDirs = [];

    /** The last missing-theme warning, kept so tests can assert on it. */
    public string $lastThemeWarning = '';

    /**
     * Directories searched for <theme>.json, least specific first.
     *
     * The library's own themes, then the consuming project's — so a project can
     * correct or extend what the library ships without forking it. Mirrors the
     * root that Tests/Selectors/<locale>.json is already read from.
     */
    protected function themeDirectories(): array
    {
        if ($this->themeDirs !== []) {
            return $this->themeDirs;
        }

        return [
            __DIR__ . '/../Themes',
            __DIR__ . '/../../../../../Tests/Themes',
        ];
    }

    /**
     * Selector overrides for the current theme.
     *
     * Classic gets no special case: the loader looks for classic.json like it
     * would for any other theme, finds nothing, and the caller keeps the base
     * map — which already IS Classic. That is what makes "Classic by default" a
     * structural property rather than a branch.
     */
    protected function getThemeSelectors(array $pageNames): array
    {
        $theme = $this->getTheme();
        $selectors = [];
        $found = false;

        foreach ($this->themeDirectories() as $dir) {
            $path = rtrim($dir, '/') . '/' . $theme . '.json';
            if (!file_exists($path)) {
                continue;
            }

            $found = true;
            $decoded = json_decode(file_get_contents($path), true);
            if (!is_array($decoded)) {
                continue;
            }

            // Same walk as the locale catalog: descend one level per page-name
            // segment, skipping the trailing "Page".
            foreach ($pageNames as $pageName) {
                if ($pageName === 'Page') {
                    continue;
                }
                $decoded = is_array($decoded) && isset($decoded[$pageName]) ? $decoded[$pageName] : [];
            }

            if (is_array($decoded)) {
                $selectors = [...$selectors, ...$decoded];
            }
        }

        // Silence here would recreate the failure mode this whole mechanism
        // exists to remove: a suite that looks like it ran and proved nothing.
        if (!$found && $theme !== 'classic') {
            $this->lastThemeWarning = sprintf(
                'No selector file found for theme "%s"; falling back to the Classic base selectors.',
                $theme
            );
        }

        return $selectors;
    }
```

Then wire it into `getSelectors()`. The existing merge at the end of that method reads:

```php
        $mergedSelectors = [
            ...$baseSelectors,
            ...$customSelectors,
            ...$specificSelectors,
        ];
```

Replace it with:

```php
        $mergedSelectors = [
            ...$baseSelectors,
            ...$this->getThemeSelectors($pageNames),
            ...$customSelectors,
            ...$specificSelectors,
        ];
```

- [ ] **Step 4: Run the tests**

```bash
vendor/bin/phpunit --testsuite Unit --filter ThemeSelectors
```

Expected: `OK (8 tests, 9 assertions)`.

- [ ] **Step 5: Surface the warning in a run**

The warning must reach the operator, not just the tests. In `src/Tests/TestsSuite.php`, after the suite's pages are imported — in `importPage()`'s caller is too deep, so do it in `TestsSuite::importPage()` right after `$this->pages[$pageVarName] = $pageInstance;` is set by the trait — add:

```php
        if ($pageInstance->lastThemeWarning !== '') {
            fwrite(STDERR, $pageInstance->lastThemeWarning . PHP_EOL);
        }
```

Put it in `src/Traits/ImportPage.php`, immediately after the line that stores the page:

```php
        $this->pages[$pageVarName] = $pageInstance;

        // One line, once per page: a misconfigured theme must not be silent.
        if ($pageInstance->lastThemeWarning !== '') {
            fwrite(STDERR, $pageInstance->lastThemeWarning . PHP_EOL);
        }
```

- [ ] **Step 6: Run the whole unit suite**

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: green.

- [ ] **Step 7: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Pages/CommonPage.php src/Traits/ImportPage.php tests/Unit/Pages/ThemeSelectorsTest.php
git commit -m "feat(pages): merge per-theme selector overrides over the base map"
```

---

### Task 3: Audit the 63 selectors and move the Hummingbird forms out

**Goal:** Each of the 11 FrontOffice page objects holds the exact Classic selector, and `src/Themes/hummingbird.json` holds the exact Hummingbird one wherever they differ. No union selectors remain.

**Files:**
- Create: `src/Themes/hummingbird.json`
- Modify, as the audit dictates: `src/Pages/v9/FrontOffice/{Cart,Category,Checkout,Home,Listing,Login,OnePageCheckout,OrderConfirmation,PricesDrop,Product,Registration}/Page.php`

**Known divergences to start from** — found while validating the One Page Checkout work, currently expressed as union selectors that this task splits:

| Page | Key | Classic | Hummingbird |
|---|---|---|---|
| `Product` | `addToCartButton` | `.add-to-cart` | `.product__add-to-cart-button` |
| `OrderConfirmation` | `confirmationBlock` | `#content-hook_order_confirmation` | `body#order-confirmation` |
| `OrderConfirmation` | `orderReference` | `#order-reference-value` | `.order-confirmation__details-list li:first-child` |
| `Login` (FO) | `logoutLink` | `#_desktop_user_info .user-info a[href*='mylogout']` | `#signout_link` |

The other 59 selectors are unverified on at least one theme; that is what the audit settles.

**Acceptance Criteria:**
- [ ] Every selector in the 11 pages has been checked against both themes
- [ ] Where a selector differs, the Classic form is in the page and the Hummingbird form is in `src/Themes/hummingbird.json`
- [ ] No selector in those pages is a comma-separated union introduced as a stopgap
- [ ] `src/Themes/hummingbird.json` contains only diverging keys, nested `{"FrontOffice": {"<Page>": {...}}}`

**Verify:** the end-to-end runs in Task 4. There is no unit test that can tell a right selector from a wrong one.

**Steps:**

- [ ] **Step 1: List every selector to audit**

```bash
grep -rn "' =>" src/Pages/v9/FrontOffice --include=Page.php | grep -E "=> *'[#.\[]" | tee /tmp/pf-selectors.txt | wc -l
```

Expected: 63 lines. Work through this list; it is the task's checklist.

- [ ] **Step 2: Check each selector against both themes**

Both themes are live in the reference container: shop 1 (`http://localhost:8092/`) runs hummingbird, shop 2 runs classic. Confirm before starting:

```bash
docker exec ps92rc1-db-1 mariadb -uroot -proot prestashop -e "select id_shop,name,theme_name from ps_shop;"
```

Expected: shop 1 `hummingbird`, shop 2 `classic`.

For a selector on a given page, count matches in the rendered DOM rather than reading templates — today's session proved templates mislead, because the shop may not use the theme you are reading:

```bash
docker exec ps92rc1-web-1 sh -lc 'grep -rn "add-to-cart" /var/www/html/themes/hummingbird/templates/catalog/_partials/product-add-to-cart.tpl'
```

Prefer the DOM: open the page in the browser and run
`document.querySelectorAll('<selector>').length` for each candidate. A count of 0 on a theme means that theme needs its own entry.

- [ ] **Step 3: Create the theme file with the four known divergences**

Create `src/Themes/hummingbird.json`:

```json
{
  "FrontOffice": {
    "Product": {
      "addToCartButton": ".product__add-to-cart-button"
    },
    "OrderConfirmation": {
      "confirmationBlock": "body#order-confirmation",
      "orderReference": ".order-confirmation__details-list li:first-child"
    },
    "Login": {
      "logoutLink": "#signout_link"
    }
  }
}
```

Extend it as the audit finds more.

- [ ] **Step 4: Put the Classic form back in the pages**

Undo each union. In `src/Pages/v9/FrontOffice/Product/Page.php`:

```php
            'addToCartButton' => '.add-to-cart',
```

In `src/Pages/v9/FrontOffice/OrderConfirmation/Page.php`:

```php
            'confirmationBlock' => '#content-hook_order_confirmation',
            'orderReference' => '#order-reference-value',
```

In `src/Pages/v9/FrontOffice/Login/Page.php`:

```php
            'logoutLink' => '#_desktop_user_info .user-info a[href*=\'mylogout\']',
```

Delete the comments that explained the unions — they describe a mechanism that no longer exists — and leave the Classic selectors unannotated, since the file is now unambiguously the Classic map.

- [ ] **Step 5: Check no union survived**

```bash
grep -rn "' =>" src/Pages/v9/FrontOffice --include=Page.php | grep -E "=> *'[^']*, *[.#\[]"
```

Expected: no output. Any hit is a union the audit has not split yet.

Note: `Cart::checkoutButton` is a deliberate exception — `'.cart-detailed-actions a.btn, .checkout a.btn'` targets two places in the *same* theme, not two themes. If the audit confirms both forms exist on both themes, keep it and say so in a comment; otherwise split it like the rest.

- [ ] **Step 6: Run the unit suite**

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: green. Unit tests do not validate selectors, but they catch a syntax error or a broken page class.

- [ ] **Step 7: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Themes/hummingbird.json src/Pages/v9/FrontOffice
git commit -m "refactor(pages): split Classic and Hummingbird selectors, drop the unions"
```

---

### Task 4: Validate against both themes

**Goal:** Prove the audit by running the existing suites against a Hummingbird shop and a Classic shop.

**Files:** none created; fixes land in the files from Task 3.

**Acceptance Criteria:**
- [ ] `OnePageCheckoutGuest` passes against shop 1 with `PRESTAFLOW_THEME=hummingbird`
- [ ] `OnePageCheckoutOrder` passes against shop 1 with `PRESTAFLOW_THEME=hummingbird`
- [ ] `Registration` passes against shop 1 with `PRESTAFLOW_THEME=hummingbird`
- [ ] The same suites pass against shop 2 with `PRESTAFLOW_THEME=classic`
- [ ] A deliberately wrong theme produces the warning and a visible failure, not a silent pass

**Verify:** every run above ends with all tests green, and each order is confirmed in `ps_orders`.

**Steps:**

- [ ] **Step 1: Find shop 2's front-office URL**

Shop 2 exists but has its own domain or path:

```bash
docker exec ps92rc1-db-1 mariadb -uroot -proot prestashop -e "select s.id_shop,s.name,s.theme_name,u.domain,u.physical_uri,u.virtual_uri,u.main from ps_shop s join ps_shop_url u on u.id_shop=s.id_shop;"
```

Use the row for shop 2 to build its front-office URL. If shop 2 has no usable URL, say so and stop — Task 4 cannot be faked, and a second shop can be given a URL in the back office under Shop Parameters.

- [ ] **Step 2: Run the suites against Hummingbird (shop 1)**

```bash
PRESTAFLOW_PS_VERSION=9.2.0 PRESTAFLOW_LOCALE=en PRESTAFLOW_THEME=hummingbird PRESTAFLOW_FO_URL='http://localhost:8092/' PRESTAFLOW_BO_URL='http://localhost:8092/admin259je3iqgg4jvpdzdlw/' PRESTAFLOW_BO_EMAIL='admin@example.com' PRESTAFLOW_BO_PASSWD='<the shop password>' PRESTAFLOW_FO_EMAIL='pf-fixture@example.com' PRESTAFLOW_FO_PASSWD='<the fixture password>' ./bin/prestaflow run src/Tests/Suites/Scenarios -g opc
```

Expected: every test green for both One Page Checkout suites.

- [ ] **Step 3: Run the registration suite against Hummingbird**

```bash
PRESTAFLOW_PS_VERSION=9.2.0 PRESTAFLOW_LOCALE=en PRESTAFLOW_THEME=hummingbird PRESTAFLOW_FO_URL='http://localhost:8092/' PRESTAFLOW_BO_URL='http://localhost:8092/admin259je3iqgg4jvpdzdlw/' ./bin/prestaflow run src/Tests/Suites/Scenarios/Registration.php
```

Expected: `2 passed`.

- [ ] **Step 4: Run the same suites against Classic (shop 2)**

Same commands with `PRESTAFLOW_THEME=classic` and shop 2's front-office URL from Step 1. This is the run that proves the Classic base map is still correct — the audit could otherwise "fix" Hummingbird by breaking Classic.

- [ ] **Step 5: Confirm the orders exist**

```bash
docker exec ps92rc1-db-1 mariadb -uroot -proot prestashop -e "select id_order,reference,id_shop,date_add from ps_orders order by id_order desc limit 6;"
```

Expected: new rows for both shops. A green confirmation page is not on its own proof the order was persisted.

- [ ] **Step 6: Exercise the scenario-level override**

Task 1 wires `params['theme']`, but every run above sets the theme through the
environment, so that path would otherwise ship unexercised. Temporarily add one
line to `src/Scenarios/Registration.php`, just after the existing
`$testSuite->params['locale'] = ...`:

```php
        $testSuite->params['theme'] = 'classic';
```

Run the registration suite against shop 1 (hummingbird) with
`PRESTAFLOW_THEME=hummingbird`. The scenario param must win, so the run picks the
Classic map on a Hummingbird shop and **fails** on the add-to-cart selector —
that failure is the proof the override works. Then remove the line and confirm
the suite passes again.

- [ ] **Step 7: Check the warning fires**

```bash
PRESTAFLOW_PS_VERSION=9.2.0 PRESTAFLOW_LOCALE=en PRESTAFLOW_THEME=panda PRESTAFLOW_FO_URL='http://localhost:8092/' PRESTAFLOW_BO_URL='http://localhost:8092/admin259je3iqgg4jvpdzdlw/' ./bin/prestaflow run src/Tests/Suites/Scenarios/Registration.php 2>&1 | head -20
```

Expected: the line `No selector file found for theme "panda"; falling back to the Classic base selectors.` appears, and the run then fails on Hummingbird markup rather than pretending to pass.

- [ ] **Step 8: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Themes/hummingbird.json src/Pages/v9/FrontOffice
git commit -m "fix(pages): correct selectors the two-theme validation disproved"
```

---

## Task Dependencies

```
Task 1 (resolution) ──> Task 2 (merge tier) ──> Task 3 (audit) ──> Task 4 (validation)
```

Strictly sequential: the audit has nowhere to put a Hummingbird selector until the merge tier exists, and the validation has nothing to prove until the audit is done.

## Out of scope

Auto-detection from asset URLs, the BackOffice, and migrating the base selectors into a `classic.json`.
