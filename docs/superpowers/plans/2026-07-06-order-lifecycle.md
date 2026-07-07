# Order Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `ManageOrder` BackOffice scenario that, chained after `CheckoutOrder` in an `OrderLifecycle` suite, opens the created order and changes its status, adds an internal note, and sets a tracking number — each verified.

**Architecture:** New BackOffice `OrderView` page object (order detail) with per-version stubs; `Orders::openOrder()` to reach it; a `ManageOrder` scenario that retrieves the order reference stored by `CheckoutOrder` and drives the OrderView actions; an `OrderLifecycle` suite composing the two scenarios. Selectors are best-effort PS 9, corrected during live validation. Browser-free structural tests lock the surface; the live end-to-end run is the real success criterion.

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (chrome-php). Live shop: PrestaShop 9.0.0-rc.1 at `https://9.0.0-rc.1.test`.

Spec: `docs/superpowers/specs/2026-07-06-order-lifecycle-design.md`

---

## Conventions (read once)

- A new `Common/BackOffice/<Name>/Page.php` needs thin stubs at `v7/`, `v8/`,
  `v9/` (because `importPage('BackOffice\<Name>')` builds
  `Pages\v{N}\BackOffice\<Name>\Page`). Stub shape:
  ```php
  <?php

  namespace PrestaFlow\Library\Pages\v9\BackOffice\<Name>;

  use PrestaFlow\Library\Pages\Common\BackOffice\<Name>\Page as BasePage;

  class Page extends BasePage
  {
  }
  ```
  (identical for v7 and v8, changing the namespace segment).
- Page objects use `$this->getSelector('key')`, `$this->click(...)`,
  `$this->setValue(...)`, `$this->waitForPageReload()`, `$this->getTextContent(...)`,
  and `${row}` token substitution in selectors.
- Selectors listed are **best-effort PS 9**; the coordinator corrects them live.
- A Scenario extends `PrestaFlow\Library\Scenarios\Scenario`, declares `public
  $params`, defines `steps($testSuite)`; inside `it()` closures `$this` rebinds to
  the suite so `getParam()/store()/retrieve()` are available (but NOT at the top of
  `steps()` — there `$this` is the scenario, use `$this->params[...]`).

---

### Task 0: OrderView page + Orders.openOrder

**Goal:** The BackOffice order-detail page object (status / history / note / tracking) plus a way to open an order from the list.

**Files:**
- Create: `src/Pages/Common/BackOffice/OrderView/Page.php` + stubs `src/Pages/v7|v8|v9/BackOffice/OrderView/Page.php`
- Modify: `src/Pages/Common/BackOffice/Orders/Page.php` (add `openOrder`)
- Test: `tests/Unit/Pages/BackOfficeOrderViewTest.php`

**Acceptance Criteria:**
- [ ] `OrderView` declares selectors `statusSelect`, `updateStatusButton`, `historyRows`, `internalNoteTextarea`, `internalNoteSaveButton`, `trackingNumberInput`, `trackingSaveButton` and has methods `getCurrentStatus`, `updateStatus`, `hasStatusInHistory`, `setInternalNote`, `getInternalNote`, `addTracking`, `getTracking`.
- [ ] `Orders` declares `listRowLink` and has `openOrder(int $row = 1)`.
- [ ] v9 classes resolve; `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeOrderViewTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Write the structural test (red)**

Create `tests/Unit/Pages/BackOfficeOrderViewTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class BackOfficeOrderViewTest extends TestCase
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
        $class = 'PrestaFlow\\Library\\Pages\\v9\\BackOffice\\' . $name . '\\Page';
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $this->globals, customs: []);
    }

    public function testOrderViewSelectorsAndMethods(): void
    {
        $page = $this->make('OrderView');
        foreach (['statusSelect', 'updateStatusButton', 'historyRows', 'internalNoteTextarea', 'internalNoteSaveButton', 'trackingNumberInput', 'trackingSaveButton'] as $key) {
            $this->assertArrayHasKey($key, $page->selectors, $key);
        }
        foreach (['getCurrentStatus', 'updateStatus', 'hasStatusInHistory', 'setInternalNote', 'getInternalNote', 'addTracking', 'getTracking'] as $method) {
            $this->assertTrue(method_exists($page, $method), $method);
        }
    }

    public function testOrdersHasOpenOrder(): void
    {
        $page = $this->make('Orders');
        $this->assertArrayHasKey('listRowLink', $page->selectors);
        $this->assertTrue(method_exists($page, 'openOrder'));
    }
}
```
Run: `vendor/bin/phpunit tests/Unit/Pages/BackOfficeOrderViewTest.php`
Expected: FAIL — OrderView class missing, Orders lacks openOrder.

- [ ] **Step 2: Create the OrderView page + stubs**

Create `src/Pages/Common/BackOffice/OrderView/Page.php`:
```php
<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\OrderView;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Order';

    public function defineSelectors()
    {
        return [
            // Best-effort PS 9 order-view (/sell/orders/{id}/view) — corrected live.
            'statusSelect' => '#update_order_status_action_input',
            'updateStatusButton' => '#update_order_status_action_btn',
            'historyRows' => '#orderHistoryTable tbody tr',
            'currentStatusBadge' => '.order-statuses-select .current, .order-status-label',
            'internalNoteTextarea' => '#order_internal_note',
            'internalNoteSaveButton' => '#internal_note_form button[type="submit"]',
            'trackingNumberInput' => '#order_carrier_tracking_number',
            'trackingSaveButton' => '#order_carrier_form button[type="submit"]',
        ];
    }

    public function getCurrentStatus(): string
    {
        return trim($this->getTextContent($this->getSelector('currentStatusBadge')));
    }

    public function updateStatus(string $status): void
    {
        $this->selectOption($this->getSelector('statusSelect'), $status);
        $this->click($this->getSelector('updateStatusButton'));
        $this->waitForPageReload();
    }

    public function hasStatusInHistory(string $status): bool
    {
        return str_contains($this->getTextContent($this->getSelector('historyRows')), $status);
    }

    public function setInternalNote(string $note): void
    {
        $this->setValue($this->getSelector('internalNoteTextarea'), $note);
        $this->click($this->getSelector('internalNoteSaveButton'));
        $this->waitForPageReload();
    }

    public function getInternalNote(): string
    {
        return trim($this->getInputValue($this->getSelector('internalNoteTextarea')));
    }

    public function addTracking(string $number): void
    {
        $this->setValue($this->getSelector('trackingNumberInput'), $number);
        $this->click($this->getSelector('trackingSaveButton'));
        $this->waitForPageReload();
    }

    public function getTracking(): string
    {
        return trim($this->getInputValue($this->getSelector('trackingNumberInput')));
    }
}
```
Create the three stubs `src/Pages/v7|v8|v9/BackOffice/OrderView/Page.php` (namespace `Pages\v{N}\BackOffice\OrderView`, extends the Common OrderView Page), e.g. v9:
```php
<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\OrderView;

use PrestaFlow\Library\Pages\Common\BackOffice\OrderView\Page as BasePage;

class Page extends BasePage
{
}
```

Note: `getInputValue` and `selectOption` already exist on `CommonPage` (used by `setValue` and `selectValue`). If `selectOption` proves unreliable for the select2 during live validation, the coordinator swaps in a JS-based `<select>` change — leave the method signature as-is.

- [ ] **Step 3: Add openOrder to the Orders page**

In `src/Pages/Common/BackOffice/Orders/Page.php`, add `'listRowLink'` to `defineSelectors()` (after `listRowReference`) and add the method after `getOrderReferenceInList()`:
```php
            'listRowLink' => '#order_grid_table tbody tr:nth-child(${row}) a.link-row-action, #order_grid_table tbody tr:nth-child(${row}) a',
```
```php
    public function openOrder(int $row = 1): void
    {
        $this->click($this->getSelector('listRowLink', ['row' => $row]));
        $this->waitForPageReload();
    }
```

- [ ] **Step 4: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/BackOfficeOrderViewTest.php`
Expected: PASS.
Run: `php -l src/Pages/Common/BackOffice/OrderView/Page.php && php -l src/Pages/Common/BackOffice/Orders/Page.php && composer test-unit`
Expected: lint clean, whole suite green.

- [ ] **Step 5: Commit** — await user go-ahead; do NOT `git commit`.

---

### Task 1: ManageOrder scenario + OrderLifecycle suite + tests

**Goal:** The BackOffice management scenario and the suite that composes CheckoutOrder → ManageOrder, plus browser-free existence/param tests.

**Files:**
- Create: `src/Scenarios/ManageOrder.php`
- Create: `src/Tests/Suites/Scenarios/OrderLifecycle.php`
- Test: `tests/Unit/Scenarios/ManageOrderTest.php`

**Acceptance Criteria:**
- [ ] `ManageOrder` extends `Scenario`, declares params `orderStatus`, `internalNote`, `trackingNumber`, `locale`, and drives openOrder → updateStatus/setInternalNote/addTracking with assertions.
- [ ] `OrderLifecycle` suite extends `TestsSuite` and composes both scenarios.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Scenarios/ManageOrderTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Write the structural test (red)**

Create `tests/Unit/Scenarios/ManageOrderTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class ManageOrderTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\ManageOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testDeclaresManagementParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\ManageOrder');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['orderStatus', 'internalNote', 'trackingNumber'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }

    public function testSuiteComposesBothScenarios(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\OrderLifecycle';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
        $ref = new \ReflectionMethod($class, 'init');
        $body = implode('', array_slice(file($ref->getFileName()), $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
        $this->assertStringContainsString('CheckoutOrder', $body);
        $this->assertStringContainsString('ManageOrder', $body);
    }
}
```
Run: `vendor/bin/phpunit tests/Unit/Scenarios/ManageOrderTest.php`
Expected: FAIL — classes do not exist.

- [ ] **Step 2: Create the ManageOrder scenario**

Create `src/Scenarios/ManageOrder.php`:
```php
<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class ManageOrder extends Scenario
{
    public $params = [
        'locale' => 'fr',
        // Status name as shown in the BO status dropdown (FR demo shop).
        'orderStatus' => 'Paiement accepté',
        'internalNote' => 'PrestaFlow internal note',
        'trackingNumber' => 'PF-TRACK-0001',
        // Optional: order reference to manage; defaults to the one stored by a
        // preceding CheckoutOrder scenario.
        'orderReference' => null,
    ];

    public function steps($testSuite)
    {
        $testSuite->params['locale'] = $this->params['locale'] ?? 'fr';

        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Orders');
        $testSuite->importPage('BackOffice\OrderView');

        extract($testSuite->pages);

        $testSuite
        ->it('open the order in the BackOffice', function () use ($backOfficeLoginPage, $backOfficeOrdersPage) {
            $reference = $this->retrieve('orderReference') ?? $this->getParam('orderReference');

            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            $backOfficeOrdersPage->goTo();
            $backOfficeOrdersPage->filterByReference($reference);
            $backOfficeOrdersPage->openOrder(1);
        })
        ->it('change the order status', function () use ($backOfficeOrderViewPage) {
            $backOfficeOrderViewPage->updateStatus($this->getParam('orderStatus'));

            Expect::that($backOfficeOrderViewPage->hasStatusInHistory($this->getParam('orderStatus')))->equals(true);
        })
        ->it('add an internal note', function () use ($backOfficeOrderViewPage) {
            $backOfficeOrderViewPage->setInternalNote($this->getParam('internalNote'));

            Expect::that($backOfficeOrderViewPage->getInternalNote())->contains($this->getParam('internalNote'));
        })
        ->it('set a tracking number', function () use ($backOfficeOrderViewPage) {
            $backOfficeOrderViewPage->addTracking($this->getParam('trackingNumber'));

            Expect::that($backOfficeOrderViewPage->getTracking())->contains($this->getParam('trackingNumber'));
        });

        return $testSuite;
    }
}
```

- [ ] **Step 3: Create the OrderLifecycle suite**

Create `src/Tests/Suites/Scenarios/OrderLifecycle.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class OrderLifecycle extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Create an order then manage its lifecycle in the BackOffice')
        ->scenario(\PrestaFlow\Library\Scenarios\CheckoutOrder::class)
        ->scenario(\PrestaFlow\Library\Scenarios\ManageOrder::class);
    }
}
```

- [ ] **Step 4: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Scenarios/ManageOrderTest.php`
Expected: PASS.
Run: `php -l src/Scenarios/ManageOrder.php && php -l src/Tests/Suites/Scenarios/OrderLifecycle.php && composer test-unit`
Expected: lint clean, whole suite green.

- [ ] **Step 5: Commit** — await user go-ahead; do NOT `git commit`.

---

## Integration check (LIVE — the real success criterion, run by the coordinator)

Against the local PS 9 (`.env.local` configured, `datas/` exists), one suite at a
time, killing Chrome + clearing the socket between runs:
```bash
pkill -9 -i chrome ; rm -f datas/.broswer datas/.broswer-options
bin/prestaflow run <tmp dir with the OrderLifecycle suite>
```
Expected: `CheckoutOrder` creates the order; `ManageOrder` opens it, changes the
status (the new status appears in the history), saves the internal note (read
back), and sets the tracking number (read back). Green, reproducible.

Reverse-engineer and correct any best-effort selector live: the order-view status
select (a select2 — may need a JS `<select>` change + change event), the update
button, the history table rows, the internal-note textarea + its save control,
and the tracking-number field + save (the **tracking is the fragile part** — it
may sit behind a carrier-block edit or a modal). If the tracking step proves too
brittle, land status + note first and iterate on tracking — do NOT paper over a
broken step.

## Commits

Explicit owner approval required before any `git commit`. Implementer subagents
must NOT run `git commit`/`git add`; leave changes in the working tree. The
coordinator batches commits once the user approves.
