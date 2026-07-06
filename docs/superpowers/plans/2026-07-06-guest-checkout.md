# Guest Checkout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `GuestCheckout` scenario that places an order as a pure guest (email + name, no account), entering a new address, and reaches the FrontOffice order confirmation.

**Architecture:** Extend the existing `FrontOffice\Checkout` page with two methods for the steps the logged-in flow skipped — `checkoutAsGuest` (personal-information step) and `fillNewAddress` (new-address step) — then reuse `chooseShipping`/`choosePaymentAndConfirm`. A `GuestCheckout` scenario + suite compose it. Selectors are best-effort PS 9, corrected during live validation. Structural tests lock the surface; the live run is the real success criterion.

**Tech Stack:** PHP 8.1+ (runtime 8.4), PHPUnit 10, headless Chrome (chrome-php). Live shop: PrestaShop 9.0.0-rc.1 at `https://9.0.0-rc.1.test`.

Spec: `docs/superpowers/specs/2026-07-06-guest-checkout-design.md`

---

## Conventions (read once)

- Page objects use `$this->getSelector('key')`, `$this->click(...)`,
  `$this->setValue(...)`, `$this->selectValue(...)`, `$this->waitForPageReload()`.
- A Scenario extends `PrestaFlow\Library\Scenarios\Scenario`, declares `public
  $params`, defines `steps($testSuite)`; inside `it()` closures `$this` rebinds to
  the suite so `getParam()/store()/retrieve()` are available (but NOT at the top of
  `steps()` — there `$this` is the scenario, use `$this->params[...]`).
- Scenarios that hit French friendly URLs propagate their locale to the suite:
  `$testSuite->params['locale'] = $this->params['locale'] ?? 'fr';` before
  importing pages.
- Selectors listed are **best-effort PS 9**; the coordinator corrects them live.

---

### Task 0: Extend the Checkout page with guest steps

**Goal:** The FrontOffice Checkout page gains `checkoutAsGuest` (personal-info) and `fillNewAddress` (new-address) plus their selectors.

**Files:**
- Modify: `src/Pages/Common/FrontOffice/Checkout/Page.php`
- Test: `tests/Unit/Pages/FrontOfficeCheckoutTest.php` (extend the existing file)

**Acceptance Criteria:**
- [ ] Checkout declares `personalEmailInput`, `personalFirstNameInput`, `personalLastNameInput`, `personalContinueButton`, `guestToggle`, `addressStreetInput`, `addressCityInput`, `addressPostcodeInput`, `addressCountrySelect`, `addressPhoneInput`.
- [ ] Checkout has `checkoutAsGuest(string $email, string $firstName, string $lastName)` and `fillNewAddress(array $address)`.
- [ ] Existing methods/selectors unchanged; `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Pages/FrontOfficeCheckoutTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Extend the structural test (red)**

In `tests/Unit/Pages/FrontOfficeCheckoutTest.php`, add these two methods to the class:
```php
    public function testCheckoutHasGuestSteps(): void
    {
        $page = $this->make('Checkout');
        foreach (['personalEmailInput', 'personalFirstNameInput', 'personalLastNameInput', 'personalContinueButton', 'guestToggle', 'addressStreetInput', 'addressCityInput', 'addressPostcodeInput', 'addressCountrySelect', 'addressPhoneInput'] as $key) {
            $this->assertArrayHasKey($key, $page->selectors, $key);
        }
        $this->assertTrue(method_exists($page, 'checkoutAsGuest'));
        $this->assertTrue(method_exists($page, 'fillNewAddress'));
    }
```
Run: `vendor/bin/phpunit tests/Unit/Pages/FrontOfficeCheckoutTest.php`
Expected: FAIL — the new selector keys and methods don't exist.

- [ ] **Step 2: Add the guest selectors**

In `src/Pages/Common/FrontOffice/Checkout/Page.php`, extend `defineSelectors()` — keep the existing entries and add:
```php
            // Guest personal-information step — best-effort PS 9, corrected live.
            'guestToggle' => '#checkout-personal-information-step .nav-item a[href*="guest"], #checkout-guest-form-tab',
            'personalEmailInput' => '#checkout-personal-information-step input[name="email"]',
            'personalFirstNameInput' => '#checkout-personal-information-step input[name="firstname"]',
            'personalLastNameInput' => '#checkout-personal-information-step input[name="lastname"]',
            'personalContinueButton' => '#checkout-personal-information-step button[type="submit"]',
            // Guest new-address step.
            'addressStreetInput' => '#checkout-addresses-step input[name="address1"]',
            'addressCityInput' => '#checkout-addresses-step input[name="city"]',
            'addressPostcodeInput' => '#checkout-addresses-step input[name="postcode"]',
            'addressCountrySelect' => '#checkout-addresses-step select[name="id_country"]',
            'addressPhoneInput' => '#checkout-addresses-step input[name="phone"]',
```

- [ ] **Step 3: Add the two methods**

In the same file, add after `confirmAddresses()`:
```php
    public function checkoutAsGuest(string $email, string $firstName, string $lastName): void
    {
        // If a guest/sign-in toggle is present, switch to the guest form.
        $this->click($this->getSelector('guestToggle'));

        $this->setValue($this->getSelector('personalFirstNameInput'), $firstName);
        $this->setValue($this->getSelector('personalLastNameInput'), $lastName);
        $this->setValue($this->getSelector('personalEmailInput'), $email);

        $this->click($this->getSelector('personalContinueButton'));
        $this->waitForPageReload();
    }

    public function fillNewAddress(array $address): void
    {
        $this->setValue($this->getSelector('addressStreetInput'), $address['street'] ?? '');
        $this->setValue($this->getSelector('addressCityInput'), $address['city'] ?? '');
        $this->setValue($this->getSelector('addressPostcodeInput'), $address['postcode'] ?? '');
        if (!empty($address['country'])) {
            $this->selectValue($this->getSelector('addressCountrySelect'), $address['country']);
        }
        $this->setValue($this->getSelector('addressPhoneInput'), $address['phone'] ?? '');

        $this->click($this->getSelector('addressesContinueButton'));
        $this->waitForPageReload();
    }
```
(`selectValue` and `setValue` already exist on `CommonPage`; `addressesContinueButton` is the existing address-step continue selector.)

- [ ] **Step 4: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Pages/FrontOfficeCheckoutTest.php`
Expected: PASS.
Run: `php -l src/Pages/Common/FrontOffice/Checkout/Page.php && composer test-unit`
Expected: lint clean, whole suite green.

- [ ] **Step 5: Commit** — await user go-ahead; do NOT `git commit`.

---

### Task 1: GuestCheckout scenario + suite + tests

**Goal:** The guest checkout scenario, its execution suite, and browser-free existence/param tests.

**Files:**
- Create: `src/Scenarios/GuestCheckout.php`
- Create: `src/Tests/Suites/Scenarios/GuestCheckout.php`
- Test: `tests/Unit/Scenarios/GuestCheckoutTest.php`

**Acceptance Criteria:**
- [ ] `GuestCheckout` extends `Scenario`, declares params `locale`, `productUrl`, `cartQuantity`, `guestEmail`, `firstName`, `lastName`, `addressStreet`, `addressCity`, `addressPostcode`, `addressCountry`, `addressPhone`, and drives the guest tunnel to `OrderConfirmation.isConfirmed()`.
- [ ] `GuestCheckout` suite extends `TestsSuite` and runs the scenario.
- [ ] `composer test-unit` green.

**Verify:** `vendor/bin/phpunit tests/Unit/Scenarios/GuestCheckoutTest.php && composer test-unit`

**Steps:**

- [ ] **Step 1: Write the structural test (red)**

Create `tests/Unit/Scenarios/GuestCheckoutTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class GuestCheckoutTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\GuestCheckout';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\GuestCheckout';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresGuestParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\GuestCheckout');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['guestEmail', 'firstName', 'lastName', 'addressStreet', 'addressCity', 'addressPostcode', 'addressCountry', 'addressPhone', 'productUrl'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
```
Run: `vendor/bin/phpunit tests/Unit/Scenarios/GuestCheckoutTest.php`
Expected: FAIL — classes do not exist.

- [ ] **Step 2: Create the scenario**

Create `src/Scenarios/GuestCheckout.php`:
```php
<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class GuestCheckout extends Scenario
{
    public $params = [
        'locale' => 'fr',
        'productUrl' => '1-1-hummingbird-printed-t-shirt.html',
        'cartQuantity' => 1,
        'guestEmail' => 'pf-guest@example.com',
        'firstName' => 'PrestaFlow',
        'lastName' => 'Guest',
        'addressStreet' => '16 Main street',
        'addressCity' => 'Paris',
        'addressPostcode' => '75002',
        'addressCountry' => 'France',
        'addressPhone' => '0102030405',
    ];

    public function steps($testSuite)
    {
        $testSuite->params['locale'] = $this->params['locale'] ?? 'fr';

        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('FrontOffice\Checkout');
        $testSuite->importPage('FrontOffice\OrderConfirmation');

        extract($testSuite->pages);

        $testSuite
        ->it('add a product to the cart', function () use ($frontOfficeProductPage) {
            $frontOfficeProductPage->goToProductPath($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));
        })
        ->it('checkout as a guest and enter an address', function () use ($frontOfficeCartPage, $frontOfficeCheckoutPage) {
            $frontOfficeCartPage->goToCart();
            $frontOfficeCartPage->proceedToCheckout();

            $frontOfficeCheckoutPage->checkoutAsGuest(
                $this->getParam('guestEmail'),
                $this->getParam('firstName'),
                $this->getParam('lastName')
            );
            $frontOfficeCheckoutPage->fillNewAddress([
                'street' => $this->getParam('addressStreet'),
                'city' => $this->getParam('addressCity'),
                'postcode' => $this->getParam('addressPostcode'),
                'country' => $this->getParam('addressCountry'),
                'phone' => $this->getParam('addressPhone'),
            ]);
        })
        ->it('choose shipping and place the order', function () use ($frontOfficeCheckoutPage) {
            $frontOfficeCheckoutPage->chooseShipping();
            $frontOfficeCheckoutPage->choosePaymentAndConfirm();
        })
        ->it('reach the order confirmation', function () use ($frontOfficeOrderConfirmationPage) {
            Expect::that($frontOfficeOrderConfirmationPage->isConfirmed())->equals(true);
        });

        return $testSuite;
    }
}
```

- [ ] **Step 3: Create the execution suite**

Create `src/Tests/Suites/Scenarios/GuestCheckout.php`:
```php
<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class GuestCheckout extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Place an order as a guest and reach the FrontOffice confirmation')
        ->scenario(\PrestaFlow\Library\Scenarios\GuestCheckout::class);
    }
}
```

- [ ] **Step 4: Run tests + lint (green)**

Run: `composer dump-autoload && vendor/bin/phpunit tests/Unit/Scenarios/GuestCheckoutTest.php`
Expected: PASS.
Run: `php -l src/Scenarios/GuestCheckout.php && php -l src/Tests/Suites/Scenarios/GuestCheckout.php && composer test-unit`
Expected: lint clean, whole suite green.

- [ ] **Step 5: Commit** — await user go-ahead; do NOT `git commit`.

---

## Integration check (LIVE — the real success criterion, run by the coordinator)

Against the local PS 9 (`.env.local` configured, `datas/` exists), one suite at a
time, killing Chrome + clearing the socket between runs:
```bash
pkill -9 -i chrome ; rm -f datas/.broswer datas/.broswer-options
bin/prestaflow run <tmp dir with the GuestCheckout suite>
```
Expected: add to cart → personal-information step as guest (email + name) → new
address (street/city/postcode/country/phone) → shipping → payment + terms → place
order → FrontOffice confirmation (isConfirmed true). Green, reproducible.

Reverse-engineer and correct any best-effort selector live — the **guest
personal-information form and the new-address form are the fragile parts** (a
guest/sign-in toggle may need clicking; required fields like phone; the country
select). If a form field's name differs, fix the selector; if the guest toggle is
absent (guest is the default form), the toggle click harmlessly no-ops. Do NOT
paper over a broken step.

## Commits

Explicit owner approval required before any `git commit`. Implementer subagents
must NOT run `git commit`/`git add`; leave changes in the working tree. The
coordinator batches commits once the user approves.
