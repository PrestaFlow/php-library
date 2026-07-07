# Field I/O Robustness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix `CommonPage::getInputValue` (read `.value` in JS) and `selectOption/selectValue` (querySelector + `change`, handles compound selectors, no debug file), add a shared `setValueByJs` helper, and factor the pages' ad-hoc JS workarounds onto them.

**Architecture:** Three targeted changes to `CommonPage` (one read fix, one select fix, one new helper), then delegate `Products::jsSetValue`/`OrderView::readValue` and the select JS blocks in OrderView/Checkout to the fixed core. All are behavioural supersets — they widen coverage without narrowing it. Browser-free structural tests lock the new bodies; a light live smoke confirms no regression.

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (chrome-php).

Spec: `docs/superpowers/specs/2026-07-06-field-io-robustness-design.md`

---

### Task 0: CommonPage core — getInputValue, selectOption, setValueByJs

**Goal:** Correct the two primitives and add the shared JS setter.

**Files:**
- Modify: `src/Pages/CommonPage.php`
- Test: `tests/Unit/Pages/CommonPageIoTest.php` (new)

**Acceptance Criteria:**
- [ ] `getInputValue` reads `.value` via `evaluate` (no `getAttribute('value')`).
- [ ] `selectOption` uses `querySelector` + dispatches `change`; no `file_put_contents`.
- [ ] `CommonPage` has `setValueByJs(string $selector, string $value): void`.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/CommonPageIoTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Write the structural test (red)**

Create `tests/Unit/Pages/CommonPageIoTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;

final class CommonPageIoTest extends TestCase
{
    private function body(string $method): string
    {
        $ref = new \ReflectionMethod(CommonPage::class, $method);
        $lines = file($ref->getFileName());
        return implode('', array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
    }

    public function testGetInputValueReadsValuePropertyViaJs(): void
    {
        $b = $this->body('getInputValue');
        $this->assertStringContainsString('evaluate', $b);
        $this->assertStringContainsString('.value', $b);
        $this->assertStringNotContainsString("getAttribute('value')", $b);
    }

    public function testSelectOptionUsesQuerySelectorAndChange(): void
    {
        $b = $this->body('selectOption');
        $this->assertStringContainsString('querySelector', $b);
        $this->assertStringContainsString('change', $b);
        $this->assertStringNotContainsString('file_put_contents', $b);
    }

    public function testHasSetValueByJs(): void
    {
        $this->assertTrue(method_exists(CommonPage::class, 'setValueByJs'));
    }
}
```
Run: `vendor/bin/phpunit tests/Unit/Pages/CommonPageIoTest.php`
Expected: FAIL — old bodies still use `getAttribute('value')` / `file_put_contents`, no `setValueByJs`.

- [ ] **Step 2: Rewrite `getInputValue`**

In `src/Pages/CommonPage.php`, replace the current `getInputValue()` body (the one doing `$element->getAttribute('value')`) with:
```php
    public function getInputValue($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
    {
        try {
            if ($waitForSelector) {
                $this->getPage()->waitUntilContainsElement($selector, $timeout);
            }
            // Read the live `.value` property (works for <textarea> and for
            // values that differ from the initial `value` attribute).
            $value = $this->getPage()->evaluate(sprintf(
                '(function(){var e=document.querySelector(%s);return e?e.value:null;})()',
                json_encode($selector)
            ))->getReturnValue();
            if ($value === null) {
                return '';
            }
            return trim(str_replace(['&nbsp;'], '', (string) $value));
        } catch (OperationTimedOut | Exception $e) {
            return false;
        }
    }
```

- [ ] **Step 3: Rewrite `selectOption` (+ add `setValueByJs`)**

Replace the current `selectOption()` (the one with the XPath transform and
`file_put_contents('temp.log', ...)`) with:
```php
    public function selectOption($selector, $value)
    {
        // Works with any CSS selector (incl. compound). Select the <option> by
        // its label and fire "change" so JS-enhanced selects (select2) update.
        $found = $this->getPage()->evaluate(sprintf(
            '(function(){var s=document.querySelector(%s);if(!s)return false;'
            . 'var o=[].slice.call(s.options).find(function(x){return x.text.trim()===%s;});'
            . 'if(!o)return false;s.value=o.value;s.dispatchEvent(new Event("change",{bubbles:true}));return true;})()',
            json_encode($selector),
            json_encode($value)
        ))->getReturnValue();

        if ($found !== true) {
            Expect::that($found)->equals(true, 'Option "' . $value . '" not found for selector "' . $selector . '"');
        }
    }
```
Leave `selectValue()` (the alias calling `selectOption`) unchanged. Then add this
new method right after `selectValue()`:
```php
    public function setValueByJs(string $selector, string $value): void
    {
        // Set a value directly via JS (value + input/change). Reliable for fields
        // on inactive tabs or otherwise not focusable, where click+type fails.
        $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);if(e){e.value=%s;'
            . 'e.dispatchEvent(new Event("input",{bubbles:true}));'
            . 'e.dispatchEvent(new Event("change",{bubbles:true}));}})()',
            json_encode($selector),
            json_encode($value)
        ));
    }
```

- [ ] **Step 4: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/CommonPageIoTest.php`
Expected: PASS.
Run: `php -l src/Pages/CommonPage.php && composer test-unit`
Expected: lint clean, whole suite green. (No stray `temp.log` is created by the unit run.)

- [ ] **Step 5: Commit** — await user go-ahead; do NOT `git commit`.

---

### Task 1: Factor the pages onto the fixed core

**Goal:** Remove the duplicated JS workarounds; delegate to the fixed `getInputValue`/`selectValue`/`setValueByJs`.

**Files:**
- Modify: `src/Pages/Common/BackOffice/Products/Page.php`
- Modify: `src/Pages/Common/BackOffice/OrderView/Page.php`
- Modify: `src/Pages/Common/FrontOffice/Checkout/Page.php`
- Test: `tests/Unit/Pages/BackOfficeOrderViewTest.php` (add a small guard)

**Acceptance Criteria:**
- [ ] `Products` no longer defines `jsSetValue`; `createProduct`/`updatePrice` call `$this->setValueByJs(...)`.
- [ ] `OrderView` no longer defines `readValue`; `getInternalNote`/`getTracking` use `getInputValue`; `updateStatus` uses `selectValue`.
- [ ] `Checkout::fillNewAddress` sets the country via `selectValue`.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/BackOfficeOrderViewTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Products — use setValueByJs**

In `src/Pages/Common/BackOffice/Products/Page.php`:
- In `createProduct`, replace the two `$this->jsSetValue(...)` calls (price and
  quantity) with `$this->setValueByJs(...)` (same arguments).
- In `updatePrice`, replace `$this->jsSetValue(...)` with `$this->setValueByJs(...)`.
- Delete the private `jsSetValue()` method entirely.

- [ ] **Step 2: OrderView — use getInputValue + selectValue**

In `src/Pages/Common/BackOffice/OrderView/Page.php`:
- `getInternalNote()`: replace `return $this->readValue($this->getSelector('internalNoteTextarea'));`
  with `return (string) $this->getInputValue($this->getSelector('internalNoteTextarea'));`.
- `getTracking()`: replace `return $this->readValue($this->getSelector('trackingNumberInput'));`
  with `return (string) $this->getInputValue($this->getSelector('trackingNumberInput'));`.
- Delete the private `readValue()` method entirely.
- `updateStatus()`: replace the inline `$this->getPage()->evaluate(...)` block that
  sets the `statusSelect` value + fires change with a single line:
  `$this->selectValue($this->getSelector('statusSelect'), $status);` (keep the
  subsequent `click(updateStatusButton)` + `waitForPageReload()`).
- Leave `getCurrentStatus()` as-is (it reads the selected option via JS — that is
  a read of a specific property, not a plain `.value`).

- [ ] **Step 3: Checkout — country via selectValue**

In `src/Pages/Common/FrontOffice/Checkout/Page.php`, in `fillNewAddress`, replace
the `if (!empty($address['country'])) { ... evaluate(...) ... }` block that
selects the country via JS with:
```php
        if (!empty($address['country'])) {
            $this->selectValue($this->getSelector('addressCountrySelect'), $address['country']);
        }
```

- [ ] **Step 4: Add a guard test**

In `tests/Unit/Pages/BackOfficeOrderViewTest.php`, add:
```php
    public function testUsesSharedIoHelpers(): void
    {
        $class = 'PrestaFlow\\Library\\Pages\\Common\\BackOffice\\OrderView\\Page';
        $this->assertFalse(method_exists($class, 'readValue'), 'readValue should be removed in favour of getInputValue');
    }
```
(This asserts the page-local workaround is gone.)

- [ ] **Step 5: Run tests + lint (green)**

Run: `composer dump-autoload`
Run: `php -l src/Pages/Common/BackOffice/Products/Page.php && php -l src/Pages/Common/BackOffice/OrderView/Page.php && php -l src/Pages/Common/FrontOffice/Checkout/Page.php`
Run: `vendor/bin/phpunit tests/Unit/Pages/BackOfficeOrderViewTest.php && composer test-unit`
Expected: lint clean, whole suite green.

- [ ] **Step 6: Commit** — await user go-ahead; do NOT `git commit`.

---

## Integration check (LIVE — light smoke, run by the coordinator)

When the local Chrome cooperates (`pkill -9 -i chrome` + ~18s settle between
runs), run one suite that exercises each fixed path, confirming no regression:
```bash
pkill -9 -i chrome ; rm -f datas/.broswer datas/.broswer-options
bin/prestaflow run <tmp dir with the EditProduct suite>       # setValueByJs
bin/prestaflow run <tmp dir with the OrderLifecycle suite>    # selectValue (status) + getInputValue (note/tracking)
bin/prestaflow run <tmp dir with the GuestCheckout suite>     # selectValue (country)
```
Expected: each stays green (EditProduct 4/4, OrderLifecycle 9/9, GuestCheckout
4/4). If any regresses, fix before committing. If Chrome is too unstable to run
them this session, rely on the unit suite + the superset nature of the changes and
note the deferred smoke.

## Commits

Explicit owner approval required before any `git commit`. Implementer subagents
must NOT run `git commit`/`git add`; leave changes in the working tree. The
coordinator batches commits once the user approves.
