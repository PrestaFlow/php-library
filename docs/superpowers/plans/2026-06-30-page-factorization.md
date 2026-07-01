# Page Factorization (v7/v8/v9 → Common + stubs) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move every page's canonical content into a versionless `src/Pages/Common/` tree and reduce each `src/Pages/v{7,8,9}/…/Page.php` to a thin stub, so shared content lives once while every per-version entry point still resolves.

**Architecture:** `importPage()` builds `Pages\v{N}\<name>\Page` from the detected PrestaShop version and instantiates it directly, so each version's class must exist. We keep that contract: canonical content goes to `Pages\Common\<area>\<name>\Page`; each `Pages\vN\…\Page` becomes `class Page extends <Common page> {}` (a pure stub), or a small override when that version genuinely differs. A browser-free, data-driven PHPUnit test enumerates the Common tree and asserts every version resolves and the known deltas are preserved.

**Tech Stack:** PHP 8.1+, PSR-4 autoload (`PrestaFlow\Library\` → `src/`, unchanged), PHPUnit 10.

Spec: `docs/superpowers/specs/2026-06-30-page-factorization-design.md`

## Authoritative delta matrix (computed from the current tree)

- **v7 content == v8 content** for every page (only the namespace/`use` version string differs).
- **v9 real deltas (need a v9 override stub):**
  - `FrontOffice/PricesDrop` — `$url='promotions'`, `$pageTitle='Promotions'` (v7/v8: `prices-drop` / `Prices drop`).
  - `BackOffice/Dashboard` — `$pageTitle='Tableau de bord'` (v7/v8: `Dashboard`).
  - `BackOffice/Login` — extra selectors + an overridden method (see Task 2 for exact code).
- `FrontOffice/Category` — cosmetic only (same `{index}-category`): single Common page, pure stubs everywhere.
- `BackOffice/Customer` — exists only in v9: Common from v9, pure v9 stub, `@unverified` v7/v8 stubs.
- **Canonical source = the v7 file** (v7==v8), except `Customer` (canonical = v9, its only source).

FrontOffice leaves (27): Account, Address, Addresses, BestSellers, Brand, Brands, CMS, Cart, Category, Contact, Content, CreditSlip, GuestTracking, Home, Identity, Information, Listing, Login, Manufacturer, Manufacturers, NewProducts, OrderHistory, PricesDrop, Product, Registration, Sitemap, Stores.
BackOffice leaves: Login, Dashboard, Customer.

## File templates (used throughout)

**A. Common base** (`Common/FrontOffice/Page.php`):
```php
<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice;

use PrestaFlow\Library\Pages\FrontOfficePage;

class Page extends FrontOfficePage
{
}
```
(BackOffice base is identical with `BackOffice` / `BackOfficePage`.)

**B. Canonical leaf** = the current v7 leaf file, with two edits only:
- namespace `…\v7\<area>\<Name>` → `…\Common\<area>\<Name>`
- **parent import**: rewrite the `use … as <Alias>;` line to point at the **Common equivalent of the SAME parent** the leaf already had — NOT blindly to `Common\<area>\Page`. Most leaves extend the area base (`…\v7\<area>\Page`) → `…\Common\<area>\Page`. But some extend a **sibling leaf** (see the parent map below) → e.g. `…\v7\FrontOffice\Listing\Page` → `…\Common\FrontOffice\Listing\Page`.
(The class body — `defineSelectors()`, `$url`, `$pageTitle`, methods — is moved verbatim.)

**Leaf parent map (parents that are NOT the area base — consistent across v7/v8/v9):**
| Leaf | Extends (original) | Common parent to use |
|------|--------------------|----------------------|
| `FrontOffice\Address` | `FrontOffice\Addresses\Page` | `Common\FrontOffice\Addresses\Page` |
| `FrontOffice\Category` | `FrontOffice\Listing\Page` | `Common\FrontOffice\Listing\Page` |
| `FrontOffice\Content` | `FrontOffice\CMS\Page` | `Common\FrontOffice\CMS\Page` |
| `FrontOffice\Information` | `FrontOffice\Identity\Page` | `Common\FrontOffice\Identity\Page` |
| `FrontOffice\PricesDrop` | `FrontOffice\Listing\Page` | `Common\FrontOffice\Listing\Page` |
| `FrontOffice\BestSellers` | `FrontOffice\Listing\Page` | `Common\FrontOffice\Listing\Page` (alias `ListingPage`) |
| `FrontOffice\NewProducts` | `FrontOffice\Listing\Page` | `Common\FrontOffice\Listing\Page` (alias `ListingPage`) |

**Always open each original leaf and preserve its actual `use … as <Alias>` / `extends <Alias>`** — only swap the namespace segment to `Common`. The map above is the known set, but the file is the source of truth.

All other leaves extend their area base `Page`. A Common child may reference a Common parent that lives in another file — the PSR-4 autoloader resolves it regardless of migration order, but the parent file MUST also be migrated (so it isn't left as a now-namespaced stub mismatch). Pure stubs (Template C) always extend the **Common leaf of the same name**, so the parent chain only matters inside `Common/`.

**C. Pure stub** (`v{N}/<area>/<Name>/Page.php`):
```php
<?php

namespace PrestaFlow\Library\Pages\v7\FrontOffice\Cart;

use PrestaFlow\Library\Pages\Common\FrontOffice\Cart\Page as BasePage;

class Page extends BasePage
{
}
```
(Swap `v7`→`v8`/`v9` and `FrontOffice\Cart` for the real area/name.)

**D. vN base stub** (`v{N}/<area>/Page.php`):
```php
<?php

namespace PrestaFlow\Library\Pages\v7\FrontOffice;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
}
```

---

### Task 0: Verification harness + Common bases + pattern proof

**Goal:** Stand up the data-driven test and the Common base classes, and migrate two representative pages (one identical, one v9-delta) end-to-end so every template is exercised and gated.

**Files:**
- Create: `src/Pages/Common/FrontOffice/Page.php`, `src/Pages/Common/BackOffice/Page.php`
- Create: `src/Pages/Common/FrontOffice/Cart/Page.php`, `src/Pages/Common/FrontOffice/PricesDrop/Page.php`
- Modify→stub: `src/Pages/v{7,8,9}/FrontOffice/Cart/Page.php`, `src/Pages/v{7,8,9}/FrontOffice/PricesDrop/Page.php`
- Test: `tests/Unit/Pages/PageFactorizationTest.php`

**Acceptance Criteria:**
- [ ] `class_exists` is true for Cart and PricesDrop in v7/v8/v9.
- [ ] v9 PricesDrop resolves `url='promotions'`/`pageTitle='Promotions'`; v7/v8 resolve `prices-drop`/`Prices drop`.
- [ ] Cart exposes identical `selectors` across v7/v8/v9.
- [ ] `composer test-unit` green (new test + 9 existing 1A tests).

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/PageFactorizationTest.php` → green

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Pages/PageFactorizationTest.php`:

```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class PageFactorizationTest extends TestCase
{
    private const NS = 'PrestaFlow\\Library\\Pages\\';
    private const VERSIONS = ['v7', 'v8', 'v9'];

    private function fakeGlobals(): array
    {
        return [
            'PS_VERSION' => '9.0.0',
            'LOCALE' => 'en',
            'PREFIX_LOCALE' => false,
            'BO' => ['URL' => 'http://localhost/admin/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'DEBUG' => false,
            'VERBOSE' => false,
        ];
    }

    private function make(string $version, string $relual): object
    {
        $class = self::NS . $version . '\\' . $relual . '\\Page';
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $this->fakeGlobals(), customs: []);
    }

    /** Every page found under Common must resolve for every version. */
    public function testEveryCommonPageResolvesForAllVersions(): void
    {
        foreach ($this->commonLeaves() as $relual) {
            foreach (self::VERSIONS as $v) {
                $class = self::NS . $v . '\\' . $relual . '\\Page';
                $this->assertTrue(class_exists($class), "Missing entry point: $class");
            }
        }
    }

    public function testPricesDropDeltaPreserved(): void
    {
        $this->assertSame('promotions', $this->make('v9', 'FrontOffice\\PricesDrop')->url);
        $this->assertSame('Promotions', $this->make('v9', 'FrontOffice\\PricesDrop')->pageTitle);
        $this->assertSame('prices-drop', $this->make('v7', 'FrontOffice\\PricesDrop')->url);
        $this->assertSame('prices-drop', $this->make('v8', 'FrontOffice\\PricesDrop')->url);
    }

    public function testIdenticalPageSharesSelectorsAcrossVersions(): void
    {
        $s7 = $this->make('v7', 'FrontOffice\\Cart')->selectors;
        $s8 = $this->make('v8', 'FrontOffice\\Cart')->selectors;
        $s9 = $this->make('v9', 'FrontOffice\\Cart')->selectors;
        $this->assertSame($s7, $s8);
        $this->assertSame($s7, $s9);
    }

    /** Relative "Area\\Name" for every leaf under src/Pages/Common (excludes the area base Page.php). */
    private function commonLeaves(): array
    {
        $root = dirname(__DIR__, 3) . '/src/Pages/Common';
        if (!is_dir($root)) {
            return [];
        }
        $leaves = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getFilename() !== 'Page.php') {
                continue;
            }
            $rel = trim(str_replace($root, '', $file->getPath()), '/');
            if ($rel === 'FrontOffice' || $rel === 'BackOffice' || $rel === '') {
                continue; // area base, not a leaf
            }
            $leaves[] = str_replace('/', '\\', $rel);
        }
        sort($leaves);
        return $leaves;
    }
}
```

- [ ] **Step 2: Run it, confirm it FAILS**

Run: `vendor/bin/phpunit tests/Unit/Pages/PageFactorizationTest.php`
Expected: FAIL — Common pages/stubs don't exist yet (e.g. `Missing entry point: …Common… not found` / class not found on make()).

- [ ] **Step 3: Create the two Common base classes**

`src/Pages/Common/FrontOffice/Page.php` — Template A (FrontOffice).
`src/Pages/Common/BackOffice/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice;

use PrestaFlow\Library\Pages\BackOfficePage;

class Page extends BackOfficePage
{
}
```

- [ ] **Step 4: Migrate Cart (identical page)**

Move content to Common (canonical = v7 Cart):
```bash
git mv src/Pages/v7/FrontOffice/Cart/Page.php src/Pages/Common/FrontOffice/Cart/Page.php
```
Edit `src/Pages/Common/FrontOffice/Cart/Page.php`: set namespace to `PrestaFlow\Library\Pages\Common\FrontOffice\Cart` and the base import to `use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;` (leave the class body untouched).

Replace the three version files with pure stubs (Template C), e.g. `src/Pages/v7/FrontOffice/Cart/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\v7\FrontOffice\Cart;

use PrestaFlow\Library\Pages\Common\FrontOffice\Cart\Page as BasePage;

class Page extends BasePage
{
}
```
Create the v8 and v9 equivalents (swap `v7`→`v8`/`v9`). (v8/v9 files already exist — overwrite them with the stub.)

- [ ] **Step 5: Migrate Listing (PricesDrop's parent — sibling-leaf proof)**

`PricesDrop` extends `Listing\Page` (which provides `getListingTitle()`, `goToProduct()`, wishlist selectors). So Listing must exist in Common first. Canonical = v7 Listing (extends the area base):
```bash
git mv src/Pages/v7/FrontOffice/Listing/Page.php src/Pages/Common/FrontOffice/Listing/Page.php
```
Edit moved file: namespace → `…Common\FrontOffice\Listing`, parent import → `use …Common\FrontOffice\Page as BasePage;` (Listing extends the area base). Body unchanged.
Then v7/v8/v9 Listing → pure stubs (Template C, `FrontOffice\Listing`).

- [ ] **Step 6: Migrate PricesDrop (v9 delta, parent = Listing)**

Canonical = v7 PricesDrop:
```bash
git mv src/Pages/v7/FrontOffice/PricesDrop/Page.php src/Pages/Common/FrontOffice/PricesDrop/Page.php
```
Edit the moved file's namespace → `…Common\FrontOffice\PricesDrop` and parent import → `use PrestaFlow\Library\Pages\Common\FrontOffice\Listing\Page as BasePage;` (PricesDrop extends **Listing**, not the area base). The body keeps `public string $pageTitle = 'Prices drop';` and `public string $url = 'prices-drop';`.

v7 and v8 → pure stubs (Template C, `FrontOffice\PricesDrop`).
v9 → override:
```php
<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\PricesDrop;

use PrestaFlow\Library\Pages\Common\FrontOffice\PricesDrop\Page as BasePage;

class Page extends BasePage
{
    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->url = 'promotions';
        $this->pageTitle = 'Promotions';

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals, customs: $customs);
    }
}
```

- [ ] **Step 7: Add an inheritance-preservation assertion to the test**

To lock the fix (PricesDrop must keep Listing's methods), add to `PageFactorizationTest`:
```php
    public function testPricesDropKeepsListingInheritance(): void
    {
        $this->assertTrue(method_exists($this->make('v9', 'FrontOffice\\PricesDrop'), 'getListingTitle'));
        $this->assertTrue(method_exists($this->make('v7', 'FrontOffice\\PricesDrop'), 'getListingTitle'));
    }
```

- [ ] **Step 8: Regenerate autoload and run the test**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/PageFactorizationTest.php`
Expected: PASS (Cart + Listing + PricesDrop resolve for all versions; delta preserved; Cart selectors identical; PricesDrop keeps getListingTitle).

- [ ] **Step 9: Full unit suite**

Run: `composer test-unit`
Expected: green (factorization test + 9 1A tests).

- [ ] **Step 10: Commit** — see "Commits" note at end of plan (do NOT commit without the user's go-ahead).

---

### Task 1: Migrate remaining FrontOffice pages

**Goal:** Apply the templates to every FrontOffice leaf except Cart/PricesDrop (already done) and the FrontOffice base.

**Files:**
- Create: `src/Pages/Common/FrontOffice/<Name>/Page.php` for each remaining leaf
- Modify→stub: `src/Pages/v{7,8,9}/FrontOffice/<Name>/Page.php` for each
- Modify→stub: `src/Pages/v{7,8,9}/FrontOffice/Page.php` (area base, Template D)

**Remaining leaves (24):** Account, Address, Addresses, BestSellers, Brand, Brands, CMS, Category, Contact, Content, CreditSlip, GuestTracking, Home, Identity, Information, Login, Manufacturer, Manufacturers, NewProducts, OrderHistory, Product, Registration, Sitemap, Stores. (Cart, PricesDrop, **Listing** already migrated in Task 0.)

**Special parents (use the Leaf parent map, not the area base):** `Address`→`Common\FrontOffice\Addresses\Page`, `Category`→`Common\FrontOffice\Listing\Page`, `Content`→`Common\FrontOffice\CMS\Page`, `Information`→`Common\FrontOffice\Identity\Page`. All four parents (Addresses, Listing, CMS, Identity) are themselves migrated in this task or Task 0 (Listing), so the Common chain resolves.

**Acceptance Criteria:**
- [ ] Every FrontOffice leaf resolves for v7/v8/v9 (`class_exists`).
- [ ] No FrontOffice leaf under `v{7,8,9}` still declares `defineSelectors()`/`$url`/`$pageTitle` (content lives in Common). Category is a pure stub in all three (cosmetic-only delta folded into Common).
- [ ] `composer test-unit` green.

**Verify:** `composer test-unit` → green (the data-driven test now covers all FO leaves)

**Steps:**

- [ ] **Step 1: For each remaining leaf, apply the identical-page recipe (Task 0 Step 4)**

For `<Name>` in the 24-leaf list above:
```bash
git mv src/Pages/v7/FrontOffice/<Name>/Page.php src/Pages/Common/FrontOffice/<Name>/Page.php
```
Edit moved file: namespace → `…Common\FrontOffice\<Name>`, and the **parent import** → the Common equivalent of its **original** parent (area base for most; for the 4 special leaves use the parent map: Address→Addresses, Category→Listing, Content→CMS, Information→Identity). Keep the original alias name used in the `extends` clause. Then overwrite `v7`, `v8`, `v9` `<Name>/Page.php` with the pure stub (Template C) — stubs always extend the Common leaf of the **same name**.

`Category` follows the same recipe — its cosmetic constructor (`$this->url = '{index}-category'`) is dropped because the Common file already declares `public string $url = '{index}-category';` (canonical from v7); all three become pure stubs.

- [ ] **Step 2: Convert the FrontOffice area base to a stub**

Overwrite `src/Pages/v7/FrontOffice/Page.php`, `v8/…`, `v9/…` with Template D (swap version).

- [ ] **Step 3: Verify no duplicated content remains in FO version dirs**

Run:
```bash
grep -rl "defineSelectors\|public string \$url\|public string \$pageTitle" src/Pages/v7/FrontOffice src/Pages/v8/FrontOffice src/Pages/v9/FrontOffice
```
Expected: no output (all FO content now lives in Common; the only v9 FO override, PricesDrop, sets `$this->url` in a constructor — not matched by these property/method patterns).

- [ ] **Step 4: Regenerate autoload and run the suite**

Run: `composer dump-autoload && composer test-unit`
Expected: green.

- [ ] **Step 5: Commit** — see "Commits" note (await user go-ahead).

---

### Task 2: Migrate BackOffice pages

**Goal:** Factor the BackOffice pages, including the two real v9 deltas (Login, Dashboard) and the v9-only Customer with `@unverified` stubs for v7/v8.

**Files:**
- Create: `src/Pages/Common/BackOffice/{Login,Dashboard,Customer}/Page.php`
- Modify→stub/override: `src/Pages/v{7,8,9}/BackOffice/{Login,Dashboard}/Page.php`, `src/Pages/v{7,8,9}/BackOffice/Page.php`
- Modify→stub: `src/Pages/v9/BackOffice/Customer/Page.php`
- Create: `src/Pages/v7/BackOffice/Customer/Page.php`, `src/Pages/v8/BackOffice/Customer/Page.php` (`@unverified`)

**Acceptance Criteria:**
- [ ] Login, Dashboard, Customer resolve for v7/v8/v9.
- [ ] v9 Dashboard `pageTitle='Tableau de bord'`; v7/v8 `Dashboard`.
- [ ] v9 Login keeps its v9 selectors + overridden method; v7/v8 Login use the canonical (v7) definition.
- [ ] v7/v8 Customer stubs carry an `@unverified` header comment.
- [ ] `composer test-unit` green.

**Verify:** `composer test-unit` → green

**Steps:**

- [ ] **Step 1: BackOffice area base → stubs**

Overwrite `src/Pages/v{7,8,9}/BackOffice/Page.php` with Template D (BackOffice / swap version).

- [ ] **Step 2: Dashboard (v9 delta = pageTitle)**

Canonical = v7 Dashboard:
```bash
git mv src/Pages/v7/BackOffice/Dashboard/Page.php src/Pages/Common/BackOffice/Dashboard/Page.php
```
Edit moved file: namespace → `…Common\BackOffice\Dashboard`, base import → `use …Common\BackOffice\Page as BasePage;` (keeps `public string $pageTitle = 'Dashboard';`).
v7, v8 → pure stubs (Template C, `BackOffice\Dashboard`).
v9 → override:
```php
<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Dashboard;

use PrestaFlow\Library\Pages\Common\BackOffice\Dashboard\Page as BasePage;

class Page extends BasePage
{
    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->pageTitle = 'Tableau de bord';

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals, customs: $customs);
    }
}
```

- [ ] **Step 3: Login (v9 delta = selectors + method)**

Canonical = v7 Login:
```bash
git mv src/Pages/v7/BackOffice/Login/Page.php src/Pages/Common/BackOffice/Login/Page.php
```
Edit moved file: namespace → `…Common\BackOffice\Login`, base import → `use …Common\BackOffice\Page as BasePage;` (body unchanged — v7 selectors + method).
v7, v8 → pure stubs (Template C, `BackOffice\Login`).
v9 → override. The Common (v7) Login already provides `pageTitle`, `defineMessages()`, `login()`, `getLoginError()`, `getPrestashopVersion()` — those are identical in v9 and are inherited. Only `defineSelectors()` (different `psVersionBlock`, `alertDangerDiv`, `alertDangerTextBlock`, extra `headerEmployeeContainer`) and `logout()` (visibility-guarded `leftClick`) genuinely differ, so the override redefines exactly those two:
```php
<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Login;

use PrestaFlow\Library\Pages\Common\BackOffice\Login\Page as BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'loginHeaderBlock' => '#login-header',
            'psVersionBlock' => '#login_form h4',
            'emailInput' => '#email',
            'passwordInput' => '#passwd',
            'submitLoginButton' => '#submit_login',
            'alertDangerDiv' => '.alert-danger',
            'alertDangerTextBlock' => '.alert-danger .alert-text',
            'employeeInfosDropDown' => '#employee_infos a',
            'headerEmployeeContainer' => '#header-employee-container',
            'logoutLink' => '#header_logout',
        ];
    }

    public function logout()
    {
        if ($this->isVisible($this->selector('employeeInfosDropDown')) !== false) {
            $this->leftClick($this->getSelector('employeeInfosDropDown'));
        } else if ($this->isVisible($this->selector('headerEmployeeContainer')) !== false) {
            $this->leftClick($this->getSelector('headerEmployeeContainer'));
        }

        $this->click($this->getSelector('logoutLink'));
    }
}
```

> Note: the Common Login's `defineSelectors()` (v7) differs from v9's, and the v9 selectors test would catch any drift. The data-driven `testIdenticalPageSharesSelectorsAcrossVersions` only runs on identical pages (Cart), not Login, so Login's intentional selector divergence is fine.

- [ ] **Step 4: Customer (v9-only) → Common + stubs**

Canonical = v9 Customer (its only source):
```bash
git mv src/Pages/v9/BackOffice/Customer/Page.php src/Pages/Common/BackOffice/Customer/Page.php
```
Edit moved file: namespace → `…Common\BackOffice\Customer`, base import → `use …Common\BackOffice\Page as BasePage;`.
v9 → pure stub (Template C, `BackOffice\Customer`).
Create v7 and v8 `@unverified` stubs, e.g. `src/Pages/v7/BackOffice/Customer/Page.php`:
```php
<?php

/** @unverified v7 — selectors inherited from the v9 Customer page; not validated on the PrestaShop 1.7 admin. */

namespace PrestaFlow\Library\Pages\v7\BackOffice\Customer;

use PrestaFlow\Library\Pages\Common\BackOffice\Customer\Page as BasePage;

class Page extends BasePage
{
}
```
(v8 identical with `v8` / `@unverified v8`.)

- [ ] **Step 5: Regenerate autoload, extend the test, run the suite**

Add a Dashboard delta assertion to `tests/Unit/Pages/PageFactorizationTest.php`:
```php
    public function testDashboardDeltaPreserved(): void
    {
        $this->assertSame('Tableau de bord', $this->make('v9', 'BackOffice\\Dashboard')->pageTitle);
        $this->assertSame('Dashboard', $this->make('v7', 'BackOffice\\Dashboard')->pageTitle);
    }
```
Run: `composer dump-autoload && composer test-unit`
Expected: green (Customer now resolves for v7/v8/v9; Dashboard delta preserved).

- [ ] **Step 6: Commit** — see "Commits" note (await user go-ahead).

---

### Task 3: Final sweep and consistency check

**Goal:** Confirm no duplicated content remains anywhere, every entry point loads, and the whole suite is green.

**Files:** none created; verification only (plus any stub fix-ups discovered).

**Acceptance Criteria:**
- [ ] No `defineSelectors()`/`$url`/`$pageTitle` declarations remain under `src/Pages/v{7,8,9}` except the three known v9 overrides (PricesDrop, Dashboard, Login).
- [ ] Every Page class under `src/Pages` loads without error.
- [ ] `composer test-unit` green.

**Verify:** commands below all succeed

**Steps:**

- [ ] **Step 1: Duplicated-content sweep**

Run:
```bash
grep -rn "defineSelectors\|public string \$url\|public string \$pageTitle" src/Pages/v7 src/Pages/v8 src/Pages/v9
```
Expected: only matches inside the v9 override constructors for PricesDrop/Dashboard (the `$this->url`/`$this->pageTitle` assignments are not matched by these patterns) and the v9 Login `defineSelectors()`. If any other version file matches, it was missed — convert it to a stub.

- [ ] **Step 2: Load every Page class**

Run:
```bash
php -r '
require "vendor/autoload.php";
$root = "src/Pages";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$n = 0;
foreach ($it as $f) {
    if ($f->getFilename() !== "Page.php") continue;
    $rel = trim(str_replace($root, "", $f->getPath()), "/");
    $class = "PrestaFlow\\Library\\Pages\\" . str_replace("/", "\\", $rel) . "\\Page";
    if (!class_exists($class)) { echo "FAIL load: $class\n"; $n++; }
}
echo $n === 0 ? "all Page classes load\n" : "$n failures\n";
'
```
Expected: `all Page classes load`.

- [ ] **Step 3: Full suite**

Run: `composer test-unit`
Expected: green.

- [ ] **Step 4: Commit** — see "Commits" note (await user go-ahead).

---

## Commits

The repository owner requires explicit approval before any `git commit`. Implementer subagents must NOT run `git commit`/`git add`; leave changes in the working tree. The coordinator will batch commits (suggested: one commit for the whole factorization, or one per task) once the user approves.
