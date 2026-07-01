# Cross-page Scenario (create in BO → verify in FO) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a reusable `CreateProductAndVerify` scenario that creates+enables a product in the BackOffice, captures its id, verifies it in the FrontOffice, then deletes it — plus the two BO Products actions it needs.

**Architecture:** Two BO Products additions (`enableProduct`, `getCreatedProductId`) complete the CRUD surface. A `Scenario` subclass composes BO Login + BO Products + FO Product following the existing `AddProductToCart` pattern, passing the new product id between steps via `TestsSuite::store()/retrieve()`. A browser-free structural test locks existence/composition; a suite invokes the scenario for the live check.

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (scenario suite only).

Spec: `docs/superpowers/specs/2026-07-01-cross-page-scenario-design.md`

Notes: `getPage()` (CommonPage) returns the chrome-php Page which has `getCurrentUrl()`. `FrontOffice\Product::goToProduct(int $productId = 0)` and `FrontOfficePage::getTitle()` already exist. Inside `it()` closures, `$this` is the `TestsSuite`, which defines `store()/retrieve()/getParam()` (as used in `AddProductToCart`). `scenario($class, $params)` registers the scenario's `$params` under its class name for `getParam()`.

---

### Task 0: BO Products scenario-support actions

**Goal:** Add `enableProduct()`, `getCreatedProductId()` and the `productOnlineToggle` selector to the Products page.

**Files:**
- Modify: `src/Pages/Common/BackOffice/Products/Page.php`
- Modify: `tests/Unit/Pages/BackOfficeProductsTest.php`

**Acceptance Criteria:**
- [ ] `productOnlineToggle` selector key is declared.
- [ ] `enableProduct(): void` and `getCreatedProductId(): int` exist.
- [ ] `getCreatedProductId()` parses the id from the current URL (`/products/(\d+)`).
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`

**Steps:**

- [ ] **Step 1: Extend the failing test**

Append this method to the class in `tests/Unit/Pages/BackOfficeProductsTest.php` (it already has a private `make()` returning a browser-free v9 Products page):
```php
    public function testHasScenarioSupport(): void
    {
        $page = $this->make();
        $this->assertArrayHasKey('productOnlineToggle', $page->selectors);
        $this->assertTrue(method_exists($page, 'enableProduct'));
        $this->assertTrue(method_exists($page, 'getCreatedProductId'));
    }
```
Run: `vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`
Expected: FAIL — key/methods missing.

- [ ] **Step 2: Add the selector**

In `src/Pages/Common/BackOffice/Products/Page.php`, inside the `defineSelectors()` return array, add this entry after `'formSaveButton' => '#product_footer_save',`:
```php
            'productOnlineToggle' => '#product_header_active_1',
```

- [ ] **Step 3: Add the two methods**

In the same file, add these methods before the final closing `}` of the class (after `getSuccessMessage()`):
```php
    public function enableProduct(): void
    {
        $this->click($this->getSelector('productOnlineToggle'));
    }

    public function getCreatedProductId(): int
    {
        if (preg_match('#/products/(\d+)#', $this->getPage()->getCurrentUrl(), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
```

- [ ] **Step 4: Run the test**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`
Expected: PASS (3 tests now).

- [ ] **Step 5: Lint + full suite**

Run: `php -l src/Pages/Common/BackOffice/Products/Page.php && composer test-unit`
Expected: "No syntax errors detected" and the whole suite green.

- [ ] **Step 6: Commit** — see "Commits" note (await user go-ahead; do NOT `git commit`).

---

### Task 1: `CreateProductAndVerify` scenario + suite + structural test

**Goal:** The composed scenario, the suite that invokes it, and a browser-free test locking their existence and composition.

**Files:**
- Create: `src/Scenarios/CreateProductAndVerify.php`
- Create: `src/Tests/Suites/Scenarios/CreateProductAndVerify.php`
- Test: `tests/Unit/Scenarios/CreateProductAndVerifyTest.php`

**Acceptance Criteria:**
- [ ] The scenario extends `Scenario`, imports BO Login + BO Products + FO Product, and runs login → create+enable → store id → FO goToProduct + assert title → delete.
- [ ] The suite invokes the scenario via `->scenario(...)`.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Scenarios/CreateProductAndVerifyTest.php`

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Scenarios/CreateProductAndVerifyTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class CreateProductAndVerifyTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\CreateProductAndVerify';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\CreateProductAndVerify';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresProductParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\CreateProductAndVerify');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        $this->assertArrayHasKey('productName', $params);
        $this->assertArrayHasKey('productPrice', $params);
        $this->assertArrayHasKey('productQuantity', $params);
    }
}
```
Run: `vendor/bin/phpunit tests/Unit/Scenarios/CreateProductAndVerifyTest.php`
Expected: FAIL — classes don't exist.

- [ ] **Step 2: Create the scenario**

`src/Scenarios/CreateProductAndVerify.php`:
```php
<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class CreateProductAndVerify extends Scenario
{
    public $params = [
        'productName' => 'PrestaFlow Scenario Product',
        'productPrice' => 9.99,
        'productQuantity' => 10,
    ];

    public function steps($testSuite)
    {
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Products');
        $testSuite->importPage('FrontOffice\Product');

        extract($testSuite->pages);

        $testSuite
        ->it('log in to the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('create and enable a product', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->createProduct(
                $this->getParam('productName'),
                (float) $this->getParam('productPrice'),
                (int) $this->getParam('productQuantity')
            );
            $backOfficeProductsPage->enableProduct();

            $this->store('productId', $backOfficeProductsPage->getCreatedProductId());
        })
        ->it('verify the product on the FrontOffice', function () use ($frontOfficeProductPage) {
            $frontOfficeProductPage->goToProduct((int) $this->retrieve('productId'));

            Expect::that($frontOfficeProductPage->getTitle())->contains($this->getParam('productName'));
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

- [ ] **Step 3: Create the invoking suite**

`src/Tests/Suites/Scenarios/CreateProductAndVerify.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class CreateProductAndVerify extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Create a product in the BackOffice and verify it in the FrontOffice')
        ->scenario(\PrestaFlow\Library\Scenarios\CreateProductAndVerify::class);
    }
}
```

- [ ] **Step 4: Run the test**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Scenarios/CreateProductAndVerifyTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Lint + full suite**

Run: `php -l src/Scenarios/CreateProductAndVerify.php && php -l src/Tests/Suites/Scenarios/CreateProductAndVerify.php && composer test-unit`
Expected: "No syntax errors detected" for both, whole suite green.

- [ ] **Step 6: Commit** — see "Commits" note (await user go-ahead).

---

## Integration check (manual, needs a reachable PrestaShop)

```bash
bin/prestaflow run src/Tests/Suites/Scenarios/CreateProductAndVerify.php
```
Validates the `@unverified` selectors end to end: log in, create + enable a product, capture its id, open it in the FO and assert its name, then delete it (self-cleaning). Correct any `defineSelectors()` entry (especially `productOnlineToggle`, the form fields, and the id-from-URL pattern) until the suite is green.

## Commits

The repository owner requires explicit approval before any `git commit`. Implementer subagents must NOT run `git commit`/`git add`; leave changes in the working tree. The coordinator batches commits once the user approves.
