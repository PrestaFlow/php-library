# BackOffice Navigation Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a reusable BackOffice menu-navigation helper and two demonstrator admin pages (Products, Orders) so admin controllers can be reached by clicking the session-tokened menu link — no token computation.

**Architecture:** `BackOfficePage` gains `$menuSelector`/`$parentMenuSelector` (declared, PHP 8.4-safe) and a `goToSubMenu($parent, $link)` that opens the parent section (if visible) and clicks the sub-link. `Products` and `Orders` follow the 1B Common+stubs pattern, declaring their menu selectors and a `goTo()`. A browser-free structural test proves the pages resolve for v7/v8/v9 and declare their menu selectors; two browser suites document the live behaviour.

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (browser suites only).

Spec: `docs/superpowers/specs/2026-06-30-backoffice-navigation-design.md`

Reference — existing helpers on `CommonPage`: `leftClick($selector, $nth=1)`, `isVisible($selector, $timeout=1000)`, `waitForPageReload()`, `pageTitle()` (declared title), `getPageTitle()` (DOM title). Existing suite style: `src/Tests/Suites/BackOffice/Login.php`.

---

### Task 0: Navigation helper + Products/Orders pages + structural test

**Goal:** The `goToSubMenu` helper, the two pages (Common + v7/v8/v9 stubs), and a browser-free test that locks their resolution and menu selectors.

**Files:**
- Modify: `src/Pages/BackOfficePage.php`
- Create: `src/Pages/Common/BackOffice/Products/Page.php`, `src/Pages/Common/BackOffice/Orders/Page.php`
- Create: `src/Pages/v7/BackOffice/Products/Page.php`, `src/Pages/v8/BackOffice/Products/Page.php`, `src/Pages/v9/BackOffice/Products/Page.php`
- Create: `src/Pages/v7/BackOffice/Orders/Page.php`, `src/Pages/v8/BackOffice/Orders/Page.php`, `src/Pages/v9/BackOffice/Orders/Page.php`
- Test: `tests/Unit/Pages/BackOfficeNavigationTest.php`

**Acceptance Criteria:**
- [ ] `BackOfficePage` declares `public string $menuSelector = '';` and `public string $parentMenuSelector = '';` and a `goToSubMenu(string $parentSelector, string $linkSelector): void`.
- [ ] `BackOffice\Products` and `BackOffice\Orders` resolve for v7/v8/v9.
- [ ] Products declares `#subtab-AdminProducts` / `#subtab-AdminCatalog`; Orders declares `#subtab-AdminOrders` / `#subtab-AdminParentOrders`; both have a non-empty `pageTitle` and a `goTo()`.
- [ ] `composer test-unit` green; `php -l` clean on `BackOfficePage.php`.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeNavigationTest.php && php -l src/Pages/BackOfficePage.php`

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Pages/BackOfficeNavigationTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class BackOfficeNavigationTest extends TestCase
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

    private function make(string $version, string $name): object
    {
        $class = self::NS . $version . '\\BackOffice\\' . $name . '\\Page';
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $this->fakeGlobals(), customs: []);
    }

    public function testPagesResolveForAllVersions(): void
    {
        foreach (self::VERSIONS as $v) {
            foreach (['Products', 'Orders'] as $name) {
                $this->assertTrue(class_exists(self::NS . $v . '\\BackOffice\\' . $name . '\\Page'), "$v $name");
            }
        }
    }

    public function testProductsDeclaresMenu(): void
    {
        $p = $this->make('v9', 'Products');
        $this->assertSame('#subtab-AdminProducts', $p->menuSelector);
        $this->assertSame('#subtab-AdminCatalog', $p->parentMenuSelector);
        $this->assertNotSame('', $p->pageTitle);
        $this->assertTrue(method_exists($p, 'goTo'));
    }

    public function testOrdersDeclaresMenu(): void
    {
        $o = $this->make('v9', 'Orders');
        $this->assertSame('#subtab-AdminOrders', $o->menuSelector);
        $this->assertSame('#subtab-AdminParentOrders', $o->parentMenuSelector);
        $this->assertNotSame('', $o->pageTitle);
        $this->assertTrue(method_exists($o, 'goTo'));
    }

    public function testHelperExists(): void
    {
        $this->assertTrue(method_exists('PrestaFlow\\Library\\Pages\\BackOfficePage', 'goToSubMenu'));
    }
}
```

- [ ] **Step 2: Run it, confirm it FAILS**

Run: `vendor/bin/phpunit tests/Unit/Pages/BackOfficeNavigationTest.php`
Expected: FAIL — `goToSubMenu` missing and Products/Orders classes not found.

- [ ] **Step 3: Add the helper + properties to `BackOfficePage`**

In `src/Pages/BackOfficePage.php`, add the two properties at the top of the class body (right after the `class BackOfficePage extends CommonPage {` opening and `use` traits, before `__construct`):
```php
    public string $menuSelector = '';
    public string $parentMenuSelector = '';
```
Add this method after `getPageURL()` (before the closing `}` of the class):
```php
    public function goToSubMenu(string $parentSelector, string $linkSelector): void
    {
        if ($parentSelector !== '' && $this->isVisible($parentSelector) !== false) {
            $this->leftClick($parentSelector);
        }

        $this->leftClick($linkSelector);
        $this->waitForPageReload();
    }
```

- [ ] **Step 4: Create the Products page (Common + stubs)**

`src/Pages/Common/BackOffice/Products/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Products;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Products';
    public string $menuSelector = '#subtab-AdminProducts';
    public string $parentMenuSelector = '#subtab-AdminCatalog';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newProductButton' => '#page-header-desc-configuration-add',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
```
Then the three pure stubs. `src/Pages/v7/BackOffice/Products/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\v7\BackOffice\Products;

use PrestaFlow\Library\Pages\Common\BackOffice\Products\Page as BasePage;

class Page extends BasePage
{
}
```
Create the same for `v8` and `v9` (swap the version in the namespace and the `use`).

- [ ] **Step 5: Create the Orders page (Common + stubs)**

`src/Pages/Common/BackOffice/Orders/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Orders;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Orders';
    public string $menuSelector = '#subtab-AdminOrders';
    public string $parentMenuSelector = '#subtab-AdminParentOrders';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newOrderButton' => '#page-header-desc-order-new_order',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
```
Then the three pure stubs. `src/Pages/v7/BackOffice/Orders/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\v7\BackOffice\Orders;

use PrestaFlow\Library\Pages\Common\BackOffice\Orders\Page as BasePage;

class Page extends BasePage
{
}
```
Create the same for `v8` and `v9`.

- [ ] **Step 6: Regenerate autoload and run the test**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/BackOfficeNavigationTest.php`
Expected: PASS — 4 tests.

- [ ] **Step 7: Lint + full suite**

Run: `php -l src/Pages/BackOfficePage.php && composer test-unit`
Expected: "No syntax errors detected" and the whole suite green (existing 23 + the new BackOffice test).

- [ ] **Step 8: Commit** — see "Commits" note (await user go-ahead; do NOT `git commit`).

---

### Task 1: Products & Orders test suites (executable docs / manual check)

**Goal:** Two BackOffice suites that log in and navigate via the menu to each page, asserting the page title — the documented live-behaviour check.

**Files:**
- Create: `src/Tests/Suites/BackOffice/Products.php`
- Create: `src/Tests/Suites/BackOffice/Orders.php`

**Acceptance Criteria:**
- [ ] Each suite imports Login + its page, logs in, calls `goTo()`, and asserts the page title contains the declared `pageTitle()`.
- [ ] `php -l` clean on both suites.

**Verify:** `php -l src/Tests/Suites/BackOffice/Products.php && php -l src/Tests/Suites/BackOffice/Orders.php` (behaviour requires a live shop — see "Integration check").

**Steps:**

- [ ] **Step 1: Create the Products suite**

`src/Tests/Suites/BackOffice/Products.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Products extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Products');

        extract($this->pages);

        $this
        ->describe('Reach the Products page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Products via the menu', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();

            Expect::that($backOfficeProductsPage->getPageTitle())->contains($backOfficeProductsPage->pageTitle());
        });
    }
}
```

- [ ] **Step 2: Create the Orders suite**

`src/Tests/Suites/BackOffice/Orders.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Orders extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Orders');

        extract($this->pages);

        $this
        ->describe('Reach the Orders page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Orders via the menu', function () use ($backOfficeOrdersPage) {
            $backOfficeOrdersPage->goTo();

            Expect::that($backOfficeOrdersPage->getPageTitle())->contains($backOfficeOrdersPage->pageTitle());
        });
    }
}
```

- [ ] **Step 3: Lint**

Run: `php -l src/Tests/Suites/BackOffice/Products.php && php -l src/Tests/Suites/BackOffice/Orders.php`
Expected: "No syntax errors detected" for both.
Run: `composer test-unit`
Expected: still green (these suites are not part of the Unit testsuite).

- [ ] **Step 4: Commit** — see "Commits" note (await user go-ahead).

---

## Integration check (manual, needs a reachable PrestaShop BO)

With a shop reachable and BO credentials configured (`.env`):
```bash
bin/prestaflow run src/Tests/Suites/BackOffice
```
Confirm the Products and Orders suites log in and land on the right pages (title contains "Products" / "Orders"). If the admin menu selectors differ from the assumed `#subtab-Admin*` conventions (or differ across PS versions), adjust the offending page's `menuSelector`/`parentMenuSelector` — override per version in the v7/v8/v9 stub if the divergence is version-specific (1B pattern). This is the step that validates the assumed selectors.

## Commits

The repository owner requires explicit approval before any `git commit`. Implementer subagents must NOT run `git commit`/`git add`; leave changes in the working tree. The coordinator batches commits once the user approves.
