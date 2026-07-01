# BackOffice Controllers Batch (Categories, Customers, Modules, Carriers) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add four BackOffice admin pages (Categories, Customers, Modules, Carriers) plus their suites, reusing the established `goToSubMenu` menu-navigation pattern.

**Architecture:** Each page is a canonical `Common/BackOffice/<Name>/Page.php` (declaring `menuSelector`/`parentMenuSelector`/`pageTitle`/`goTo()`) with three pure per-version stubs — the exact pattern used for Products/Orders in the previous lot. A browser-free structural test covers resolution and menu selectors; one browser suite per page documents the live flow.

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (browser suites only).

Spec: `docs/superpowers/specs/2026-07-01-backoffice-controllers-batch-design.md`

Reference: `BackOfficePage::goToSubMenu($parent, $link)` and the properties `$menuSelector`/`$parentMenuSelector` already exist (added in the previous lot). Existing example page: `src/Pages/Common/BackOffice/Products/Page.php`. Existing example suite: `src/Tests/Suites/BackOffice/Products.php`.

**Page data (menu selectors are PrestaShop conventions, live-validated):**

| Name | menuSelector | parentMenuSelector | pageTitle |
|------|--------------|--------------------|-----------|
| Categories | `#subtab-AdminCategories` | `#subtab-AdminCatalog` | Categories |
| Customers | `#subtab-AdminCustomers` | `#subtab-AdminParentCustomer` | Customers |
| Modules | `#subtab-AdminModulesManage` | `#subtab-AdminParentModulesSf` | Modules |
| Carriers | `#subtab-AdminCarriers` | `#subtab-AdminParentShipping` | Carriers |

---

### Task 0: Four page objects + extended structural test

**Goal:** The four pages (Common + v7/v8/v9 stubs) and the extended browser-free test that locks their resolution and menu selectors.

**Files:**
- Create: `src/Pages/Common/BackOffice/{Categories,Customers,Modules,Carriers}/Page.php`
- Create: `src/Pages/v{7,8,9}/BackOffice/{Categories,Customers,Modules,Carriers}/Page.php` (12 stubs)
- Modify: `tests/Unit/Pages/BackOfficeNavigationTest.php`

**Acceptance Criteria:**
- [ ] The four pages resolve for v7/v8/v9.
- [ ] Each declares the exact menu selectors from the table, a non-empty `pageTitle`, and a `goTo()`.
- [ ] `Customers` (list) coexists with the existing `Customer` (edit) page.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeNavigationTest.php`

**Steps:**

- [ ] **Step 1: Extend the failing test**

Append these methods to the class in `tests/Unit/Pages/BackOfficeNavigationTest.php` (it already has `make()` and `fakeGlobals()` helpers and `const VERSIONS`):
```php
    public function testBatchPagesResolveForAllVersions(): void
    {
        foreach (self::VERSIONS as $v) {
            foreach (['Categories', 'Customers', 'Modules', 'Carriers'] as $name) {
                $this->assertTrue(class_exists(self::NS . $v . '\\BackOffice\\' . $name . '\\Page'), "$v $name");
            }
        }
    }

    public function testBatchPagesDeclareMenu(): void
    {
        $expected = [
            'Categories' => ['#subtab-AdminCategories', '#subtab-AdminCatalog'],
            'Customers'  => ['#subtab-AdminCustomers', '#subtab-AdminParentCustomer'],
            'Modules'    => ['#subtab-AdminModulesManage', '#subtab-AdminParentModulesSf'],
            'Carriers'   => ['#subtab-AdminCarriers', '#subtab-AdminParentShipping'],
        ];
        foreach ($expected as $name => [$menu, $parent]) {
            $page = $this->make('v9', $name);
            $this->assertSame($menu, $page->menuSelector, $name);
            $this->assertSame($parent, $page->parentMenuSelector, $name);
            $this->assertNotSame('', $page->pageTitle, $name);
            $this->assertTrue(method_exists($page, 'goTo'), $name);
        }
    }

    public function testCustomersListCoexistsWithCustomerEdit(): void
    {
        $this->assertTrue(class_exists(self::NS . 'v9\\BackOffice\\Customers\\Page'));
        $this->assertTrue(class_exists(self::NS . 'v9\\BackOffice\\Customer\\Page'));
    }
```

- [ ] **Step 2: Run it, confirm it FAILS**

Run: `vendor/bin/phpunit tests/Unit/Pages/BackOfficeNavigationTest.php`
Expected: FAIL — the four new classes don't exist yet.

- [ ] **Step 3: Create the four Common pages**

`src/Pages/Common/BackOffice/Categories/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Categories;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Categories';
    public string $menuSelector = '#subtab-AdminCategories';
    public string $parentMenuSelector = '#subtab-AdminCatalog';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newCategoryButton' => '#page-header-desc-configuration-add',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
```

`src/Pages/Common/BackOffice/Customers/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Customers;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Customers';
    public string $menuSelector = '#subtab-AdminCustomers';
    public string $parentMenuSelector = '#subtab-AdminParentCustomer';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newCustomerButton' => '#page-header-desc-configuration-add',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
```

`src/Pages/Common/BackOffice/Modules/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Modules;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Modules';
    public string $menuSelector = '#subtab-AdminModulesManage';
    public string $parentMenuSelector = '#subtab-AdminParentModulesSf';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
```

`src/Pages/Common/BackOffice/Carriers/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Carriers;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Carriers';
    public string $menuSelector = '#subtab-AdminCarriers';
    public string $parentMenuSelector = '#subtab-AdminParentShipping';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newCarrierButton' => '#page-header-desc-configuration-add',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }
}
```

- [ ] **Step 4: Create the twelve stubs**

For each `<Name>` in {Categories, Customers, Modules, Carriers} and each version in {v7, v8, v9}, create `src/Pages/<version>/BackOffice/<Name>/Page.php`. Template (shown for v7/Categories — swap the version segment in BOTH the namespace and the `use`, and the `<Name>`):
```php
<?php

namespace PrestaFlow\Library\Pages\v7\BackOffice\Categories;

use PrestaFlow\Library\Pages\Common\BackOffice\Categories\Page as BasePage;

class Page extends BasePage
{
}
```

- [ ] **Step 5: Regenerate autoload and run the test**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/BackOfficeNavigationTest.php`
Expected: PASS (the existing 4 tests + the 3 new ones).

- [ ] **Step 6: Full suite**

Run: `composer test-unit`
Expected: green.

- [ ] **Step 7: Commit** — see "Commits" note (await user go-ahead; do NOT `git commit`).

---

### Task 1: Four test suites

**Goal:** One BackOffice suite per new controller (login → `goTo()` → assert title), matching the existing Products/Orders suite style.

**Files:**
- Create: `src/Tests/Suites/BackOffice/{Categories,Customers,Modules,Carriers}.php`

**Acceptance Criteria:**
- [ ] Each suite imports Login + its page, logs in, calls `goTo()`, asserts the page title contains `pageTitle()`.
- [ ] `php -l` clean on all four; `composer test-unit` still green.

**Verify:** `php -l src/Tests/Suites/BackOffice/Categories.php && php -l src/Tests/Suites/BackOffice/Customers.php && php -l src/Tests/Suites/BackOffice/Modules.php && php -l src/Tests/Suites/BackOffice/Carriers.php`

**Steps:**

- [ ] **Step 1: Create the four suites**

`src/Tests/Suites/BackOffice/Categories.php` (repeat for the other three: swap the class name, the imported page `BackOffice\<Name>`, the extracted var `$backOffice<Name>Page`, and the describe text). The import variable name is `lcfirst` of the page path with backslashes removed + `Page` — e.g. `BackOffice\Categories` → `$backOfficeCategoriesPage`, `BackOffice\Customers` → `$backOfficeCustomersPage`, `BackOffice\Modules` → `$backOfficeModulesPage`, `BackOffice\Carriers` → `$backOfficeCarriersPage`.
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Categories extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Categories');

        extract($this->pages);

        $this
        ->describe('Reach the Categories page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Categories via the menu', function () use ($backOfficeCategoriesPage) {
            $backOfficeCategoriesPage->goTo();

            Expect::that($backOfficeCategoriesPage->getPageTitle())->contains($backOfficeCategoriesPage->pageTitle());
        });
    }
}
```

`src/Tests/Suites/BackOffice/Customers.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Customers extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Customers');

        extract($this->pages);

        $this
        ->describe('Reach the Customers page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Customers via the menu', function () use ($backOfficeCustomersPage) {
            $backOfficeCustomersPage->goTo();

            Expect::that($backOfficeCustomersPage->getPageTitle())->contains($backOfficeCustomersPage->pageTitle());
        });
    }
}
```

`src/Tests/Suites/BackOffice/Modules.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Modules extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Modules');

        extract($this->pages);

        $this
        ->describe('Reach the Modules page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Modules via the menu', function () use ($backOfficeModulesPage) {
            $backOfficeModulesPage->goTo();

            Expect::that($backOfficeModulesPage->getPageTitle())->contains($backOfficeModulesPage->pageTitle());
        });
    }
}
```

`src/Tests/Suites/BackOffice/Carriers.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Carriers extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Carriers');

        extract($this->pages);

        $this
        ->describe('Reach the Carriers page from the admin menu')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should navigate to Carriers via the menu', function () use ($backOfficeCarriersPage) {
            $backOfficeCarriersPage->goTo();

            Expect::that($backOfficeCarriersPage->getPageTitle())->contains($backOfficeCarriersPage->pageTitle());
        });
    }
}
```

- [ ] **Step 2: Lint + suite**

Run: `php -l src/Tests/Suites/BackOffice/Categories.php && php -l src/Tests/Suites/BackOffice/Customers.php && php -l src/Tests/Suites/BackOffice/Modules.php && php -l src/Tests/Suites/BackOffice/Carriers.php && composer test-unit`
Expected: "No syntax errors detected" for each, unit suite still green.

- [ ] **Step 3: Commit** — see "Commits" note (await user go-ahead).

---

## Integration check (manual, needs a reachable PrestaShop BO)

```bash
bin/prestaflow run src/Tests/Suites/BackOffice
```
Confirm each suite logs in and lands on the right page (title contains the declared `pageTitle`). If a menu selector or title differs from the assumed convention (or differs across PS versions), adjust the page's `menuSelector`/`parentMenuSelector`/`pageTitle` — override per version in the v7/v8/v9 stub if the divergence is version-specific. Notably `Modules` may render as "Module Manager".

## Commits

The repository owner requires explicit approval before any `git commit`. Implementer subagents must NOT run `git commit`/`git add`; leave changes in the working tree. The coordinator batches commits once the user approves.
