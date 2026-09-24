# One Page Checkout (PrestaShop 9.2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give PrestaFlow a page object, two back-office configuration pages and two scenarios covering the One Page Checkout shipped as the `ps_onepagecheckout` module in PrestaShop 9.2.

**Architecture:** A new `v9\FrontOffice\OnePageCheckout` page object drives the OPC DOM, which is entirely different from the 5-step tunnel and refreshes its sections over AJAX. Because the module is off by default and guest checkout is a separate setting, two back-office page objects (`CheckoutLayout`, `OrderSettings`) put the shop into the required state before the front-office steps. A small `waitForCondition()` helper on `CommonPage` lets page methods wait on *state* (a rendered carrier list, an enabled pay button) rather than on navigation or fixed delays.

**Tech Stack:** PHP 8.2+, chrome-php/chrome (headless Chrome), PHPUnit 10 for unit tests, the `bin/prestaflow` runner for suites. Reference shop: the running container `ps92rc1-web-1` (PrestaShop 9.2.0) on `http://localhost:8092`.

**Spec:** `docs/superpowers/specs/2026-09-23-one-page-checkout-design.md`

**On commits:** each task ends with a suggested commit boundary. Per the project's standing rule, **do not run `git commit` without the user's explicit instruction** — stage nothing on your own initiative; the commit steps document where a commit belongs, not permission to make one.

---

## File Structure

| File | Responsibility |
|---|---|
| `src/Pages/CommonPage.php` (modify) | Add `waitForCondition()` — the only shared-code change |
| `src/Pages/v9/FrontOffice/OnePageCheckout/Page.php` (create) | OPC DOM: selectors + checkout actions |
| `src/Pages/v9/BackOffice/CheckoutLayout/Page.php` (create) | Module configuration: switch between one-page and four-page layout |
| `src/Pages/v9/BackOffice/OrderSettings/Page.php` (create) | Shop Parameters > Order Settings: guest checkout switch |
| `src/Scenarios/OnePageCheckoutOrder.php` (create) | Logged-in customer path |
| `src/Scenarios/OnePageCheckoutGuest.php` (create) | Guest path |
| `src/Tests/Suites/Scenarios/OnePageCheckoutOrder.php` (create) | Runnable suite for the logged-in path |
| `src/Tests/Suites/Scenarios/OnePageCheckoutGuest.php` (create) | Runnable suite for the guest path |
| `tests/Unit/Pages/WaitForConditionTest.php` (create) | Behavioural test of the polling helper |
| `tests/Unit/Pages/OnePageCheckoutPageTest.php` (create) | Structural test of the OPC page object |
| `tests/Unit/Scenarios/OnePageCheckoutOrderTest.php` (create) | Structural test of the logged-in scenario |
| `tests/Unit/Scenarios/OnePageCheckoutGuestTest.php` (create) | Structural test of the guest scenario |

No `v7`/`v8` delegates: the OPC does not exist before 9.2. `src/Pages/v9/FrontOffice/Checkout/Page.php` is not modified.

---

### Task 1: `CommonPage::waitForCondition`

**Goal:** A reusable helper that polls a JavaScript expression until it is true or a timeout expires, so page objects can wait on state instead of on navigation.

**Files:**
- Modify: `src/Pages/CommonPage.php` (add a method next to `waitForPageReload`, around line 391)
- Test: `tests/Unit/Pages/WaitForConditionTest.php`

**Why this exists:** the existing helpers cover presence (`elementIsVisible`, backed by `waitUntilContainsElement`) and navigation (`waitForPageReload`). The OPC needs "the pay button is no longer disabled" and "the carrier list has been rendered" — neither is a presence check nor a navigation.

**Acceptance Criteria:**
- [ ] `waitForCondition(string $jsExpression, int $timeout = 10000, int $interval = 200): bool` exists on `CommonPage`
- [ ] Returns `true` as soon as the expression evaluates truthy, and stops polling at that point
- [ ] Returns `false` when the timeout expires without the expression ever being truthy
- [ ] A JavaScript error inside the expression counts as "not yet true" instead of propagating
- [ ] An exception thrown by `evaluate()` (page busy during a navigation) counts as "not yet true"

**Verify:** `vendor/bin/phpunit --testsuite Unit --filter WaitForCondition` → `OK (3 tests)`

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Pages/WaitForConditionTest.php`:

```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;

/**
 * Test double: CommonPage::getPage() normally returns the shared headless-Chrome
 * page through TestsSuite. Here it returns a fake that replays a scripted list of
 * evaluate() results, so the polling logic can be tested without a browser.
 */
final class FakeConditionPage extends CommonPage
{
    /** @var array<int, mixed> results replayed by successive evaluate() calls */
    public array $results = [];
    public int $calls = 0;
    /** @var bool when true, the first evaluate() call throws */
    public bool $throwOnFirstCall = false;

    // Deliberately bypasses the parent constructor: no locale, no globals, no browser.
    public function __construct()
    {
    }

    public function getPage()
    {
        return new class ($this) {
            public function __construct(private FakeConditionPage $owner)
            {
            }

            public function evaluate(string $js)
            {
                $index = $this->owner->calls;
                $this->owner->calls++;

                if ($this->owner->throwOnFirstCall && $index === 0) {
                    throw new \RuntimeException('page is navigating');
                }

                $value = $this->owner->results[$index] ?? false;

                return new class ($value) {
                    public function __construct(private mixed $value)
                    {
                    }

                    public function getReturnValue(): mixed
                    {
                        return $this->value;
                    }
                };
            }
        };
    }
}

final class WaitForConditionTest extends TestCase
{
    public function testReturnsTrueAndStopsPollingOnFirstTruthyResult(): void
    {
        $page = new FakeConditionPage();
        $page->results = [false, false, true, true];

        $this->assertTrue($page->waitForCondition('window.ready === true', 2000, 20));
        $this->assertSame(3, $page->calls, 'polling must stop as soon as the condition is met');
    }

    public function testReturnsFalseWhenTimeoutExpires(): void
    {
        $page = new FakeConditionPage();
        $page->results = [];

        $this->assertFalse($page->waitForCondition('window.ready === true', 200, 20));
        $this->assertGreaterThan(1, $page->calls, 'the helper must poll more than once before giving up');
    }

    public function testEvaluateExceptionCountsAsNotYetTrue(): void
    {
        $page = new FakeConditionPage();
        $page->throwOnFirstCall = true;
        $page->results = [null, true];

        $this->assertTrue($page->waitForCondition('window.ready === true', 2000, 20));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit --testsuite Unit --filter WaitForCondition
```

Expected: FAIL — `Call to undefined method ... ::waitForCondition()`.

- [ ] **Step 3: Implement the helper**

In `src/Pages/CommonPage.php`, immediately after the `waitForPageReload()` method, add:

```php
    /**
     * Poll a JS expression until it is truthy or the timeout expires.
     *
     * Complements waitForPageReload() (navigation) and elementIsVisible()
     * (presence) with a wait on *state*: a section that finished rendering over
     * AJAX, a button that lost its disabled attribute. Returns whether the
     * condition was met, so callers choose between asserting and branching.
     *
     * @param string $jsExpression a JS expression, evaluated for truthiness
     * @param int    $timeout      total budget in milliseconds
     * @param int    $interval     delay between two polls, in milliseconds
     */
    public function waitForCondition(string $jsExpression, int $timeout = 10000, int $interval = 200): bool
    {
        $deadline = microtime(true) + ($timeout / 1000);

        do {
            try {
                // The expression is wrapped in its own try/catch: a TypeError on a
                // node that is not in the DOM yet means "not ready", not "failed".
                $met = $this->getPage()->evaluate(
                    '(function(){try{return !!(' . $jsExpression . ');}catch(e){return false;}})()'
                )->getReturnValue();

                if ($met === true) {
                    return true;
                }
            } catch (\Throwable $e) {
                // The page is busy (navigation in flight, target detached): retry
                // until the deadline rather than failing the whole step.
            }

            usleep($interval * 1000);
        } while (microtime(true) < $deadline);

        return false;
    }
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit --testsuite Unit --filter WaitForCondition
```

Expected: `OK (3 tests, 5 assertions)`.

- [ ] **Step 5: Run the whole unit suite for regressions**

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: all tests pass, no test count decrease compared to before the change.

- [ ] **Step 6: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Pages/CommonPage.php tests/Unit/Pages/WaitForConditionTest.php
git commit -m "feat(pages): waitForCondition helper polling a JS expression until true"
```

---

### Task 2: FrontOffice `OnePageCheckout` page object

**Goal:** A page object exposing the OPC checkout as a handful of intent-level methods, each waiting on the state its AJAX round-trip produces.

**Files:**
- Create: `src/Pages/v9/FrontOffice/OnePageCheckout/Page.php`
- Test: `tests/Unit/Pages/OnePageCheckoutPageTest.php`

**Source of truth for selectors:** the module's own map, `modules/ps_onepagecheckout/views/js/selectors.js`, plus the templates under `views/templates/front/checkout/_partials/one-page-checkout/`.

**Behaviour confirmed by reading the module's JS** (`opc-address.js`, `opc-submit.js`):
- address fields autosave on `input`/`change`, debounced — nothing to click to persist them;
- changing `id_country` re-renders the address form, so the country must be set *before* the other fields;
- `#opc-pay-button` is enabled by `validateForm()` only once address, carrier, payment and terms are all valid;
- the final submit is a `fetch` followed by `window.location.href`, i.e. a real navigation.

**Note on the spec:** `selectAddress()` takes a nullable id (`selectAddress(?int $idAddress = null)`). Passing `null` picks the first address card, which keeps scenarios free of hardcoded fixture ids. This is a small widening of the API described in the spec, for the same purpose.

**Acceptance Criteria:**
- [ ] Class `PrestaFlow\Library\Pages\v9\FrontOffice\OnePageCheckout\Page` extends `PrestaFlow\Library\Pages\Common\FrontOffice\Page`
- [ ] `$url` is `'order'` and `$pageTitle` is `'Checkout'`
- [ ] `defineSelectors()` returns every key used by the methods below
- [ ] Public methods: `isOnePageCheckoutActive`, `continueAsGuest`, `selectAddress`, `fillNewAddress`, `selectFirstCarrier`, `selectFirstPayment`, `acceptTerms`, `placeOrder`
- [ ] No method uses `sleep()`; every AJAX wait goes through `waitForCondition()`

**Verify:** `vendor/bin/phpunit --testsuite Unit --filter OnePageCheckoutPage` → `OK (3 tests)`

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Pages/OnePageCheckoutPageTest.php`:

```php
<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class OnePageCheckoutPageTest extends TestCase
{
    private const PAGE_CLASS = 'PrestaFlow\\Library\\Pages\\v9\\FrontOffice\\OnePageCheckout\\Page';

    public function testPageExistsAndExtendsFrontOfficeBasePage(): void
    {
        $this->assertTrue(class_exists(self::PAGE_CLASS));
        $this->assertTrue(is_subclass_of(self::PAGE_CLASS, 'PrestaFlow\\Library\\Pages\\Common\\FrontOffice\\Page'));
    }

    public function testPageTargetsTheOrderController(): void
    {
        $defaults = (new \ReflectionClass(self::PAGE_CLASS))->getDefaultProperties();
        $this->assertSame('order', $defaults['url']);
        $this->assertSame('Checkout', $defaults['pageTitle']);
    }

    public function testPageExposesTheCheckoutActions(): void
    {
        foreach ([
            'isOnePageCheckoutActive',
            'continueAsGuest',
            'selectAddress',
            'fillNewAddress',
            'selectFirstCarrier',
            'selectFirstPayment',
            'acceptTerms',
            'placeOrder',
        ] as $method) {
            $this->assertTrue(method_exists(self::PAGE_CLASS, $method), $method);
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit --testsuite Unit --filter OnePageCheckoutPage
```

Expected: FAIL — the class does not exist.

- [ ] **Step 3: Create the page object**

Create `src/Pages/v9/FrontOffice/OnePageCheckout/Page.php`:

```php
<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\OnePageCheckout;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

/**
 * One Page Checkout, shipped as the ps_onepagecheckout module since PrestaShop 9.2.
 *
 * Same controller as the classic tunnel ('order'): the module takes over through
 * actionCheckoutBuildProcess and displayOverrideTemplate, so there is no new route.
 * The DOM, however, is entirely different, and the sections refresh over AJAX —
 * hence the state-based waits instead of waitForPageReload().
 *
 * Selectors come from the module's own map, views/js/selectors.js.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Checkout';
    public string $url = 'order';

    public function defineSelectors()
    {
        return [
            'opcForm' => '#opc-form',
            // Contact section (guest only; a logged-in customer sees their account info)
            'guestEmailInput' => '#field-email',
            // Addresses: existing ones as radio cards, or an inline form when there is none
            'addressList' => '#opc-delivery-address-content-list',
            'addressRadio' => '#opc-delivery-address-content-list .js-opc-address-radio',
            'addressRadioById' => '#opc-address-delivery-${idAddress}',
            'addressFields' => '#opc-delivery-address-fields',
            'addressCountrySelect' => '#opc-delivery-address-fields select[name="id_country"]',
            'addressFirstNameInput' => '#opc-delivery-address-fields input[name="firstname"]',
            'addressLastNameInput' => '#opc-delivery-address-fields input[name="lastname"]',
            'addressStreetInput' => '#opc-delivery-address-fields input[name="address1"]',
            'addressPostcodeInput' => '#opc-delivery-address-fields input[name="postcode"]',
            'addressCityInput' => '#opc-delivery-address-fields input[name="city"]',
            'addressPhoneInput' => '#opc-delivery-address-fields input[name="phone"]',
            // Delivery
            'deliveryMethods' => '#opc-delivery-methods',
            'carrierOption' => '#opc-delivery-methods input[name="delivery_option"]',
            // Payment
            'paymentMethods' => '#opc-payment-methods',
            'paymentOption' => '#opc-payment-methods input[name="payment-option"]',
            // Footer
            'termsCheckbox' => '#conditions-to-approve input[type="checkbox"]',
            'payButton' => '#opc-pay-button',
        ];
    }

    /**
     * Whether the shop currently renders the One Page Checkout.
     *
     * Doubles as an assertion after switching the layout in the back office and as
     * a branch point for a caller that supports both checkouts.
     */
    public function isOnePageCheckoutActive(): bool
    {
        return $this->elementIsVisible($this->getSelector('opcForm'), 5000);
    }

    /**
     * Guest path: fill the contact e-mail. The module creates the guest customer
     * in the background (guestinit controller), so we wait for the address form
     * to be ready rather than for a navigation.
     */
    public function continueAsGuest(string $email): void
    {
        $this->setValueByJs($this->getSelector('guestEmailInput'), $email);

        $this->waitForCondition(
            'document.querySelector("#opc-delivery-address-fields") '
            . '&& !document.querySelector("#opc-delivery-address-fields").classList.contains("d-none")'
        );
    }

    /**
     * Pick an existing address card. Without an id, the first card is used, which
     * keeps callers free of hardcoded fixture ids.
     *
     * Selecting an address triggers the carrier fetch, so we wait for the carrier
     * list rather than returning while the section still shows its loader.
     */
    public function selectAddress(?int $idAddress = null): void
    {
        $selector = $idAddress === null
            ? $this->getSelector('addressRadio')
            : $this->getSelector('addressRadioById', ['idAddress' => $idAddress]);

        $this->click($selector);

        $this->waitForCarriers();
    }

    /**
     * Fill the inline address form (guest, or a customer with no address yet).
     *
     * Country goes first on purpose: changing id_country re-renders the whole form
     * from the back end (address format per country), which would wipe fields set
     * before it. The other fields autosave on input, debounced by the module, so
     * there is no save button to click — the carrier list appearing is the signal
     * that the address was persisted.
     *
     * @param array{firstName?:string,lastName?:string,street?:string,postcode?:string,city?:string,country?:string,phone?:string} $address
     */
    public function fillNewAddress(array $address): void
    {
        if (!empty($address['country'])) {
            $this->selectValue($this->getSelector('addressCountrySelect'), $address['country']);
            // The re-render replaces the inputs: wait for the street field to be
            // back in the DOM before typing into it.
            $this->waitForCondition('!!document.querySelector("#opc-delivery-address-fields input[name=\'address1\']")');
        }

        foreach ([
            'addressFirstNameInput' => $address['firstName'] ?? '',
            'addressLastNameInput' => $address['lastName'] ?? '',
            'addressStreetInput' => $address['street'] ?? '',
            'addressPostcodeInput' => $address['postcode'] ?? '',
            'addressCityInput' => $address['city'] ?? '',
            'addressPhoneInput' => $address['phone'] ?? '',
        ] as $selectorKey => $value) {
            if ($value === '') {
                continue;
            }

            $this->setValueByJs($this->getSelector($selectorKey), $value);
        }

        $this->waitForCarriers();
    }

    /**
     * Select the first available carrier, then wait for the payment options the
     * module fetches in response.
     */
    public function selectFirstCarrier(): void
    {
        $this->waitForCarriers();
        $this->click($this->getSelector('carrierOption'));

        $this->waitForCondition('!!document.querySelector("#opc-payment-methods input[name=\'payment-option\']")');
    }

    /**
     * Select the first payment option.
     */
    public function selectFirstPayment(): void
    {
        $this->click($this->getSelector('paymentOption'));
    }

    /**
     * Tick every required terms checkbox, then wait for the pay button to be
     * enabled: the module's validateForm() only enables it once address, carrier,
     * payment and terms are all valid, which makes it the single reliable signal
     * that the checkout is ready to submit.
     */
    public function acceptTerms(): void
    {
        $this->getPage()->evaluate(
            '(function(){[].slice.call(document.querySelectorAll("#conditions-to-approve input[type=checkbox]"))'
            . '.forEach(function(c){if(!c.checked){c.click();}});})()'
        );

        $this->waitForCondition('!document.querySelector("#opc-pay-button").disabled');
    }

    /**
     * Submit the order. The module posts over fetch and then navigates with
     * window.location.href, so this is a real navigation.
     */
    public function placeOrder(): void
    {
        $this->click($this->getSelector('payButton'));
        $this->waitForPageReload();
    }

    /**
     * Wait for the carrier list to be rendered inside its placeholder. The section
     * starts as an "awaiting address" message and is replaced once the address is
     * known, so we wait for a radio input, not for the container.
     */
    private function waitForCarriers(): void
    {
        $this->waitForCondition('!!document.querySelector("#opc-delivery-methods input[name=\'delivery_option\']")');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit --testsuite Unit --filter OnePageCheckoutPage
```

Expected: `OK (3 tests, 11 assertions)`.

- [ ] **Step 5: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Pages/v9/FrontOffice/OnePageCheckout tests/Unit/Pages/OnePageCheckoutPageTest.php
git commit -m "feat(pages): FrontOffice OnePageCheckout page object for PS 9.2"
```

---

### Task 3: BackOffice `CheckoutLayout` page object

**Goal:** Switch the shop between the one-page and the four-page checkout from the module's back-office configuration, confirmation modal included.

**Files:**
- Create: `src/Pages/v9/BackOffice/CheckoutLayout/Page.php`

**Navigation:** the module registers the admin tab `AdminPsOnePageCheckout` under `AdminParentThemes` (verified in `ps_tab`), so the page is reachable through the sidebar exactly like `BackOffice\Carriers`. Going through `goToSubMenu()` reads the anchor's resolved `href`, which carries a valid security token — navigating to a legacy admin controller by hand would hit the "Invalid security token" page.

**Form, from `views/templates/admin/checkout_layout_configuration.html.twig`:** a radio pair `#PS_ONE_PAGE_CHECKOUT_ENABLED_one_page` / `#PS_ONE_PAGE_CHECKOUT_ENABLED_four_page`, a `#psopc-save-btn` button, then a confirmation modal `#psopc-confirmation-modal` whose `#psopc-confirm-btn` actually submits. The modal's maintenance checkbox `#psopc-maintenance-toggle` is left untouched so the front office stays reachable for the steps that follow.

**Acceptance Criteria:**
- [ ] Class `PrestaFlow\Library\Pages\v9\BackOffice\CheckoutLayout\Page` extends `PrestaFlow\Library\Pages\Common\BackOffice\Page`
- [ ] `goTo()` navigates through the sidebar (`#subtab-AdminParentThemes` → `#subtab-AdminPsOnePageCheckout`)
- [ ] `switchToOnePageCheckout()` and `switchToFourPageCheckout()` select the radio, save, and confirm in the modal
- [ ] The maintenance toggle is never clicked

**Verify:** `vendor/bin/phpunit --testsuite Unit` → still green (this task adds no unit test of its own; it is covered end-to-end in Task 7)

**Steps:**

- [ ] **Step 1: Create the page object**

Create `src/Pages/v9/BackOffice/CheckoutLayout/Page.php`:

```php
<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\CheckoutLayout;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

/**
 * ps_onepagecheckout configuration screen (PrestaShop 9.2+).
 *
 * The module installs the tab AdminPsOnePageCheckout under Design
 * (AdminParentThemes), so the sidebar is the way in: goToSubMenu() reads the
 * anchor's href, which carries a valid token, where a hand-built URL to a legacy
 * admin controller would be rejected.
 *
 * Saving is a two-step affair: the save button opens a confirmation modal, and
 * only the modal's button submits the form.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Checkout';
    public string $menuSelector = '#subtab-AdminPsOnePageCheckout';
    public string $parentMenuSelector = '#subtab-AdminParentThemes';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'configurationBlock' => '.psopc-configuration',
            'onePageChoice' => '#PS_ONE_PAGE_CHECKOUT_ENABLED_one_page',
            'fourPageChoice' => '#PS_ONE_PAGE_CHECKOUT_ENABLED_four_page',
            'saveButton' => '#psopc-save-btn',
            'confirmationModal' => '#psopc-confirmation-modal',
            'confirmButton' => '#psopc-confirm-btn',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    public function switchToOnePageCheckout(): void
    {
        $this->applyLayout($this->getSelector('onePageChoice'));
    }

    public function switchToFourPageCheckout(): void
    {
        $this->applyLayout($this->getSelector('fourPageChoice'));
    }

    /**
     * Select a layout, open the confirmation modal and confirm.
     *
     * The maintenance checkbox inside the modal is deliberately left alone: turning
     * it on would close the front office to the checkout steps that come next.
     */
    private function applyLayout(string $choiceSelector): void
    {
        $this->click($choiceSelector);
        $this->click($this->getSelector('saveButton'));

        // The modal fades in; waiting for it to be shown avoids clicking through
        // the backdrop while the animation is still running.
        $this->waitForCondition(
            'document.querySelector("#psopc-confirmation-modal") '
            . '&& document.querySelector("#psopc-confirmation-modal").classList.contains("show")'
        );

        $this->click($this->getSelector('confirmButton'));
        $this->waitForPageReload();
    }
}
```

- [ ] **Step 2: Verify the file parses and the class autoloads**

```bash
php -l src/Pages/v9/BackOffice/CheckoutLayout/Page.php && php -r 'require "vendor/autoload.php"; var_dump(class_exists("PrestaFlow\\Library\\Pages\\v9\\BackOffice\\CheckoutLayout\\Page"));'
```

Expected: `No syntax errors detected` then `bool(true)`.

- [ ] **Step 3: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Pages/v9/BackOffice/CheckoutLayout
git commit -m "feat(pages): BackOffice CheckoutLayout page driving the ps_onepagecheckout switch"
```

---

### Task 4: BackOffice `OrderSettings` page object

**Goal:** Turn guest checkout on or off from Shop Parameters > Order Settings, which the guest scenario needs.

**Files:**
- Create: `src/Pages/v9/BackOffice/OrderSettings/Page.php`

**Form:** `PS_GUEST_CHECKOUT_ENABLED` is bound to the field `enable_guest_checkout` of `OrderPreferences\GeneralType`, rendered inside `#configuration_general_form` and saved by `#form-general-save-button`. PrestaShop's `SwitchType` renders a pair of radios named `<form_prefix>[enable_guest_checkout]` with values `1` and `0`. The selector below matches on the field name suffix instead of the generated id prefix, so a change to the form's block prefix does not break it.

**Acceptance Criteria:**
- [ ] Class `PrestaFlow\Library\Pages\v9\BackOffice\OrderSettings\Page` extends `PrestaFlow\Library\Pages\Common\BackOffice\Page`
- [ ] `goTo()` navigates through the sidebar (`#subtab-AdminParentOrderPreferences` → `#subtab-AdminOrderPreferences`)
- [ ] `setGuestCheckout(bool $enabled)` selects the matching radio and saves
- [ ] `isGuestCheckoutEnabled()` reports the current state, so a scenario can skip a needless save

**Verify:** `vendor/bin/phpunit --testsuite Unit` → still green (covered end-to-end in Task 7)

**Steps:**

- [ ] **Step 1: Create the page object**

Create `src/Pages/v9/BackOffice/OrderSettings/Page.php`:

```php
<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\OrderSettings;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

/**
 * Shop Parameters > Order Settings.
 *
 * Only carries what the One Page Checkout scenarios need for now: the guest
 * checkout switch (PS_GUEST_CHECKOUT_ENABLED), which lives here and not in the
 * ps_onepagecheckout configuration.
 *
 * The switch is a pair of radios rendered by PrestaShop's SwitchType. Matching on
 * the field-name suffix rather than on the generated id keeps the selector stable
 * across form block-prefix changes.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Order settings';
    public string $menuSelector = '#subtab-AdminOrderPreferences';
    public string $parentMenuSelector = '#subtab-AdminParentOrderPreferences';

    public function defineSelectors()
    {
        return [
            'pageHeading' => '.page-title',
            'generalForm' => '#configuration_general_form',
            'guestCheckoutRadio' => '#configuration_general_form input[type="radio"][name$="[enable_guest_checkout]"][value="${value}"]',
            'saveButton' => '#form-general-save-button',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    public function isGuestCheckoutEnabled(): bool
    {
        return $this->getPage()->evaluate(
            '(function(){var e=document.querySelector('
            . '"#configuration_general_form input[type=radio][name$=\'[enable_guest_checkout]\'][value=\'1\']");'
            . 'return !!(e && e.checked);})()'
        )->getReturnValue() === true;
    }

    public function setGuestCheckout(bool $enabled): void
    {
        if ($this->isGuestCheckoutEnabled() === $enabled) {
            return;
        }

        $this->click($this->getSelector('guestCheckoutRadio', ['value' => $enabled ? '1' : '0']));
        $this->click($this->getSelector('saveButton'));
        $this->waitForPageReload();
    }
}
```

- [ ] **Step 2: Verify the file parses and the class autoloads**

```bash
php -l src/Pages/v9/BackOffice/OrderSettings/Page.php && php -r 'require "vendor/autoload.php"; var_dump(class_exists("PrestaFlow\\Library\\Pages\\v9\\BackOffice\\OrderSettings\\Page"));'
```

Expected: `No syntax errors detected` then `bool(true)`.

- [ ] **Step 3: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Pages/v9/BackOffice/OrderSettings
git commit -m "feat(pages): BackOffice OrderSettings page with the guest checkout switch"
```

---

### Task 5: `OnePageCheckoutOrder` scenario (logged-in customer)

**Goal:** A runnable scenario that switches the shop to the one-page layout and places an order as a logged-in customer, skipping cleanly below PrestaShop 9.2.

**Files:**
- Create: `src/Scenarios/OnePageCheckoutOrder.php`
- Create: `src/Tests/Suites/Scenarios/OnePageCheckoutOrder.php`
- Test: `tests/Unit/Scenarios/OnePageCheckoutOrderTest.php`

**Version guard:** `getMinorVersion()` returns `"9.2"` for a 9.2.x shop (`src/Traits/Version.php:170`). Below that, the scenario registers a single skipped test through `$testSuite->skip()` and returns, instead of failing on a selector that will never exist.

**No teardown:** per the spec, the scenario does not restore the previous checkout layout. It sets the state it needs up front, which is what keeps scenarios independent of execution order.

**Acceptance Criteria:**
- [ ] Class `PrestaFlow\Library\Scenarios\OnePageCheckoutOrder` extends `Scenario`
- [ ] `params` declares `locale`, `customerEmail`, `customerPassword`, `productUrl`, `cartQuantity`
- [ ] Below 9.2, the scenario produces exactly one skipped test and touches no page
- [ ] On 9.2+, it logs into the back office, switches the layout, logs into the front office, adds a product, checks out and asserts the confirmation

**Verify:** `vendor/bin/phpunit --testsuite Unit --filter OnePageCheckoutOrder` → `OK (3 tests)`

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Scenarios/OnePageCheckoutOrderTest.php`:

```php
<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class OnePageCheckoutOrderTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\OnePageCheckoutOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\OnePageCheckoutOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresItsParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\OnePageCheckoutOrder');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['locale', 'customerEmail', 'customerPassword', 'productUrl', 'cartQuantity'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit --testsuite Unit --filter OnePageCheckoutOrder
```

Expected: FAIL — the classes do not exist.

- [ ] **Step 3: Create the scenario**

Create `src/Scenarios/OnePageCheckoutOrder.php`:

```php
<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Place an order through the One Page Checkout as a logged-in customer.
 *
 * The OPC ships as the ps_onepagecheckout module in PrestaShop 9.2 and is off by
 * default, so the scenario switches the shop to the one-page layout in the back
 * office before touching the front office. It does not restore the previous
 * layout: each scenario sets the state it needs, which is what keeps them
 * independent of execution order.
 */
class OnePageCheckoutOrder extends Scenario
{
    public $params = [
        // The reference shop (ps92rc1 container) runs in English.
        'locale' => 'en',
        'customerEmail' => 'pub@prestashop.com',
        'customerPassword' => 'prestashop_demo',
        // Canonical product path — friendly URLs can't be rebuilt from an id.
        // A product with NO combinations: add-to-cart on a product that has
        // them returns "no longer available" unless an id_product_attribute is
        // posted too.
        'productUrl' => '6-mug-the-best-is-yet-to-come.html',
        'cartQuantity' => 1,
    ];

    public function steps($testSuite)
    {
        // The One Page Checkout module does not exist before 9.2: skip with an
        // explicit message rather than failing later on a missing selector.
        if (version_compare((string) $this->getMinorVersion(), '9.2', '<')) {
            $testSuite->skip('One Page Checkout requires PrestaShop 9.2+', function () {
            });

            return $testSuite;
        }

        // importPage() resolves friendly URLs from the SUITE's locale, so propagate
        // this scenario's locale before importing pages.
        $testSuite->params['locale'] = $this->params['locale'] ?? 'en';

        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\CheckoutLayout');
        $testSuite->importPage('FrontOffice\Login');
        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\OnePageCheckout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');

        extract($testSuite->pages);

        $testSuite
        ->it('log in on the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('login');
            $backOfficeLoginPage->login();
        })
        ->it('switch the shop to the one page checkout', function () use ($backOfficeCheckoutLayoutPage) {
            $backOfficeCheckoutLayoutPage->goTo();
            $backOfficeCheckoutLayoutPage->switchToOnePageCheckout();
        })
        ->it('log in on the FrontOffice', function () use ($frontOfficeLoginPage) {
            $frontOfficeLoginPage->goToPage('login');
            $frontOfficeLoginPage->login(
                $this->getParam('customerEmail'),
                $this->getParam('customerPassword')
            );
        })
        ->it('add a product to the cart', function () use ($frontOfficeProductPage) {
            $frontOfficeProductPage->goToProductPath($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));
        })
        ->it('reach the one page checkout', function () use ($frontOfficeCartPage, $frontOfficeOnePageCheckoutPage) {
            $frontOfficeCartPage->goToCart();
            $frontOfficeCartPage->proceedToCheckout();

            Expect::that($frontOfficeOnePageCheckoutPage->isOnePageCheckoutActive())->equals(true);
        })
        ->it('fill the checkout in one page', function () use ($frontOfficeOnePageCheckoutPage) {
            $frontOfficeOnePageCheckoutPage->selectAddress();
            $frontOfficeOnePageCheckoutPage->selectFirstCarrier();
            $frontOfficeOnePageCheckoutPage->selectFirstPayment();
            $frontOfficeOnePageCheckoutPage->acceptTerms();
            $frontOfficeOnePageCheckoutPage->placeOrder();
        })
        ->it('reach the order confirmation', function () use ($frontOfficeOrderConfirmationPage) {
            Expect::that($frontOfficeOrderConfirmationPage->isConfirmed())->equals(true);
        });

        return $testSuite;
    }
}
```

- [ ] **Step 4: Create the suite**

Create `src/Tests/Suites/Scenarios/OnePageCheckoutOrder.php`:

```php
<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class OnePageCheckoutOrder extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Place an order through the One Page Checkout as a logged-in customer')
        ->scenario(\PrestaFlow\Library\Scenarios\OnePageCheckoutOrder::class);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
vendor/bin/phpunit --testsuite Unit --filter OnePageCheckoutOrder
```

Expected: `OK (3 tests, 9 assertions)`.

- [ ] **Step 6: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Scenarios/OnePageCheckoutOrder.php src/Tests/Suites/Scenarios/OnePageCheckoutOrder.php tests/Unit/Scenarios/OnePageCheckoutOrderTest.php
git commit -m "feat(scenarios): OnePageCheckoutOrder covering the logged-in OPC path"
```

---

### Task 6: `OnePageCheckoutGuest` scenario

**Goal:** The same checkout as a guest: the scenario enables guest checkout, fills the contact e-mail and the inline address form, and places the order.

**Files:**
- Create: `src/Scenarios/OnePageCheckoutGuest.php`
- Create: `src/Tests/Suites/Scenarios/OnePageCheckoutGuest.php`
- Test: `tests/Unit/Scenarios/OnePageCheckoutGuestTest.php`

**Extra state to set:** guest checkout is a separate setting from the checkout layout, so this scenario drives `BackOffice\OrderSettings` as well.

**Acceptance Criteria:**
- [ ] Class `PrestaFlow\Library\Scenarios\OnePageCheckoutGuest` extends `Scenario`
- [ ] `params` declares `locale`, `guestEmail`, `firstName`, `lastName`, `addressStreet`, `addressCity`, `addressPostcode`, `addressCountry`, `addressPhone`, `productUrl`, `cartQuantity`
- [ ] Same 9.2 version guard as Task 5
- [ ] Enables guest checkout and the one-page layout before the front-office steps

**Verify:** `vendor/bin/phpunit --testsuite Unit --filter OnePageCheckoutGuest` → `OK (3 tests)`

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Scenarios/OnePageCheckoutGuestTest.php`:

```php
<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class OnePageCheckoutGuestTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\OnePageCheckoutGuest';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\OnePageCheckoutGuest';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresGuestParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\OnePageCheckoutGuest');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach ([
            'locale', 'guestEmail', 'firstName', 'lastName', 'addressStreet',
            'addressCity', 'addressPostcode', 'addressCountry', 'addressPhone',
            'productUrl', 'cartQuantity',
        ] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit --testsuite Unit --filter OnePageCheckoutGuest
```

Expected: FAIL — the classes do not exist.

- [ ] **Step 3: Create the scenario**

Create `src/Scenarios/OnePageCheckoutGuest.php`:

```php
<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Place an order through the One Page Checkout as a guest.
 *
 * Two shop settings are involved and they live in different places: the checkout
 * layout in the ps_onepagecheckout configuration, guest checkout in Shop
 * Parameters > Order Settings. Both are set up front and neither is restored —
 * each scenario is responsible for the state it requires.
 */
class OnePageCheckoutGuest extends Scenario
{
    public $params = [
        // The reference shop (ps92rc1 container) runs in English.
        'locale' => 'en',
        'guestEmail' => 'pf-opc-guest@example.com',
        'firstName' => 'PrestaFlow',
        'lastName' => 'Guest',
        'addressStreet' => '16 Main street',
        'addressCity' => 'Paris',
        'addressPostcode' => '75002',
        'addressCountry' => 'France',
        'addressPhone' => '0102030405',
        // Canonical product path — friendly URLs can't be rebuilt from an id.
        // A product with NO combinations: add-to-cart on a product that has
        // them returns "no longer available" unless an id_product_attribute is
        // posted too.
        'productUrl' => '6-mug-the-best-is-yet-to-come.html',
        'cartQuantity' => 1,
    ];

    public function steps($testSuite)
    {
        // The One Page Checkout module does not exist before 9.2: skip with an
        // explicit message rather than failing later on a missing selector.
        if (version_compare((string) $this->getMinorVersion(), '9.2', '<')) {
            $testSuite->skip('One Page Checkout requires PrestaShop 9.2+', function () {
            });

            return $testSuite;
        }

        // importPage() resolves friendly URLs from the SUITE's locale, so propagate
        // this scenario's locale before importing pages.
        $testSuite->params['locale'] = $this->params['locale'] ?? 'en';

        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\CheckoutLayout');
        $testSuite->importPage('BackOffice\OrderSettings');
        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\OnePageCheckout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');

        extract($testSuite->pages);

        $testSuite
        ->it('log in on the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('login');
            $backOfficeLoginPage->login();
        })
        ->it('enable guest checkout', function () use ($backOfficeOrderSettingsPage) {
            $backOfficeOrderSettingsPage->goTo();
            $backOfficeOrderSettingsPage->setGuestCheckout(true);
        })
        ->it('switch the shop to the one page checkout', function () use ($backOfficeCheckoutLayoutPage) {
            $backOfficeCheckoutLayoutPage->goTo();
            $backOfficeCheckoutLayoutPage->switchToOnePageCheckout();
        })
        ->it('add a product to the cart', function () use ($frontOfficeProductPage) {
            $frontOfficeProductPage->goToProductPath($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));
        })
        ->it('reach the one page checkout', function () use ($frontOfficeCartPage, $frontOfficeOnePageCheckoutPage) {
            $frontOfficeCartPage->goToCart();
            $frontOfficeCartPage->proceedToCheckout();

            Expect::that($frontOfficeOnePageCheckoutPage->isOnePageCheckoutActive())->equals(true);
        })
        ->it('check out as a guest and enter an address', function () use ($frontOfficeOnePageCheckoutPage) {
            $frontOfficeOnePageCheckoutPage->continueAsGuest($this->getParam('guestEmail'));
            $frontOfficeOnePageCheckoutPage->fillNewAddress([
                'firstName' => $this->getParam('firstName'),
                'lastName' => $this->getParam('lastName'),
                'street' => $this->getParam('addressStreet'),
                'postcode' => $this->getParam('addressPostcode'),
                'city' => $this->getParam('addressCity'),
                'country' => $this->getParam('addressCountry'),
                'phone' => $this->getParam('addressPhone'),
            ]);
        })
        ->it('choose shipping and payment, then place the order', function () use ($frontOfficeOnePageCheckoutPage) {
            $frontOfficeOnePageCheckoutPage->selectFirstCarrier();
            $frontOfficeOnePageCheckoutPage->selectFirstPayment();
            $frontOfficeOnePageCheckoutPage->acceptTerms();
            $frontOfficeOnePageCheckoutPage->placeOrder();
        })
        ->it('reach the order confirmation', function () use ($frontOfficeOrderConfirmationPage) {
            Expect::that($frontOfficeOrderConfirmationPage->isConfirmed())->equals(true);
        });

        return $testSuite;
    }
}
```

- [ ] **Step 4: Create the suite**

Create `src/Tests/Suites/Scenarios/OnePageCheckoutGuest.php`:

```php
<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class OnePageCheckoutGuest extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Place an order through the One Page Checkout as a guest')
        ->scenario(\PrestaFlow\Library\Scenarios\OnePageCheckoutGuest::class);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
vendor/bin/phpunit --testsuite Unit --filter OnePageCheckoutGuest
```

Expected: `OK (3 tests, 17 assertions)`.

- [ ] **Step 6: Run the whole unit suite**

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: everything green.

- [ ] **Step 7: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Scenarios/OnePageCheckoutGuest.php src/Tests/Suites/Scenarios/OnePageCheckoutGuest.php tests/Unit/Scenarios/OnePageCheckoutGuestTest.php
git commit -m "feat(scenarios): OnePageCheckoutGuest covering the guest OPC path"
```

---

### Task 7: End-to-end validation against the 9.2 shop

**Goal:** Run both suites against the live PrestaShop 9.2.0 container and fix whatever the real DOM disagrees with. Nothing in this feature is proven until this passes.

**Files:**
- Create: `.env.opc` (local, untracked — add it to `.gitignore` if it is not covered)
- Modify (as needed): the page objects from Tasks 2–4

**Reference shop, already verified:**

| Fact | Value |
|---|---|
| Container | `ps92rc1-web-1`, PrestaShop 9.2.0 |
| Front office | `http://localhost:8092/` |
| Back office | `http://localhost:8092/admin259je3iqgg4jvpdzdlw/` |
| Employee | `admin@example.com` (password supplied by the user; not recoverable from the container) |
| Customer | `pub@prestashop.com`, with two addresses (ids 2 and 5) — **password unknown**, which is what blocks the logged-in scenario |
| Carriers | `Click and collect` and `My carrier` are active |
| Payment | `ps_wirepayment`, `ps_cashondelivery`, `ps_checkpayment`, `ps_checkout` hooked on `paymentOptions` |
| Module | `ps_onepagecheckout` installed and active, and `PS_ONE_PAGE_CHECKOUT_ENABLED` is **already `1`** — the shop was left on the one-page layout |
| Multistore | **Enabled**, two shops (`PS92RC1` id 1, `Shop2` id 2). The module configuration renders nothing outside a single shop context |
| Theme | Shop 1 runs **hummingbird**, shop 2 runs classic. Several library selectors were classic-only and had to be widened |
| Default language | `en` (id_lang 1) |
| Product | `6-mug-the-best-is-yet-to-come.html` (id 6, no combinations, 300 in stock). The demo t-shirt has combinations and its add-to-cart returns "no longer available" without an `id_product_attribute` |

> **Correction.** An earlier draft of this plan stated that
> `PS_ONE_PAGE_CHECKOUT_ENABLED` was unset, i.e. that the shop started on the
> four-page checkout. That was wrong — the initial probe searched for
> `%ONEPAGE%`, which does not match `PS_ONE_PAGE_CHECKOUT_ENABLED`. The setting
> was already `1`, as was `PS_GUEST_CHECKOUT_ENABLED`. The scenarios set both
> anyway, so they do not depend on the starting state.

**Acceptance Criteria:**
- [ ] `OnePageCheckoutOrder` reaches the order confirmation against `localhost:8092`
- [ ] `OnePageCheckoutGuest` reaches the order confirmation against `localhost:8092`
- [ ] Both runs leave the shop on the one-page layout (no teardown, by design)
- [ ] Any selector corrected during this task is reflected in the page object, not worked around in the scenario

**Verify:** both suite runs end with every test green.

**Steps:**

- [ ] **Step 1: Write the environment file for the 9.2 shop**

The credentials below are the ones verified in the container; the employee password is the one open input — ask the user rather than guessing.

```bash
cat > .env.opc <<'ENV'
PRESTAFLOW_PS_VERSION=9.2.0
PRESTAFLOW_LOCALE=en
PRESTAFLOW_FO_URL=http://localhost:8092/
PRESTAFLOW_BO_URL=http://localhost:8092/admin259je3iqgg4jvpdzdlw/
PRESTAFLOW_BO_EMAIL=admin@example.com
PRESTAFLOW_BO_PASSWD=<ask the user>
PRESTAFLOW_HEADLESS=true
PRESTAFLOW_VERBOSE=true
ENV
```

Confirm `.env.opc` is ignored by git:

```bash
git check-ignore -v .env.opc || echo "NOT IGNORED — add .env.opc to .gitignore"
```

- [ ] **Step 2: Confirm the shop is reachable and still in four-page mode**

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8092/
docker exec ps92rc1-db-1 mariadb -uroot -proot prestashop -e "select name,value from ps_configuration where name='PS_ONE_PAGE_CHECKOUT_ENABLED';"
```

Expected: `200`, and either no row or `value = 0`. This is the starting state the scenarios are written against.

- [ ] **Step 3: Run the logged-in scenario**

```bash
./bin/prestaflow run src/Tests/Suites/Scenarios/OnePageCheckoutOrder.php
```

Expected: every step green, ending on "reach the order confirmation".

- [ ] **Step 4: Fix what the real DOM disagrees with**

Failures to expect, and where each one belongs:

- *Back-office login fails* → wrong password in `.env.opc`. Ask the user; do not brute-force.
- *"switch the shop to the one page checkout" fails* → check the sidebar ids actually rendered. Confirm with:
  ```bash
  docker exec ps92rc1-db-1 mariadb -uroot -proot prestashop -e "select id_tab,class_name,id_parent from ps_tab where class_name in ('AdminPsOnePageCheckout','AdminParentThemes');"
  ```
  The tab must exist under `AdminParentThemes`; adjust `parentMenuSelector` / `menuSelector` in `CheckoutLayout\Page` if the rendered ids differ.
- *"reach the one page checkout" fails* → the layout switch did not persist. Check:
  ```bash
  docker exec ps92rc1-db-1 mariadb -uroot -proot prestashop -e "select name,value from ps_configuration where name='PS_ONE_PAGE_CHECKOUT_ENABLED';"
  ```
  A value of `0` after the switch means the confirmation modal was not confirmed: revisit `applyLayout()` in `CheckoutLayout\Page`.
- *Carrier or payment wait times out* → compare the live DOM with the module's own map, `modules/ps_onepagecheckout/views/js/selectors.js`, and correct the page object's selector. Do not raise the timeout to paper over a wrong selector.
- *Pay button stays disabled* → something the module's `validateForm()` requires is missing (a required address field, an unchecked term). Read the live form rather than clicking blindly:
  ```bash
  docker exec ps92rc1-web-1 sh -lc 'cat /var/www/html/modules/ps_onepagecheckout/views/js/opc-submit.js' | sed -n '1,120p'
  ```

- [ ] **Step 5: Run the guest scenario**

```bash
./bin/prestaflow run src/Tests/Suites/Scenarios/OnePageCheckoutGuest.php
```

Expected: every step green. If the guest step fails, confirm the setting actually landed:

```bash
docker exec ps92rc1-db-1 mariadb -uroot -proot prestashop -e "select name,value from ps_configuration where name='PS_GUEST_CHECKOUT_ENABLED';"
```

Expected: `1`. A `0` means `setGuestCheckout()` did not save — fix `OrderSettings\Page`, not the scenario.

- [ ] **Step 6: Confirm the orders were really created**

```bash
docker exec ps92rc1-db-1 mariadb -uroot -proot prestashop -e "select id_order,reference,id_customer,total_paid,date_add from ps_orders order by id_order desc limit 5;"
```

Expected: one new order per successful run — a green confirmation page is not on its own proof that the order was persisted.

- [ ] **Step 7: Re-run the unit suite after any page-object correction**

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: everything green.

- [ ] **Step 8: Suggested commit boundary** (do not run without the user's explicit instruction)

```bash
git add src/Pages/v9
git commit -m "fix(pages): align One Page Checkout selectors with the live PS 9.2 shop"
```

---

## Task Dependencies

```
Task 1 (waitForCondition)
  └─> Task 2 (FO OnePageCheckout)  ─┐
  └─> Task 3 (BO CheckoutLayout)   ─┤
  └─> Task 4 (BO OrderSettings)    ─┤
                                    ├─> Task 5 (logged-in scenario) ─┐
                                    └─> Task 6 (guest scenario)     ─┴─> Task 7 (E2E validation)
```

Tasks 2, 3 and 4 are independent of each other once Task 1 is in. Task 5 needs Tasks 2 and 3; Task 6 needs Tasks 2, 3 and 4.

## Out of scope

As stated in the spec: express checkout buttons, a billing address distinct from the delivery address, gift wrapping and delivery messages, address editing and deletion from the card dropdown, virtual carts, and multistore.
