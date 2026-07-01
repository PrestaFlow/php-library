# BackOffice Products CRUD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the `Common/BackOffice/Products` page real list + create + delete actions (best-effort PS 9 selectors, `@unverified`), plus a self-cleaning CRUD suite, to unblock cross-page scenarios.

**Architecture:** The action *logic* lives on the Common Products page (DRY); the *selectors* live in `defineSelectors()` and are best-effort PS 9 conventions marked `@unverified` (v7/v8 overrides deferred). A browser-free structural test locks that the methods exist and the selector keys are declared; a browser suite documents the live create→find→delete flow (fails until selectors are corrected on a real shop — expected).

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (suite only).

Spec: `docs/superpowers/specs/2026-07-01-backoffice-products-crud-design.md`

Interaction helpers on `CommonPage` (already exist): `getSelector($key, $replacements=[])` (resolves a named selector, replaces `${token}`), `setValue($selector, $value)`, `click($selector, $nth=1)`, `waitForPageReload()`, `getTextContent($selector, $index=1, ...)`. `getSelector` throws if the key is undefined — every key used below is declared in `defineSelectors()`.

---

### Task 0: Products page actions + structural test

**Goal:** The eight action methods and the extended `@unverified` selector set on `Common/BackOffice/Products/Page.php`, plus a browser-free test locking method + selector-key presence.

**Files:**
- Modify: `src/Pages/Common/BackOffice/Products/Page.php`
- Test: `tests/Unit/Pages/BackOfficeProductsTest.php`

**Acceptance Criteria:**
- [ ] The page declares all selector keys from the spec table.
- [ ] The eight methods exist with the right signatures: `filterByName`, `resetFilter`, `getListCount`, `getProductNameInList`, `goToNewProduct`, `createProduct`, `deleteProduct`, `getSuccessMessage`.
- [ ] `defineSelectors()` carries an `@unverified` note.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Pages/BackOfficeProductsTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class BackOfficeProductsTest extends TestCase
{
    private function make(): object
    {
        $class = 'PrestaFlow\\Library\\Pages\\v9\\BackOffice\\Products\\Page';
        $globals = [
            'PS_VERSION' => '9.0.0',
            'LOCALE' => 'en',
            'PREFIX_LOCALE' => false,
            'BO' => ['URL' => 'http://localhost/admin/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'DEBUG' => false,
            'VERBOSE' => false,
        ];
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $globals, customs: []);
    }

    public function testDeclaresAllSelectorKeys(): void
    {
        $selectors = $this->make()->selectors;
        foreach ([
            'pageHeading', 'newProductButton', 'filterNameInput', 'searchButton',
            'resetButton', 'listRow', 'listRowName', 'resultCount', 'rowActionsToggle',
            'rowDeleteLink', 'deleteConfirmButton', 'formNameInput', 'formPriceInput',
            'formQuantityInput', 'formSaveButton', 'successAlert',
        ] as $key) {
            $this->assertArrayHasKey($key, $selectors, $key);
        }
    }

    public function testHasAllActionMethods(): void
    {
        $page = $this->make();
        foreach ([
            'filterByName', 'resetFilter', 'getListCount', 'getProductNameInList',
            'goToNewProduct', 'createProduct', 'deleteProduct', 'getSuccessMessage',
        ] as $method) {
            $this->assertTrue(method_exists($page, $method), $method);
        }
    }
}
```

- [ ] **Step 2: Run it, confirm it FAILS**

Run: `vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`
Expected: FAIL — selector keys and methods don't exist yet.

- [ ] **Step 3: Rewrite `src/Pages/Common/BackOffice/Products/Page.php`**

Replace the whole file with:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\Products;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Products';
    public string $menuSelector = '#subtab-AdminProducts';
    public string $parentMenuSelector = '#subtab-AdminCatalog';

    /**
     * @unverified — best-effort PrestaShop 9 admin selectors. The list grid and
     * product-form selectors have NOT been validated against a live shop and
     * differ on 1.7/8 (v7/v8 defineSelectors() overrides are deferred).
     */
    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newProductButton' => '#page-header-desc-configuration-add',
            'filterNameInput' => '#product_grid_table th input[name="product[name]"]',
            'searchButton' => '#product_grid_search_form button.grid-search-button',
            'resetButton' => '#product_grid_search_form .grid-reset-button',
            'listRow' => '#product_grid_table tbody tr:nth-child(${row})',
            'listRowName' => '#product_grid_table tbody tr:nth-child(${row}) .column-name',
            'resultCount' => '.pagination-total, #product_grid_panel .card-header .badge',
            'rowActionsToggle' => '#product_grid_table tbody tr:nth-child(${row}) .dropdown-toggle',
            'rowDeleteLink' => '#product_grid_table tbody tr:nth-child(${row}) a.grid-delete-row-link',
            'deleteConfirmButton' => '.modal.show .btn-confirm-submit',
            'formNameInput' => '#product_header_name_1',
            'formPriceInput' => '#product_pricing_price_tax_excluded',
            'formQuantityInput' => '#product_stock_quantities_delta_quantity',
            'formSaveButton' => '#product_footer_save',
            'successAlert' => '.alert-success, .growl-success',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    public function filterByName(string $name): void
    {
        $this->setValue($this->getSelector('filterNameInput'), $name);
        $this->click($this->getSelector('searchButton'));
        $this->waitForPageReload();
    }

    public function resetFilter(): void
    {
        $this->click($this->getSelector('resetButton'));
        $this->waitForPageReload();
    }

    public function getListCount(): int
    {
        return (int) preg_replace('/\D+/', '', $this->getTextContent($this->getSelector('resultCount')));
    }

    public function getProductNameInList(int $row = 1): string
    {
        return trim($this->getTextContent($this->getSelector('listRowName', ['row' => $row])));
    }

    public function goToNewProduct(): void
    {
        $this->click($this->getSelector('newProductButton'));
        $this->waitForPageReload();
    }

    public function createProduct(string $name, float $price = 0, int $quantity = 0): void
    {
        $this->goToNewProduct();
        $this->setValue($this->getSelector('formNameInput'), $name);
        $this->setValue($this->getSelector('formPriceInput'), (string) $price);
        $this->setValue($this->getSelector('formQuantityInput'), (string) $quantity);
        $this->click($this->getSelector('formSaveButton'));
        $this->waitForPageReload();
    }

    public function deleteProduct(int $row = 1): void
    {
        $this->click($this->getSelector('rowActionsToggle', ['row' => $row]));
        $this->click($this->getSelector('rowDeleteLink', ['row' => $row]));
        $this->click($this->getSelector('deleteConfirmButton'));
        $this->waitForPageReload();
    }

    public function getSuccessMessage(): string
    {
        return trim($this->getTextContent($this->getSelector('successAlert')));
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`
Expected: PASS — 2 tests.

- [ ] **Step 5: Lint + full suite**

Run: `php -l src/Pages/Common/BackOffice/Products/Page.php && composer test-unit`
Expected: "No syntax errors detected" and the whole suite green (existing 30 + the 2 new).

- [ ] **Step 6: Commit** — see "Commits" note (await user go-ahead; do NOT `git commit`).

---

### Task 1: Self-cleaning ProductsCrud suite

**Goal:** A browser suite that logs in, creates a product, finds it via the filter, then deletes it — cleaning up after itself.

**Files:**
- Create: `src/Tests/Suites/BackOffice/ProductsCrud.php`

**Acceptance Criteria:**
- [ ] The suite logs in, creates a product, asserts the success message, filters by its name, asserts the name appears, deletes it, asserts success.
- [ ] `php -l` clean; `composer test-unit` still green (suite not in the Unit testsuite).

**Verify:** `php -l src/Tests/Suites/BackOffice/ProductsCrud.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Create the suite**

`src/Tests/Suites/BackOffice/ProductsCrud.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class ProductsCrud extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Products');

        extract($this->pages);

        $productName = 'PrestaFlow Test Product';

        $this
        ->describe('Create, find and delete a product from the BackOffice')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should create a product', function () use ($backOfficeProductsPage, $productName) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->createProduct($productName, 9.99, 10);

            Expect::that($backOfficeProductsPage->getSuccessMessage())->isNotEmpty();
        })
        ->it('should find the product in the list', function () use ($backOfficeProductsPage, $productName) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($productName);

            Expect::that($backOfficeProductsPage->getProductNameInList(1))->contains($productName);
        })
        ->it('should delete the product', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->deleteProduct(1);

            Expect::that($backOfficeProductsPage->getSuccessMessage())->isNotEmpty();
        });
    }
}
```

- [ ] **Step 2: Lint + suite**

Run: `php -l src/Tests/Suites/BackOffice/ProductsCrud.php && composer test-unit`
Expected: "No syntax errors detected" and unit suite still green.

- [ ] **Step 3: Commit** — see "Commits" note (await user go-ahead).

---

## Integration check (manual, needs a reachable PrestaShop BO)

```bash
bin/prestaflow run src/Tests/Suites/BackOffice/ProductsCrud.php
```
This is the step that validates the `@unverified` selectors: run it against a real shop and correct any selector in `defineSelectors()` that doesn't match the actual admin DOM until the suite is green (create → find → delete). The suite cleans up after itself (it deletes the product it created). Expect selector corrections on the first live run — that is the point of this check.

## Commits

The repository owner requires explicit approval before any `git commit`. Implementer subagents must NOT run `git commit`/`git add`; leave changes in the working tree. The coordinator batches commits once the user approves.
