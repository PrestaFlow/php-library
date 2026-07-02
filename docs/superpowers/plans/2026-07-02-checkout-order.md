# Checkout Order Scenario Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A reusable `CheckoutOrder` scenario where a logged-in customer adds a product to the cart, completes the PrestaShop 9 checkout tunnel with an offline payment, and the resulting order is verified on the FrontOffice confirmation page and then found in the BackOffice orders list.

**Architecture:** New/extended focused page objects — FrontOffice `Cart` (methods), `Checkout` (tunnel), `OrderConfirmation` (read reference), and BackOffice `Orders` (order lookup) — composed by a `CheckoutOrder` scenario. FrontOffice `Login` already provides `login()`. Selectors are best-effort PS 9 and are **corrected during live validation** (the proven method from the product CRUD). Browser-free structural tests lock the surface; the live end-to-end run is the real success criterion.

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (chrome-php). Live shop: PrestaShop 9.0.0-rc.1 at `https://9.0.0-rc.1.test`.

Spec: `docs/superpowers/specs/2026-07-02-checkout-order-design.md`

---

## Conventions (read once)

- Every new `Common/FrontOffice/<Name>/Page.php` needs thin per-version stubs at
  `v7/`, `v8/`, `v9/` (because `importPage('FrontOffice\<Name>')` builds
  `Pages\v{N}\FrontOffice\<Name>\Page`). A stub is:
  ```php
  <?php

  namespace PrestaFlow\Library\Pages\v9\FrontOffice\<Name>;

  use PrestaFlow\Library\Pages\Common\FrontOffice\<Name>\Page as BasePage;

  class Page extends BasePage
  {
  }
  ```
  (identical for `v7` and `v8`, changing the namespace segment).
- Page objects use `$this->getSelector('key')`, `$this->click(...)`,
  `$this->setValue(...)`, `$this->waitForPageReload()`, `$this->getTextContent(...)`,
  `$this->isVisible(...)` — all inherited from `CommonPage`.
- Selectors listed are **best-effort PS 9**; the coordinator corrects them live.

---

### Task 0: FrontOffice checkout pages (Cart, Checkout, OrderConfirmation)

**Goal:** The FrontOffice checkout surface — cart actions, the multi-step tunnel, and the confirmation read — as focused page objects with per-version stubs.

**Files:**
- Modify: `src/Pages/Common/FrontOffice/Cart/Page.php`
- Create: `src/Pages/Common/FrontOffice/Checkout/Page.php` + stubs `src/Pages/v7|v8|v9/FrontOffice/Checkout/Page.php`
- Create: `src/Pages/Common/FrontOffice/OrderConfirmation/Page.php` + stubs `src/Pages/v7|v8|v9/FrontOffice/OrderConfirmation/Page.php`
- Test: `tests/Unit/Pages/FrontOfficeCheckoutTest.php`

**Acceptance Criteria:**
- [ ] `Cart` page declares `checkoutButton` and has `proceedToCheckout()`.
- [ ] `Checkout` page declares the tunnel selectors and has `confirmAddresses()`, `chooseShipping()`, `choosePaymentAndConfirm()`.
- [ ] `OrderConfirmation` page declares `confirmationBlock`/`orderReference` and has `isConfirmed()`, `getOrderReference()`.
- [ ] All three resolve for v9 (`importPage` builds the v9 class).
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/FrontOfficeCheckoutTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Write the structural test (red)**

Create `tests/Unit/Pages/FrontOfficeCheckoutTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class FrontOfficeCheckoutTest extends TestCase
{
    private array $globals = [
        'PS_VERSION' => '9.0.0',
        'LOCALE' => 'en',
        'PREFIX_LOCALE' => false,
        'BO' => ['URL' => 'http://localhost/admin/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
        'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
        'DEBUG' => false,
        'VERBOSE' => false,
    ];

    private function make(string $name): object
    {
        $class = 'PrestaFlow\\Library\\Pages\\v9\\FrontOffice\\' . $name . '\\Page';
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $this->globals, customs: []);
    }

    public function testCartHasCheckoutAction(): void
    {
        $page = $this->make('Cart');
        $this->assertArrayHasKey('checkoutButton', $page->selectors);
        $this->assertTrue(method_exists($page, 'proceedToCheckout'));
    }

    public function testCheckoutHasTunnelActions(): void
    {
        $page = $this->make('Checkout');
        foreach (['addressesContinueButton', 'shippingOption', 'shippingContinueButton', 'paymentOption', 'termsCheckbox', 'placeOrderButton'] as $key) {
            $this->assertArrayHasKey($key, $page->selectors, $key);
        }
        foreach (['confirmAddresses', 'chooseShipping', 'choosePaymentAndConfirm'] as $method) {
            $this->assertTrue(method_exists($page, $method), $method);
        }
    }

    public function testOrderConfirmationReadsReference(): void
    {
        $page = $this->make('OrderConfirmation');
        $this->assertArrayHasKey('confirmationBlock', $page->selectors);
        $this->assertArrayHasKey('orderReference', $page->selectors);
        $this->assertTrue(method_exists($page, 'isConfirmed'));
        $this->assertTrue(method_exists($page, 'getOrderReference'));
    }
}
```
Run: `vendor/bin/phpunit tests/Unit/Pages/FrontOfficeCheckoutTest.php`
Expected: FAIL — Checkout/OrderConfirmation classes do not exist yet.

- [ ] **Step 2: Extend the Cart page**

Replace the empty class body in `src/Pages/Common/FrontOffice/Cart/Page.php` with:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice\Cart;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Cart';
    public string $url = 'cart';

    public function defineSelectors()
    {
        return [
            // Best-effort PS 9 classic theme — corrected live.
            'checkoutButton' => '.cart-detailed-actions a.btn, .checkout a.btn',
        ];
    }

    public function proceedToCheckout(): void
    {
        $this->click($this->getSelector('checkoutButton'));
        $this->waitForPageReload();
    }
}
```

- [ ] **Step 3: Create the Checkout page + stubs**

Create `src/Pages/Common/FrontOffice/Checkout/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice\Checkout;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Checkout';
    public string $url = 'order';

    public function defineSelectors()
    {
        return [
            // Best-effort PS 9 checkout tunnel (order controller) — corrected live.
            'addressesContinueButton' => '#checkout-addresses-step button[name="confirm-addresses"]',
            'shippingOption' => '#checkout-delivery-step input[name^="delivery_option"]',
            'shippingContinueButton' => '#checkout-delivery-step button[name="confirmDeliveryOption"]',
            'paymentOption' => 'input[name="payment-option"]',
            'termsCheckbox' => '#conditions-to-approve input[type="checkbox"]',
            'placeOrderButton' => '#payment-confirmation button',
        ];
    }

    public function confirmAddresses(): void
    {
        $this->click($this->getSelector('addressesContinueButton'));
        $this->waitForPageReload();
    }

    public function chooseShipping(): void
    {
        $this->click($this->getSelector('shippingOption'));
        $this->click($this->getSelector('shippingContinueButton'));
        $this->waitForPageReload();
    }

    public function choosePaymentAndConfirm(): void
    {
        $this->click($this->getSelector('paymentOption'));
        $this->click($this->getSelector('termsCheckbox'));
        $this->click($this->getSelector('placeOrderButton'));
        $this->waitForPageReload();
    }
}
```
Create the three stubs (identical bar the version segment), e.g. `src/Pages/v9/FrontOffice/Checkout/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Checkout;

use PrestaFlow\Library\Pages\Common\FrontOffice\Checkout\Page as BasePage;

class Page extends BasePage
{
}
```
and the same for `v8` and `v7` (change `v9` → `v8` / `v7` in the namespace).

- [ ] **Step 4: Create the OrderConfirmation page + stubs**

Create `src/Pages/Common/FrontOffice/OrderConfirmation/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice\OrderConfirmation;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Order confirmation';

    public function defineSelectors()
    {
        return [
            // Best-effort PS 9 confirmation page — corrected live.
            'confirmationBlock' => '#content-hook_order_confirmation',
            'orderReference' => '#order-reference-value',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->isVisible($this->getSelector('confirmationBlock'));
    }

    public function getOrderReference(): string
    {
        return trim($this->getTextContent($this->getSelector('orderReference')));
    }
}
```
Create the three stubs `src/Pages/v7|v8|v9/FrontOffice/OrderConfirmation/Page.php` (same shape as Step 3's stub, namespace `Pages\v{N}\FrontOffice\OrderConfirmation`).

- [ ] **Step 5: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/FrontOfficeCheckoutTest.php`
Expected: PASS (3 methods).
Run: `php -l src/Pages/Common/FrontOffice/Checkout/Page.php && php -l src/Pages/Common/FrontOffice/OrderConfirmation/Page.php && composer test-unit`
Expected: lint clean, whole unit suite green.

- [ ] **Step 6: Commit** — await user go-ahead; do NOT `git commit`.

---

### Task 1: BackOffice Orders — order lookup

**Goal:** Find an order in the BackOffice orders grid by its reference.

**Files:**
- Modify: `src/Pages/Common/BackOffice/Orders/Page.php`
- Test: `tests/Unit/Pages/BackOfficeOrdersTest.php`

**Acceptance Criteria:**
- [ ] Orders page declares `filterReferenceInput`, `searchButton`, `listRowReference`.
- [ ] Orders page has `filterByReference()` and `getOrderReferenceInList()`.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeOrdersTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Write the structural test (red)**

Create `tests/Unit/Pages/BackOfficeOrdersTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class BackOfficeOrdersTest extends TestCase
{
    private function make(): object
    {
        $class = 'PrestaFlow\\Library\\Pages\\v9\\BackOffice\\Orders\\Page';
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

    public function testDeclaresOrderLookupSelectors(): void
    {
        $selectors = $this->make()->selectors;
        foreach (['filterReferenceInput', 'searchButton', 'listRowReference'] as $key) {
            $this->assertArrayHasKey($key, $selectors, $key);
        }
    }

    public function testHasOrderLookupMethods(): void
    {
        $page = $this->make();
        $this->assertTrue(method_exists($page, 'filterByReference'));
        $this->assertTrue(method_exists($page, 'getOrderReferenceInList'));
    }
}
```
Run: `vendor/bin/phpunit tests/Unit/Pages/BackOfficeOrdersTest.php`
Expected: FAIL — selectors/methods missing.

- [ ] **Step 2: Extend the Orders page**

In `src/Pages/Common/BackOffice/Orders/Page.php`, replace `defineSelectors()` and add the two methods:
```php
    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'newOrderButton' => '#page-header-desc-order-new_order',
            // Best-effort PS 9 orders grid — corrected live.
            'filterReferenceInput' => '#order_grid_table th input[name="order[reference]"]',
            'searchButton' => '#order_grid_search_form button.grid-search-button',
            'listRowReference' => '#order_grid_table tbody tr:nth-child(${row}) .column-reference',
        ];
    }

    public function filterByReference(string $reference): void
    {
        $this->setValue($this->getSelector('filterReferenceInput'), $reference);
        $this->click($this->getSelector('searchButton'));
        $this->waitForPageReload();
    }

    public function getOrderReferenceInList(int $row = 1): string
    {
        return trim($this->getTextContent($this->getSelector('listRowReference', ['row' => $row])));
    }
```
(Keep the existing `goTo()` method unchanged.)

- [ ] **Step 3: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/BackOfficeOrdersTest.php`
Expected: PASS.
Run: `php -l src/Pages/Common/BackOffice/Orders/Page.php && composer test-unit`
Expected: lint clean, whole suite green.

- [ ] **Step 4: Commit** — await user go-ahead; do NOT `git commit`.

---

### Task 2: CheckoutOrder scenario + suite + tests

**Goal:** Compose the pages into an end-to-end checkout scenario, with an execution suite and browser-free existence/param tests.

**Files:**
- Create: `src/Scenarios/CheckoutOrder.php`
- Create: `src/Tests/Suites/Scenarios/CheckoutOrder.php`
- Test: `tests/Unit/Scenarios/CheckoutOrderTest.php`

**Acceptance Criteria:**
- [ ] `CheckoutOrder` scenario extends `Scenario`, declares params `customerEmail`, `customerPassword`, `productId`, `cartQuantity`, and composes the FO+BO steps with `store('orderReference', ...)`.
- [ ] Execution suite extends `TestsSuite` and runs the scenario.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Scenarios/CheckoutOrderTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Write the structural test (red)**

Create `tests/Unit/Scenarios/CheckoutOrderTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class CheckoutOrderTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\CheckoutOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\CheckoutOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresCheckoutParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\CheckoutOrder');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['customerEmail', 'customerPassword', 'productId', 'cartQuantity'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
```
Run: `vendor/bin/phpunit tests/Unit/Scenarios/CheckoutOrderTest.php`
Expected: FAIL — classes do not exist.

- [ ] **Step 2: Create the scenario**

Create `src/Scenarios/CheckoutOrder.php`:
```php
<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class CheckoutOrder extends Scenario
{
    public $params = [
        // Defaults confirmed live; override per shop via the suite/params.
        'customerEmail' => 'pub@prestashop.com',
        'customerPassword' => '0123456789',
        'productId' => 1,
        'cartQuantity' => 1,
    ];

    public function steps($testSuite)
    {
        $testSuite->importPage('FrontOffice\Login');
        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\Checkout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Orders');

        extract($testSuite->pages);

        $testSuite
        ->it('log in on the FrontOffice', function () use ($frontOfficeLoginPage) {
            $frontOfficeLoginPage->goToPage('login');
            $frontOfficeLoginPage->login(
                $this->getParam('customerEmail'),
                $this->getParam('customerPassword')
            );
        })
        ->it('add a product to the cart', function () use ($frontOfficeProductPage) {
            $frontOfficeProductPage->goToProduct((int) $this->getParam('productId'));
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));
        })
        ->it('go through the checkout tunnel', function () use ($frontOfficeCartPage, $frontOfficeCheckoutPage) {
            $frontOfficeCartPage->proceedToCheckout();
            $frontOfficeCheckoutPage->confirmAddresses();
            $frontOfficeCheckoutPage->chooseShipping();
            $frontOfficeCheckoutPage->choosePaymentAndConfirm();
        })
        ->it('reach the order confirmation', function () use ($frontOfficeOrderConfirmationPage) {
            Expect::that($frontOfficeOrderConfirmationPage->isConfirmed())->isTrue();

            $this->store('orderReference', $frontOfficeOrderConfirmationPage->getOrderReference());
        })
        ->it('find the order in the BackOffice', function () use ($backOfficeLoginPage, $backOfficeOrdersPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            $backOfficeOrdersPage->goTo();
            $backOfficeOrdersPage->filterByReference($this->retrieve('orderReference'));

            Expect::that($backOfficeOrdersPage->getOrderReferenceInList(1))
                ->contains($this->retrieve('orderReference'));
        });

        return $testSuite;
    }
}
```
Note: `goToProduct((int) productId)` uses the FrontOffice Product page's URL-based navigation. If it does not resolve on the live shop (friendly-URL constraints seen with the product CRUD), the coordinator swaps it for the canonical-URL approach during live validation (see the integration check).

- [ ] **Step 3: Create the execution suite**

Create `src/Tests/Suites/Scenarios/CheckoutOrder.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class CheckoutOrder extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Log in, add to cart, checkout, and verify the order in the BackOffice')
        ->scenario(\PrestaFlow\Library\Scenarios\CheckoutOrder::class);
    }
}
```

- [ ] **Step 4: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Scenarios/CheckoutOrderTest.php`
Expected: PASS.
Run: `php -l src/Scenarios/CheckoutOrder.php && php -l src/Tests/Suites/Scenarios/CheckoutOrder.php && composer test-unit`
Expected: lint clean, whole suite green.

- [ ] **Step 5: Commit** — await user go-ahead; do NOT `git commit`.

---

## Integration check (LIVE — the real success criterion, run by the coordinator)

Prerequisite (test-env setup, not scenario code): ensure the local PS 9 has a
**customer with a saved address and known password**, at least one **carrier**,
and one **offline payment** enabled. If the default demo customer's password is
unknown, set a known one in the BackOffice (Customers) or create a customer, and
put those values in the `CheckoutOrder` suite/params. Confirm the offline payment
module (bank wire or check) is enabled.

Then, one suite at a time, killing Chrome + clearing the socket between runs:
```bash
pkill -9 -i chrome ; rm -f datas/.broswer datas/.broswer-options
bin/prestaflow run <tmp dir with the CheckoutOrder suite>
```
Expected: FO login → add to cart → tunnel (addresses → shipping → payment + terms
→ place order) → confirmation page (order reference non-empty) → BO login → the
order is found by reference. Green, reproducible.

Reverse-engineer and correct any best-effort selector live (cart checkout button,
tunnel step continue buttons, carrier/payment inputs, terms checkbox, place-order
button, confirmation reference, orders-grid reference column) using chrome-php
probes — the same method used for the product CRUD. Do NOT paper over a broken
step; fix the selector or flow.

## Commits

Explicit owner approval required before any `git commit`. Implementer subagents
must NOT run `git commit`/`git add`; leave changes in the working tree. The
coordinator batches commits once the user approves.
