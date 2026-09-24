# Flashlight Test Infrastructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hand-built `ps92rc1` container with reproducible PrestaShop shops from the official `prestashop-flashlight` images, provisioned by post-scripts, used both locally and in CI, and run the real smoke suite against 1.7.8.11, 8.2.8 and 9.2.0.

**Architecture:** A repo-root `docker-compose.yml` declares one Flashlight shop plus one MariaDB per PrestaShop version. Each shop mounts `docker/post-scripts/`, which Flashlight executes **after** PrestaShop has started — the only point at which a second shop can be provisioned. A GitHub Actions matrix boots one shop per job and runs the smoke suite against it. The 1.7 and 8.2 rows are expected to fail at first: their 84 page objects are nine-line stubs inheriting v9's selectors, and the failures are the point — they are the inventory of what actually differs per version.

**Tech Stack:** Docker Compose v2, `prestashop/prestashop-flashlight` images, GitHub Actions, the existing PrestaFlow runner (`bin/prestaflow`).

---

## Supersedes

`docs/superpowers/plans/2026-07-10-phase2-task0-flashlight-infra.md`. That plan
was never completed and could not have been: it pinned
`prestashop/prestashop-flashlight:9.0.1`, a tag that does not exist (404 on the
registry — only suffixed tags such as `9.0.1-nginx` resolve). Its acceptance
criterion "`docker compose up ps90 -d` returns HTTP 200" was therefore
unachievable, and the `docker-compose.yml` it produced was left uncommitted in
the working tree for two months. Delete the old plan when this one lands.

## Verified facts this plan rests on

Checked on 2026-09-24 against the registry and the upstream README, because the
previous plan died on an unverified tag:

| Fact | Value |
|---|---|
| Image tags that resolve | `1.7.8.11-nginx`, `8.2.8-nginx`, `9.0.3-nginx`, `9.2.0-nginx` |
| Bare tags (no suffix) | **404 for every 9.x and 8.x**; only `1.7.8.11` resolves |
| Back office URL | `{PS_DOMAIN}/admin-dev` — fixed, no random folder |
| Back office login | `admin@prestashop.com` / `prestashop` |
| Post-startup hook | `POST_SCRIPTS_DIR`, default `/tmp/post-scripts` |
| Failure behaviour | `ON_POST_SCRIPT_FAILURE=fail` (default) aborts the container |
| Theme variants | **none** — no `-hummingbird` tags, and only four ad-hoc `-classic` tags, all 8.x |

That last row matters: Flashlight gives you a version's default theme and
nothing else. Classic-on-9.2 coverage needs the theme switched inside the
container, which `SwitchTheme` already does through the back office.

## File Structure

**New:**
- `docker-compose.yml` — repo root; services `ps17`, `ps82`, `ps92` and one MariaDB each.
- `docker/post-scripts/10-second-shop.sh` — provisions a second shop in the running container.
- `docker/post-scripts/20-carrier-restrictions.sh` — works around PrestaShop#42964.
- `.env.flashlight.example` — the `PRESTAFLOW_*` values for each shop.
- `.github/workflows/live-smoke.yml` — matrix over the three versions.
- `src/Tests/Suites/Smoke/FrontOfficeSmoke.php` — the suite CI runs.
- `docs/testing-with-flashlight.md` — one-page guide.

**Modified:**
- `README.md` — a "Run a suite against a throwaway shop" section linking the guide.
- `.gitignore` — add `.env.flashlight` if absent.

**Untouched:** every existing page object and scenario. This task adds infrastructure; it changes no library behaviour.

---

### Task 1: Compose fixture

**Goal:** `docker compose up ps92 -d` serves a working PrestaShop 9.2.0 at `http://localhost:8092/`, and the same for 1.7.8.11 on 8017 and 8.2.8 on 8082.

**Files:**
- Create: `docker-compose.yml`
- Create: `.env.flashlight.example`
- Modify: `.gitignore`

**Acceptance Criteria:**
- [ ] `docker compose config` validates with no error.
- [ ] Each of the three shops answers HTTP 200 on its port within 180s of `up -d`.
- [ ] Each back office answers HTTP 200 or 302 at `/admin-dev/`.
- [ ] `.gitignore` contains `.env.flashlight`.

**Verify:**

```bash
docker compose up ps92 -d
until curl -sf http://localhost:8092/admin-dev/ >/dev/null 2>&1 \
   || [ "$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8092/admin-dev/)" = "302" ]; do sleep 5; done
echo OK
```

**This deliberately probes `/admin-dev/`, not `/`.** An earlier version of this
plan checked `/` and was a check that could not fail: a leftover hand-made
container was bound to 8092 and answered 200, so the verify passed while nothing
Flashlight had started. `/admin-dev/` is the discriminator — Flashlight serves it
(302 to the login), a hand-made install with a random admin folder returns 404.

**Precondition:** port 8092 must be free. If anything else holds it, this task's
verification is meaningless rather than merely inconvenient. Check with
`docker ps --format '{{.Names}}\t{{.Ports}}' | grep 8092` before starting.

**Steps:**

- [ ] **Step 1: Write `docker-compose.yml`**

```yaml
# Throwaway PrestaShop shops from the official Flashlight images.
#
# One shop and one database per version. Bring up a single shop at a time:
#   docker compose up ps92 -d
# Its database starts with it through depends_on.
#
# Tags carry a suffix on purpose. The bare `9.2.0` and `8.2.8` tags do not
# exist on the registry — a previous attempt at this file pinned `9.0.1` and
# could never start.
services:
  db17:
    image: mariadb:11
    environment:
      MARIADB_ROOT_PASSWORD: prestashop
      MARIADB_DATABASE: prestashop
      MARIADB_USER: prestashop
      MARIADB_PASSWORD: prestashop
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect"]
      interval: 5s
      timeout: 5s
      retries: 20

  ps17:
    image: prestashop/prestashop-flashlight:1.7.8.11-nginx
    depends_on:
      db17:
        condition: service_healthy
    environment:
      PS_DOMAIN: localhost:8017
      MYSQL_HOST: db17
      DEBUG_MODE: "false"
      POST_SCRIPTS_DIR: /tmp/post-scripts
    volumes:
      - ./docker/post-scripts:/tmp/post-scripts:ro
    ports:
      - "8017:80"
      - "8018:80"   # the second shop

  db82:
    image: mariadb:11
    environment:
      MARIADB_ROOT_PASSWORD: prestashop
      MARIADB_DATABASE: prestashop
      MARIADB_USER: prestashop
      MARIADB_PASSWORD: prestashop
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect"]
      interval: 5s
      timeout: 5s
      retries: 20

  ps82:
    image: prestashop/prestashop-flashlight:8.2.8-nginx
    depends_on:
      db82:
        condition: service_healthy
    environment:
      PS_DOMAIN: localhost:8082
      MYSQL_HOST: db82
      DEBUG_MODE: "false"
      POST_SCRIPTS_DIR: /tmp/post-scripts
    volumes:
      - ./docker/post-scripts:/tmp/post-scripts:ro
    ports:
      - "8082:80"
      - "8083:80"   # the second shop

  db92:
    image: mariadb:11
    environment:
      MARIADB_ROOT_PASSWORD: prestashop
      MARIADB_DATABASE: prestashop
      MARIADB_USER: prestashop
      MARIADB_PASSWORD: prestashop
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect"]
      interval: 5s
      timeout: 5s
      retries: 20

  ps92:
    image: prestashop/prestashop-flashlight:9.2.0-nginx
    depends_on:
      db92:
        condition: service_healthy
    environment:
      PS_DOMAIN: localhost:8092
      MYSQL_HOST: db92
      DEBUG_MODE: "false"
      POST_SCRIPTS_DIR: /tmp/post-scripts
    volumes:
      - ./docker/post-scripts:/tmp/post-scripts:ro
    ports:
      - "8092:80"
      - "8093:80"   # the second shop, on its own port rather than a virtual URI
```

- [ ] **Step 2: Write `.env.flashlight.example`**

```bash
# Values for a Flashlight shop. Copy to .env.flashlight and adjust the port.
#
# The back office folder and credentials are fixed by the image, so unlike a
# hand-made install there is nothing machine-specific to discover here.
PRESTAFLOW_FO_URL=http://localhost:8092/
PRESTAFLOW_BO_URL=http://localhost:8092/admin-dev/
PRESTAFLOW_BO_EMAIL=admin@prestashop.com
PRESTAFLOW_BO_PASSWD=prestashop
PRESTAFLOW_LOCALE=en

# One of these per shop:
#   1.7.8.11 -> port 8017, theme classic
#   8.2.8    -> port 8082, theme classic
#   9.2.0    -> port 8092, theme hummingbird
PRESTAFLOW_PS_VERSION=9.2.0
PRESTAFLOW_THEME=hummingbird
```

- [ ] **Step 3: Add `.env.flashlight` to `.gitignore`**

```bash
grep -q '^\.env\.flashlight$' .gitignore || printf '.env.flashlight\n' >> .gitignore
grep -n 'env.flashlight' .gitignore
```

Expected: one matching line.

- [ ] **Step 4: Validate and boot**

```bash
docker compose config >/dev/null && echo "compose OK"
docker compose up ps92 -d
until curl -sf http://localhost:8092/ >/dev/null; do sleep 5; done; echo "FO OK"
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8092/admin-dev/
```

Expected: `compose OK`, then `FO OK`, then `200` or `302`.

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml .env.flashlight.example .gitignore
git commit -m "chore(infra): flashlight compose fixture for 1.7, 8.2 and 9.2"
```

---

### Task 2: Second shop, provisioned by a post-script

**Goal:** Every Flashlight shop boots with a working second shop, so multistore is exercised by default instead of being a state somebody built by hand once.

**Files:**
- Create: `docker/post-scripts/10-second-shop.sh`
- Create: `docker/post-scripts/20-carrier-restrictions.sh`

**Acceptance Criteria:**
- [ ] After `docker compose up ps92 -d`, the second shop answers HTTP 200 on its own port (`http://localhost:8093/`).
- [ ] `ps_shop` contains two rows.
- [ ] `ps_module_carrier` has as many rows for shop 2 as for shop 1.
- [ ] Shop 2's theme assets resolve: fetching its stylesheet on the second port returns 200.
- [ ] Shop 2 is reachable on its own port and shop 1 is unaffected on its own.

**A criterion deliberately NOT claimed:** the first draft of this plan asserted
that a failing post-script aborts the container, because `ON_POST_SCRIPT_FAILURE`
defaults to `fail`. That was taken from the documentation and is **false in
practice** — verified on 2026-09-24 by running a deliberately failing script: the
container logged the failure and stayed up and healthy. In Flashlight's
`run.sh`, the handler's `exit 8` runs inside the `sh -c` child that `xargs`
spawns, so it kills the child, and the pipeline's exit status is `awk`'s and is
never checked. A half-provisioned shop keeps serving.

Do not write a criterion around it. The real protection is Task 3's suite
asserting the second shop works, so a broken provisioning fails loudly at test
time instead of pretending at boot time.

**Verify:**

```bash
curl -s -o /dev/null -w 'shop2 %{http_code}\n' http://localhost:8093/
```

Expected `200`. Then confirm its assets really resolve, which is the check the
virtual-URI design silently failed:

```bash
curl -s http://localhost:8093/ | grep -o 'href="[^"]*\.css[^"]*"' | head -1
# take that URL and fetch it — it must return 200, not 404
```

**Steps:**

- [ ] **Step 1: Write `docker/post-scripts/10-second-shop.sh`**

Note the two traps this encodes, both found the hard way on 2026-09-24:
`Shop::copyShopData()` reads `Tools::getValue('categoryBox')`, so it fatals
outside an HTTP request unless `$_POST` is primed; and a shop on a virtual URI
serves no assets until `.htaccess` is regenerated.

```bash
#!/bin/sh
set -eu

echo "* Provisioning a second shop..."

cat > /tmp/second-shop.php <<'PHP'
<?php
require_once '/var/www/html/config/config.inc.php';

$src = 1;

// A second shop, its group, and its URL on a virtual URI.
$group = new ShopGroup();
$group->name = 'Group2';
$group->active = true;
$group->add();

$shop = new Shop();
$shop->name = 'Shop2';
$shop->id_shop_group = (int) $group->id;
$shop->id_category = (int) Configuration::get('PS_HOME_CATEGORY');
$shop->theme_name = 'classic';
$shop->active = true;
$shop->add();

$url = new ShopUrl();
$url->id_shop = (int) $shop->id;
$secondPort = getenv('SECOND_SHOP_PORT') ?: '8093';
$host = preg_replace('/:\d+$/', '', (string) Configuration::get('PS_SHOP_DOMAIN'));
$url->domain = $url->domain_ssl = $host . ':' . $secondPort;
// A DEDICATED PORT, not a virtual URI. PrestaShop discriminates shops by
// domain including the port, so localhost:8093 is a distinct shop — and its
// assets resolve at the root, needing no rewrite at all.
//
// The virtual-URI approach this replaced cannot work here: it relies on
// Tools::generateHtaccess(), and every Flashlight tag we use is -nginx, which
// never reads .htaccess. Verified 2026-09-24: /shop2/themes/...css returned
// 404 while the same file at the root returned 200. There is no -apache tag
// for 1.7.8.11, 8.2.8 or 9.2.0 (only 9.0.3 has one), so switching flavour is
// not an option either.
$url->physical_uri = '/';
$url->virtual_uri = '';
$url->main = true;
$url->active = true;
$url->add();

// copyShopData() reads Tools::getValue('categoryBox'): outside an HTTP request
// that returns false and count(false) is fatal on PHP 8. Prime it with the
// source shop's categories, which is what the Multistore form would post.
$rows = Db::getInstance()->executeS(
    'SELECT id_category FROM ' . _DB_PREFIX_ . 'category_shop WHERE id_shop = ' . (int) $src
);
$categories = array_map('intval', array_column($rows ?: [], 'id_category'));
$_POST['categoryBox'] = $_REQUEST['categoryBox'] = $categories;

// The same 26 keys the Multistore form offers.
$keys = [
    'carrier', 'cms', 'contact', 'country', 'currency', 'discount', 'employee',
    'image', 'lang', 'manufacturer', 'module', 'hook_module', 'meta_lang',
    'product', 'product_attribute', 'stock_available', 'store',
    'webservice_account', 'attribute_group', 'feature', 'group',
    'tax_rules_group', 'supplier', 'zone', 'cart_rule',
];
$shop->copyShopData($src, array_fill_keys($keys, 'on'));
$shop->associateSuperAdmins();

array_unshift($categories, (int) Configuration::get('PS_ROOT_CATEGORY'));
Category::updateFromShop(array_values(array_unique($categories)), (int) $shop->id);

// Module-owned data travels through a hook, not through copyShopData().
foreach ((array) Hook::getHookModuleExecList('actionShopDataDuplication') as $m) {
    Hook::exec('actionShopDataDuplication', [
        'old_id_shop' => $src,
        'new_id_shop' => (int) $shop->id,
    ], (int) $m['id_module']);
}

// Multistore is enabled, and its back-office screens must exist with it:
// setting the flag without activating the tabs gives a shop that cannot be
// managed, which is exactly how the previous hand-made container ended up.
Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);
Db::getInstance()->execute(
    'UPDATE ' . _DB_PREFIX_ . "tab SET active = 1 WHERE class_name IN ('AdminShopGroup','AdminShopUrl')"
);

// Nothing to rewrite: the second shop lives at the root of its own port.

echo 'second shop id=' . (int) $shop->id . PHP_EOL;
PHP

php /tmp/second-shop.php
rm -f /tmp/second-shop.php

echo "✅ Second shop provisioned"
```

- [ ] **Step 2: Write `docker/post-scripts/20-carrier-restrictions.sh`**

```bash
#!/bin/sh
set -eu

# Works around PrestaShop#42964: ps_module_carrier is not in
# Shop::getAssoTables(), so a duplicated shop inherits no carrier restrictions
# and Hook::getHookModuleExecList() then offers it no payment method at all.
# Without this, checkout on shop 2 stops at "no payment method available".
echo "* Copying carrier restrictions to the second shop..."

cat > /tmp/carrier-restrictions.php <<'PHP'
<?php
require_once '/var/www/html/config/config.inc.php';

$rows = Db::getInstance()->executeS('SELECT id_shop FROM ' . _DB_PREFIX_ . 'shop WHERE id_shop <> 1');
foreach ($rows ?: [] as $row) {
    Db::getInstance()->execute(
        'INSERT IGNORE INTO ' . _DB_PREFIX_ . 'module_carrier (id_module, id_shop, id_reference) '
        . 'SELECT id_module, ' . (int) $row['id_shop'] . ', id_reference '
        . 'FROM ' . _DB_PREFIX_ . 'module_carrier WHERE id_shop = 1'
    );
}
PHP

php /tmp/carrier-restrictions.php
rm -f /tmp/carrier-restrictions.php

echo "✅ Carrier restrictions copied"
```

- [ ] **Step 3: Make both executable**

```bash
chmod +x docker/post-scripts/10-second-shop.sh docker/post-scripts/20-carrier-restrictions.sh
ls -l docker/post-scripts/
```

Expected: both show the executable bit.

- [ ] **Step 4: Boot from scratch and verify**

```bash
docker compose down -v
docker compose up ps92 -d
until curl -sf http://localhost:8092/ >/dev/null; do sleep 5; done
curl -s -o /dev/null -w 'shop2 %{http_code}\n' http://localhost:8092/shop2/
docker compose exec -T db92 mariadb -uroot -pprestashop prestashop \
  -e "SELECT COUNT(*) AS shops FROM ps_shop; SELECT SUM(id_shop=1) s1, SUM(id_shop=2) s2 FROM ps_module_carrier;"
```

Expected: `shop2 200`, `shops` = 2, and `s1` equal to `s2`.

- [ ] **Step 5: Commit**

```bash
git add docker/post-scripts
git commit -m "chore(infra): provision a second shop in every flashlight container"
```

---

### Task 3: The smoke suite CI runs

**Goal:** One suite that walks Home → Listing → Product and is meaningful on any supported
version, so the matrix has something real to run.

**Files:**
- Create: `src/Tests/Suites/Smoke/FrontOfficeSmoke.php`
- Modify: `src/Pages/v9/FrontOffice/Home/Page.php` (add `isDisplayed()`)

**Acceptance Criteria:**
- [ ] The suite passes against the 9.2 shop on port 8092.
- [ ] Every step carries an assertion.
- [ ] It is proven able to fail (see Step 4).
- [ ] It hardcodes no product URL, no category id and no theme-specific selector.

**Verify:** with ps92 up and the env exported,
`php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php` → every step PASS.

**Why this shape:** three pages, each reached from the previous one, so a failure names the page
whose markup differs instead of reporting a generic dead end. The last assertion reads a PRICE on
purpose: it exercises the listing-to-product navigation, the product page's price selector and
`parsePrice()` in one go — the exact combination that was silently returning `0.0` on hummingbird
until 2026-09-24.

**Steps:**

- [ ] **Step 1: Add `isDisplayed()` to the v9 Home page**

The suite needs to assert it reached the home page. `homePageSection` already exists as a
selector; nothing exposes it as a question.

```php
    /**
     * Whether the home page rendered its own content section.
     *
     * Distinct from "the request returned 200": a maintenance page, an error
     * page and a redirect to another shop all answer 200 too.
     */
    public function isDisplayed(): bool
    {
        return $this->elementIsVisible($this->getSelector('homePageSection'), 5000);
    }
```

- [ ] **Step 2: Write the suite**

```php
<?php

namespace PrestaFlow\Library\Tests\Suites\Smoke;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * The smallest suite worth running against every supported PrestaShop.
 *
 * Deliberately not a checkout. The point is to fail EARLY and SPECIFICALLY on a
 * version whose markup differs, so the failure names the page. A tunnel reports
 * the same "stuck at step 2" for a dozen unrelated causes.
 *
 * Nothing here is pinned to a fixture: no product URL, no category id. The
 * suite walks whatever catalogue the shop has, so it is as valid on 1.7.8.11 as
 * on 9.2.0 — and when it fails on one of them, that failure is the finding.
 */
class FrontOfficeSmoke extends TestsSuite
{
    public function init()
    {
        $this->importPage('FrontOffice\Home');
        $this->importPage('FrontOffice\Listing');
        $this->importPage('FrontOffice\Product');

        extract($this->pages);

        $this
        ->describe('Front office smoke')
        ->it('the home page renders', function () use ($frontOfficeHomePage) {
            $frontOfficeHomePage->goToPage('home');

            Expect::that($frontOfficeHomePage->isDisplayed())->equals(true);
        })
        ->it('reach the product listing from the home page', function () use ($frontOfficeHomePage, $frontOfficeListingPage) {
            // Reads the "all products" link out of the home page and follows it,
            // so a theme that renames that link fails here and names Home.
            $frontOfficeHomePage->goToAllProducts();

            Expect::that($frontOfficeListingPage->getListingTitle())->notEquals('');
        })
        ->it('open a product and read its price', function () use ($frontOfficeListingPage, $frontOfficeProductPage) {
            $frontOfficeListingPage->goToProduct(1);

            // A price above zero proves three things at once: the listing linked
            // to a real product, the product page matched its price element, and
            // parsePrice() understood the rendered format.
            Expect::that($frontOfficeProductPage->getPrice() > 0)->equals(true);
        });
    }
}
```

- [ ] **Step 3: Run it against the 9.2 shop**

```bash
docker compose up ps92 -d
until [ "$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8092/admin-dev/)" = "302" ]; do sleep 5; done

PRESTAFLOW_PS_VERSION=9.2.0 \
PRESTAFLOW_FO_URL=http://localhost:8092/ \
PRESTAFLOW_BO_URL=http://localhost:8092/admin-dev/ \
PRESTAFLOW_BO_EMAIL=admin@prestashop.com \
PRESTAFLOW_BO_PASSWD=prestashop \
PRESTAFLOW_THEME=hummingbird \
PRESTAFLOW_LOCALE=en \
php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php
```

Expected: three steps, all PASS.

- [ ] **Step 4: Prove it can fail**

Point it at the second shop's port but keep the hummingbird theme, on a shop that runs classic:

```bash
PRESTAFLOW_FO_URL=http://localhost:8093/ PRESTAFLOW_THEME=hummingbird \
  PRESTAFLOW_PS_VERSION=9.2.0 PRESTAFLOW_LOCALE=en \
  php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php
```

If that still passes, the suite is not discriminating and must be tightened before
it is worth putting in CI. Record which step failed and how.

- [ ] **Step 5: Run it against the second shop, correctly**

```bash
PRESTAFLOW_FO_URL=http://localhost:8093/ PRESTAFLOW_THEME=classic \
  PRESTAFLOW_PS_VERSION=9.2.0 PRESTAFLOW_LOCALE=en \
  php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php
```

Expected: PASS. This is what proves the provisioned second shop is genuinely usable, which is the
protection Task 2 could not get from the container (a failing post-script does not stop it).

- [ ] **Step 6: Commit**

```bash
git add src/Tests/Suites/Smoke/FrontOfficeSmoke.php src/Pages/v9/FrontOffice/Home/Page.php
git commit -m "test(smoke): a front-office suite fit to run on every version"
```

---

### Task 4: The CI matrix

**Goal:** Every push to `main` and every pull request runs the smoke suite against 1.7.8.11, 8.2.8 and 9.2.0.

**Files:**
- Create: `.github/workflows/live-smoke.yml`

**Acceptance Criteria:**
- [ ] The workflow runs three jobs, one per version, with `fail-fast: false`.
- [ ] The 9.2 job passes.
- [ ] The 1.7 and 8.2 jobs run the suite for real — they are **not** skipped, and they are expected to fail until the v7/v8 page objects stop being stubs.
- [ ] Each job uploads its failure screenshots as an artifact.

**Verify:** push the branch and read the run — three jobs present, 9.2 green.

**Steps:**

- [ ] **Step 1: Write the workflow**

```yaml
name: Live smoke

on:
  push:
    branches: [main]
  pull_request:

jobs:
  smoke:
    name: Smoke (PrestaShop ${{ matrix.ps }})
    runs-on: ubuntu-latest

    strategy:
      fail-fast: false
      matrix:
        include:
          - ps: "1.7.8.11"
            service: ps17
            port: 8017
            theme: classic
          - ps: "8.2.8"
            service: ps82
            port: 8082
            theme: classic
          - ps: "9.2.0"
            service: ps92
            port: 8092
            theme: hummingbird

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Start the shop
        run: docker compose up ${{ matrix.service }} -d

      - name: Wait for the shop
        run: |
          for i in $(seq 1 60); do
            if curl -sf "http://localhost:${{ matrix.port }}/" >/dev/null; then
              echo "shop is up"; exit 0
            fi
            sleep 5
          done
          echo "shop never came up"; docker compose logs ${{ matrix.service }}; exit 1

      - name: Run the smoke suite
        env:
          PRESTAFLOW_FO_URL: http://localhost:${{ matrix.port }}/
          PRESTAFLOW_BO_URL: http://localhost:${{ matrix.port }}/admin-dev/
          PRESTAFLOW_BO_EMAIL: admin@prestashop.com
          PRESTAFLOW_BO_PASSWD: prestashop
          PRESTAFLOW_PS_VERSION: ${{ matrix.ps }}
          PRESTAFLOW_THEME: ${{ matrix.theme }}
          PRESTAFLOW_LOCALE: en
        run: php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php

      - name: Upload failure screenshots
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: screenshots-${{ matrix.ps }}
          path: prestaflow/screens/
          if-no-files-found: ignore
```

- [ ] **Step 2: Say in the README that red is expected, and why**

Append to the section added in Task 5:

```markdown
The 1.7 and 8.2 rows of the live-smoke matrix are expected to fail today. Their
page objects are nine-line stubs inheriting the v9 selectors, so the matrix is
reporting an absence of support rather than a regression. Each failure names the
page that differs, which is the inventory the v7/v8 work needs.
```

- [ ] **Step 3: Push and read the run**

```bash
git add .github/workflows/live-smoke.yml
git commit -m "ci: run the smoke suite against 1.7, 8.2 and 9.2"
git push origin HEAD
gh run list --limit 1
```

Expected: three jobs, 9.2 green, the other two red with named failures.

---

### Task 5: Documentation

**Goal:** Someone who has never seen this repo can get a shop running and a suite passing in three commands.

**Files:**
- Create: `docs/testing-with-flashlight.md`
- Modify: `README.md`

**Acceptance Criteria:**
- [ ] The guide covers: start a shop, configure, run a suite, tear down, and the two known traps.
- [ ] `README.md` links it.
- [ ] Every command in the guide has been run.

**Verify:** follow the guide from a clean checkout on a machine with no shop running; the smoke suite passes.

**Steps:**

- [ ] **Step 1: Write `docs/testing-with-flashlight.md`**

```markdown
# Testing against a throwaway shop

PrestaFlow drives a real PrestaShop. These are disposable ones, from the
official [Flashlight](https://github.com/PrestaShop/PrestaShop-Flashlight)
images, so nothing depends on a shop somebody set up by hand.

## Start a shop

```bash
docker compose up ps92 -d     # 9.2.0 on :8092, or ps82 / ps17
```

The first boot provisions a second shop at `/shop2/` as well, so multistore is
there by default rather than being something you build once and forget.

## Configure

```bash
cp .env.flashlight.example .env.flashlight
```

The back office is at `/admin-dev/` with `admin@prestashop.com` / `prestashop`
— fixed by the image, so there is no random folder to look up.

## Run a suite

```bash
set -a; . ./.env.flashlight; set +a
php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php
```

## Tear down

```bash
docker compose down -v        # -v also drops the database volume
```

Leaving out `-v` keeps the data, which is occasionally what you want and
usually how you end up debugging a shop whose state you no longer understand.

## Two traps worth knowing

**A shop on a virtual URI serves no assets until `.htaccess` is regenerated.**
The provisioning script does it. If you add a shop by hand, the front office
renders unstyled, the JavaScript never loads, and add-to-cart silently does
nothing.

**A duplicated shop has no payment methods.** `ps_module_carrier` is not copied
by PrestaShop (see
[PrestaShop#42964](https://github.com/PrestaShop/PrestaShop/issues/42964)), so
checkout stops at "no payment method available". The provisioning script copies
the rows.
```

- [ ] **Step 2: Add the README section**

```markdown
## Run a suite against a throwaway shop

```bash
docker compose up ps92 -d
cp .env.flashlight.example .env.flashlight
set -a; . ./.env.flashlight; set +a
php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php
```

See [Testing with Flashlight](docs/testing-with-flashlight.md).
```

- [ ] **Step 3: Follow the guide from scratch**

```bash
docker compose down -v
# then run every command in the guide, in order
```

Expected: the smoke suite passes.

- [ ] **Step 4: Commit**

```bash
git add docs/testing-with-flashlight.md README.md
git commit -m "docs: testing against a throwaway flashlight shop"
```

---

### Task 6: Retire the hand-made container

**Goal:** One shop story, not two. The `ps92rc1` container stops being the reference.

**Files:**
- Delete: `docs/superpowers/plans/2026-07-10-phase2-task0-flashlight-infra.md`

**Acceptance Criteria:**
- [ ] The superseded July plan is gone.
- [ ] `docs/testing-with-flashlight.md` is the only documented way to get a shop.
- [ ] No committed file references `ps92rc1` or a hand-made admin folder.

**Verify:** `grep -rn "ps92rc1" --include="*.md" --include="*.php" --include="*.yml" . | grep -v vendor` → no output.

**Steps:**

- [ ] **Step 1: Check what still refers to the old container**

```bash
grep -rn "ps92rc1\|admin259je3iqgg4jvpdzdlw" --include="*.md" --include="*.php" --include="*.yml" . | grep -v vendor/ || echo "no references"
```

- [ ] **Step 2: Remove the superseded plan**

```bash
# Untracked, so plain rm — `git rm` would fail with "did not match any files".
rm -f docs/superpowers/plans/2026-07-10-phase2-task0-flashlight-infra.md
rm -f docs/superpowers/plans/2026-07-10-phase2-task0-flashlight-infra.md.tasks.json
```

- [ ] **Step 3: Commit**

```bash
git commit -m "chore: retire the hand-made reference container"
```

**Note for whoever runs this task:** the `ps92rc1` container and its snapshots
in `~/prestaflow-ps92rc1-*` belong to the developer's machine, not to the repo.
Deleting them is the developer's call, not this plan's — say what is now
redundant and let them decide.

---

## Self-Review Notes

**Spec coverage.** The two decisions taken on 2026-09-24 are both implemented:
Flashlight replaces the hand-made container (Tasks 1, 2 and 6), and the smoke
suite runs on every version rather than skipping the ones expected to fail
(Task 4).

**Placeholders.** None. Every file is given in full, every command is runnable,
and the one genuinely conditional step — Task 3 Step 2, if a page method turns
out to be missing — says to implement the method rather than to weaken the test.

**Type consistency.** Service names (`ps17`, `ps82`, `ps92`), ports (8017, 8082,
8092) and database services (`db17`, `db82`, `db92`) are used identically in the
compose file, the workflow matrix and the documentation.

**Correction applied 2026-09-24, after the Task 1 spec review.** Task 1's verify
line originally curled `/` on port 8092 and would have reported success from a
leftover container that happened to hold the port. It now probes `/admin-dev/`,
which only Flashlight serves, and states the free-port precondition. Every other
port check in this plan inherits the same hazard: while anything else holds 8092,
Tasks 2, 3 and 5 verify against the wrong shop. Free the port before running them.

**Known risk, stated rather than hidden.** Task 2 provisions the second shop by
calling `Shop::copyShopData()` from the CLI. That method reads request state and
its behaviour across 1.7, 8.2 and 9.2 has only been verified on 9.2. If it
fails on an older version, `ON_POST_SCRIPT_FAILURE=fail` aborts the container —
loudly, which is the right failure. The fallback is to gate the script on the
version it runs under.
