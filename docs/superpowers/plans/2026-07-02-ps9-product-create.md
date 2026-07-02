# PS 9 Product Create Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite the BackOffice Products page's product-creation flow to drive PrestaShop 9's real JS/AJAX create flow (type page → draft → inline edit form), with corrected selectors, and fix the enable-before-save bug in the CreateProductAndVerify scenario.

**Architecture:** One cohesive change to `src/Pages/Common/BackOffice/Products/Page.php` — `goToNewProduct()` becomes two link-clicks (Add link → `#create_product_create`), `createProduct()` activates the product before saving, and three selectors are corrected/added. The scenario drops its now-redundant `enableProduct()` call. A browser-free structural test locks the selectors and method ordering; live validation against the local PS 9 is the real success criterion.

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (chrome-php). Live shop: PrestaShop 9.0.0-rc.1 at `https://9.0.0-rc.1.test`.

Spec: `docs/superpowers/specs/2026-07-02-ps9-product-create-design.md`

---

### Task 0: PS 9 create flow + selectors + scenario fix

**Goal:** Products page creates & publishes a standard product via the real PS 9 flow; scenario no longer double-enables after save; structural test proves the new selectors and ordering.

**Files:**
- Modify: `src/Pages/Common/BackOffice/Products/Page.php` (`defineSelectors`, `goToNewProduct`, `createProduct`)
- Modify: `src/Scenarios/CreateProductAndVerify.php` (remove redundant `enableProduct()` call)
- Modify (test): `tests/Unit/Pages/BackOfficeProductsTest.php` (add corrected-selector + ordering assertions)

**Acceptance Criteria:**
- [ ] `defineSelectors()` adds `createProductButton => '#create_product_create'`, sets `formPriceInput => '#product_pricing_retail_price_price_tax_excluded'` and `formQuantityInput => '#product_stock_quantities_delta_quantity_delta'`.
- [ ] `goToNewProduct()` clicks `newProductButton`, `waitForPageReload()`, then clicks `createProductButton`, `waitForPageReload()`.
- [ ] `createProduct()` calls `goToNewProduct()`, fills name/price/quantity, clicks `productOnlineToggle`, then clicks `formSaveButton`, then `waitForPageReload()` — activation is before save.
- [ ] `CreateProductAndVerify` no longer calls `enableProduct()`.
- [ ] `composer test-unit` green (new assertions + all existing).

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Extend the structural test (red)**

In `tests/Unit/Pages/BackOfficeProductsTest.php`, add the `createProductButton` key to the `testDeclaresAllSelectorKeys()` list and add two new test methods. Replace the `testDeclaresAllSelectorKeys` key array to include `createProductButton`:

```php
    public function testDeclaresAllSelectorKeys(): void
    {
        $selectors = $this->make()->selectors;
        foreach ([
            'pageHeading', 'newProductButton', 'createProductButton', 'filterNameInput', 'searchButton',
            'resetButton', 'listRow', 'listRowName', 'resultCount', 'rowActionsToggle',
            'rowDeleteLink', 'deleteConfirmButton', 'formNameInput', 'formPriceInput',
            'formQuantityInput', 'formSaveButton', 'successAlert',
        ] as $key) {
            $this->assertArrayHasKey($key, $selectors, $key);
        }
    }
```

Add these methods to the class:

```php
    public function testUsesRealPs9FormSelectors(): void
    {
        $selectors = $this->make()->selectors;
        $this->assertSame('#create_product_create', $selectors['createProductButton']);
        $this->assertSame('#product_pricing_retail_price_price_tax_excluded', $selectors['formPriceInput']);
        $this->assertSame('#product_stock_quantities_delta_quantity_delta', $selectors['formQuantityInput']);
    }

    private function methodBody(string $method): string
    {
        $class = 'PrestaFlow\\Library\\Pages\\Common\\BackOffice\\Products\\Page';
        $ref = new \ReflectionMethod($class, $method);
        $lines = file($ref->getFileName());
        return implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        ));
    }

    public function testGoToNewProductClicksAddThenCreate(): void
    {
        $body = $this->methodBody('goToNewProduct');
        $this->assertStringContainsString('newProductButton', $body);
        $this->assertStringContainsString('createProductButton', $body);
        $this->assertLessThan(
            strpos($body, 'createProductButton'),
            strpos($body, 'newProductButton'),
            'goToNewProduct must click the Add link before the create-draft button'
        );
    }

    public function testCreateProductActivatesBeforeSave(): void
    {
        $body = $this->methodBody('createProduct');
        $this->assertLessThan(
            strpos($body, 'formSaveButton'),
            strpos($body, 'productOnlineToggle'),
            'createProduct must click the active radio before saving so it persists'
        );
    }
```

Run: `vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`
Expected: FAIL — `createProductButton` key missing, `formPriceInput`/`formQuantityInput` still the old values, `productOnlineToggle` not in `createProduct` body.

- [ ] **Step 2: Correct the selectors**

In `src/Pages/Common/BackOffice/Products/Page.php`, update the `@unverified` docblock and the three selectors inside `defineSelectors()`. Replace the docblock above `defineSelectors()`:

```php
    /**
     * PrestaShop 9 admin selectors. The product-create/edit flow and its
     * selectors are validated live (2026-07-02) against PS 9.0.0-rc.1. The list
     * grid selectors are validated via the ProductsCrud suite. v7/v8
     * defineSelectors() overrides remain deferred.
     */
```

Add the `createProductButton` line right after `newProductButton`, and change `formPriceInput` and `formQuantityInput`:

```php
            'newProductButton' => '#page-header-desc-configuration-add',
            'createProductButton' => '#create_product_create',
```
```php
            'formNameInput' => '#product_header_name_1',
            'formPriceInput' => '#product_pricing_retail_price_price_tax_excluded',
            'formQuantityInput' => '#product_stock_quantities_delta_quantity_delta',
```

- [ ] **Step 3: Rewrite `goToNewProduct()`**

Replace the current `goToNewProduct()` (lines ~69-73) with the two-click flow:

```php
    public function goToNewProduct(): void
    {
        // "Add new product" on the list; its href points to
        // /sell/catalog/products/create?shopId=1 (no token needed).
        $this->click($this->getSelector('newProductButton'));
        $this->waitForPageReload();
        // On the create page, "Standard product" is pre-selected. Clicking the
        // create button instantiates the draft; the AJAX flow then renders the
        // full editable product form inline.
        $this->click($this->getSelector('createProductButton'));
        $this->waitForPageReload();
    }
```

- [ ] **Step 4: Activate before save in `createProduct()`**

Replace the current `createProduct()` (lines ~75-83) so the active radio is clicked before saving:

```php
    public function createProduct(string $name, float $price = 0, int $quantity = 0): void
    {
        $this->goToNewProduct();
        $this->setValue($this->getSelector('formNameInput'), $name);
        $this->setValue($this->getSelector('formPriceInput'), (string) $price);
        $this->setValue($this->getSelector('formQuantityInput'), (string) $quantity);
        // Enable the product (radio) BEFORE saving so the online state persists.
        $this->click($this->getSelector('productOnlineToggle'));
        $this->click($this->getSelector('formSaveButton'));
        $this->waitForPageReload();
    }
```

- [ ] **Step 5: Drop the redundant `enableProduct()` in the scenario**

In `src/Scenarios/CreateProductAndVerify.php`, remove the `$backOfficeProductsPage->enableProduct();` line (line ~35) inside the "create and enable a product" step, since `createProduct` now enables before saving. The step becomes:

```php
        ->it('create and enable a product', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->createProduct(
                $this->getParam('productName'),
                (float) $this->getParam('productPrice'),
                (int) $this->getParam('productQuantity')
            );

            $this->store('productId', $backOfficeProductsPage->getCreatedProductId());
        })
```

- [ ] **Step 6: Run the test + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/BackOfficeProductsTest.php`
Expected: PASS (all methods, including the 3 new ones).
Run: `php -l src/Pages/Common/BackOffice/Products/Page.php && php -l src/Scenarios/CreateProductAndVerify.php && composer test-unit`
Expected: lint clean and the whole unit suite green.

- [ ] **Step 7: Commit** — see "Commits" note (await user go-ahead; do NOT `git commit`).

---

## Integration check (LIVE — the real success criterion, run by the coordinator)

Against the local PrestaShop 9 (`.env.local` configured, `datas/` exists), one
suite at a time, killing Chrome + clearing the socket between runs:

```bash
pkill -f "Google Chrome" ; rm -f datas/.broswer datas/.broswer-options
bin/prestaflow run <tmp dir with ProductsCrud>
pkill -f "Google Chrome" ; rm -f datas/.broswer datas/.broswer-options
bin/prestaflow run <tmp dir with the CreateProductAndVerify suite>
```

Expected:
- **ProductsCrud**: logs in, opens the product list, creates a product via the
  new flow, filters by name and finds it, deletes it. Green.
- **CreateProductAndVerify**: creates the product in the BO, the product is
  **visible on the FrontOffice** (title contains the product name), then it is
  deleted. Green.

If the create submit does not land on `/products/{id}/edit` (so
`getCreatedProductId()` returns 0), inspect the post-save URL live and adjust the
`waitForPageReload()` timing or the id regex — do NOT paper over it with a fixed
id.

## Commits

The repository owner requires explicit approval before any `git commit`.
Implementer subagents must NOT run `git commit`/`git add`; leave changes in the
working tree. The coordinator batches commits once the user approves.
