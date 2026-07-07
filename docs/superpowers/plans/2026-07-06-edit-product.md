# Edit Product Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Open an existing product from the BackOffice list, change its price, save, and verify — via a self-cleaning `EditProduct` scenario.

**Architecture:** Extend the `BackOffice\Products` page with `openProduct` (row edit link), `updatePrice` (fill price + save), and `getFormPrice` (JS `.value` read), reusing the existing edit-form selectors. An `EditProduct` scenario creates a product, opens it, edits the price, verifies, then deletes it. Selectors are best-effort PS 9, corrected during live validation.

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (chrome-php). Live shop: PrestaShop 9.0.0-rc.1 at `https://9.0.0-rc.1.test`.

Spec: `docs/superpowers/specs/2026-07-06-edit-product-design.md`

---

## Conventions (read once)

- Page objects use `$this->getSelector('key')`, `$this->click(...)`,
  `$this->setValue(...)`, `$this->waitForPageReload()`, `${row}` token substitution.
- A Scenario extends `PrestaFlow\Library\Scenarios\Scenario`, declares `public
  $params`, defines `steps($testSuite)`; inside `it()` closures `$this` rebinds to
  the suite so `getParam()` is available.
- Selectors listed are **best-effort PS 9**; the coordinator corrects them live.
- The Products page already has: `formPriceInput`
  (`#product_pricing_retail_price_price_tax_excluded`), `formSaveButton`
  (`#product_footer_save`), `goTo()`, `createProduct()`, `deleteProduct()`,
  `filterByName()`.

---

### Task 0: Products page — open + edit price

**Goal:** Products page gains `openProduct`, `updatePrice`, `getFormPrice` (+ a `listRowLink` selector).

**Files:**
- Modify: `src/Pages/Common/BackOffice/Products/Page.php`
- Test: `tests/Unit/Pages/BackOfficeProductsTest.php` (extend the existing file)

**Acceptance Criteria:**
- [ ] Products declares `listRowLink`.
- [ ] Products has `openProduct(int $row = 1)`, `updatePrice(float $price)`, `getFormPrice(): string`.
- [ ] Existing methods/selectors unchanged; `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Extend the structural test (red)**

In `tests/Unit/Pages/BackOfficeProductsTest.php`, add this method to the class:
```php
    public function testHasEditActions(): void
    {
        $page = $this->make();
        $this->assertArrayHasKey('listRowLink', $page->selectors);
        foreach (['openProduct', 'updatePrice', 'getFormPrice'] as $method) {
            $this->assertTrue(method_exists($page, $method), $method);
        }
    }
```
Run: `vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`
Expected: FAIL — `listRowLink` key and the methods don't exist.

- [ ] **Step 2: Add the selector**

In `src/Pages/Common/BackOffice/Products/Page.php`, add to `defineSelectors()` (after the existing `listRowName` entry):
```php
            // Row edit link — best-effort PS 9, corrected live.
            'listRowLink' => '#product_grid_table tbody tr:nth-child(${row}) a[href*="/products/"][href*="/edit"]',
```

- [ ] **Step 3: Add the methods**

In the same file, add after the existing `getCreatedProductUrl()` method (at the end of the class, before the closing brace):
```php
    public function openProduct(int $row = 1): void
    {
        $this->click($this->getSelector('listRowLink', ['row' => $row]));
        $this->waitForPageReload();
    }

    public function updatePrice(float $price): void
    {
        $this->setValue($this->getSelector('formPriceInput'), (string) $price);
        $this->click($this->getSelector('formSaveButton'));
        $this->waitForPageReload();
    }

    public function getFormPrice(): string
    {
        $sel = json_encode($this->getSelector('formPriceInput'));

        return trim((string) $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return e?e.value:"";})()',
            $sel
        ))->getReturnValue());
    }
```

- [ ] **Step 4: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`
Expected: PASS.
Run: `php -l src/Pages/Common/BackOffice/Products/Page.php && composer test-unit`
Expected: lint clean, whole suite green.

- [ ] **Step 5: Commit** — await user go-ahead; do NOT `git commit`.

---

### Task 1: EditProduct scenario + suite + tests

**Goal:** The self-cleaning edit scenario, its execution suite, and browser-free existence/param tests.

**Files:**
- Create: `src/Scenarios/EditProduct.php`
- Create: `src/Tests/Suites/Scenarios/EditProduct.php`
- Test: `tests/Unit/Scenarios/EditProductTest.php`

**Acceptance Criteria:**
- [ ] `EditProduct` extends `Scenario`, declares params `productName`, `initialPrice`, `newPrice`, `quantity`, and does create → open → updatePrice → assert getFormPrice contains newPrice → delete.
- [ ] `EditProduct` suite extends `TestsSuite` and runs the scenario.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Scenarios/EditProductTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Write the structural test (red)**

Create `tests/Unit/Scenarios/EditProductTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class EditProductTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\EditProduct';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\EditProduct';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresEditParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\EditProduct');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['productName', 'initialPrice', 'newPrice', 'quantity'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
```
Run: `vendor/bin/phpunit tests/Unit/Scenarios/EditProductTest.php`
Expected: FAIL — classes do not exist.

- [ ] **Step 2: Create the scenario**

Create `src/Scenarios/EditProduct.php`:
```php
<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class EditProduct extends Scenario
{
    public $params = [
        'productName' => 'PF Edit Test',
        'initialPrice' => 9.99,
        'newPrice' => 19.99,
        'quantity' => 10,
    ];

    public function steps($testSuite)
    {
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Products');

        extract($testSuite->pages);

        $testSuite
        ->it('log in to the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('create a product to edit', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->createProduct(
                $this->getParam('productName'),
                (float) $this->getParam('initialPrice'),
                (int) $this->getParam('quantity')
            );
        })
        ->it('open the product from the list and change its price', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($this->getParam('productName'));
            $backOfficeProductsPage->openProduct(1);

            $backOfficeProductsPage->updatePrice((float) $this->getParam('newPrice'));

            Expect::that($backOfficeProductsPage->getFormPrice())->contains((string) $this->getParam('newPrice'));
        })
        ->it('delete the product from the BackOffice', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($this->getParam('productName'));
            $backOfficeProductsPage->deleteProduct(1);
        });

        return $testSuite;
    }
}
```

- [ ] **Step 3: Create the execution suite**

Create `src/Tests/Suites/Scenarios/EditProduct.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class EditProduct extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Create a product, edit its price from the list, and verify')
        ->scenario(\PrestaFlow\Library\Scenarios\EditProduct::class);
    }
}
```

- [ ] **Step 4: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Scenarios/EditProductTest.php`
Expected: PASS.
Run: `php -l src/Scenarios/EditProduct.php && php -l src/Tests/Suites/Scenarios/EditProduct.php && composer test-unit`
Expected: lint clean, whole suite green.

- [ ] **Step 5: Commit** — await user go-ahead; do NOT `git commit`.

---

## Integration check (LIVE — the real success criterion, run by the coordinator)

Against the local PS 9 (`.env.local` configured, `datas/` exists), one suite at a
time, killing Chrome + clearing the socket between runs:
```bash
pkill -9 -i chrome ; rm -f datas/.broswer datas/.broswer-options
bin/prestaflow run <tmp dir with the EditProduct suite>
```
Expected: BO login → create the product → open it from the list → change the price
→ the price field holds the new value (`getFormPrice()` contains it) → delete.
Green, reproducible.

Reverse-engineer and correct the best-effort `listRowLink` selector live if the
row edit link differs (it is the one unproven selector; the price/save fields were
validated during the product-create work). Do NOT paper over a broken step.

## Commits

Explicit owner approval required before any `git commit`. Implementer subagents
must NOT run `git commit`/`git add`; leave changes in the working tree. The
coordinator batches commits once the user approves.
