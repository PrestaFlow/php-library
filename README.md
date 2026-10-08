# Let's Build Together 🚀

PrestaFlow is an open-source set of prebuilt tests components, ready-to-use examples made for the PrestaShop E-commerce software.

As now, it's the first library of its kind that lets you write and manage automated tests on your PrestaShop in PHP.

More info : [see the documentation](https://prestaflow.io/docs/).

## Pin a PrestaShop version per suite

By default, PrestaFlow reads the target PrestaShop version from `PRESTAFLOW_PS_VERSION` in your `.env`. To pin a version per suite — useful when a single repo tests both a 1.7 shop and a 9.x shop (typical migration workflow):

```php
class MigrationBaselineSuite extends TestsSuite
{
    protected $psVersion = '1.7.8.11';   // static: pinned at class level

    public function init(): void
    {
        // ...or fluent: pinned at boot time
        $this->onVersion($_ENV['SHOP_UNDER_TEST'] ?? '1.7.8.11');
        $this->importPage('BackOffice\Login');
    }
}
```

Resolution priority: fluent `onVersion()` → `$psVersion` property → `PRESTAFLOW_PS_VERSION` env → default `8.1.0`.

`onVersion()` throws `InvalidArgumentException` on malformed input (expected format: `1.7`, `1.7.8`, `1.7.8.11`, `9`, `9.0`, `9.0.1`, etc.).

## Run only some suite folders

`prestaflow run <path>` runs every suite under `<path>`. To run only some of
its sub-folders, list them, comma-separated, relative to `<path>`:

```bash
./vendor/bin/prestaflow run tests --suites=BackOffice,FrontOffice/Checkout
PRESTAFLOW_SUITES=BackOffice,FrontOffice/Checkout ./vendor/bin/prestaflow run tests
```

- Each name is a sub-folder of `<path>`, scanned recursively; nested paths such
  as `FrontOffice/Checkout` are allowed. The run takes the union of the folders.
- `--suites` wins over `PRESTAFLOW_SUITES`. The GitHub Action sets
  `PRESTAFLOW_SUITES` from its `suites` input.
- A name that matches no folder fails the run (non-zero exit code), listing the
  missing names and the available sub-folders. A typo never becomes a green
  job that ran zero tests.
- A filtered run that ends up with no suite at all (an empty folder, or
  nothing left after `--group` / `--draft`) fails too, for the same reason.
- Absolute paths and `..` are refused: the filter can only narrow `<path>`.
- `--group` and `--draft` still apply, on the suites of the selected folders.
- Unset or empty: every suite under `<path>` runs, as before.

## Visual regression runs

`VisualTestsSuite` reads these variables:

| Variable | Effect |
|---|---|
| `PRESTAFLOW_DEVICE` | Device preset of the run (`desktop`, `mobile`, …); must be declared in the suite's `$devices`. Default: first declared device. |
| `PRESTAFLOW_LOCALE` | Locale of the run; must be declared in the suite's `$locales`. Default: first declared locale. |
| `PRESTAFLOW_VISUAL_ONLY` | Comma-separated checkpoint names, as declared in `$checkpoints` (e.g. `home,footer`). Only those run, in declaration order; the others are not in the results at all. Spaces are trimmed, unknown names are ignored — but if **none** matches, the run fails with a step listing the unknown and declared names. Unset or empty: every checkpoint runs. |
| `PRESTAFLOW_VISUAL_UPDATE` | Boolean (`1`, `true`, `yes`, `on`). Captures exactly as usual (masks, device, scroll, element clip) but writes each capture as the reference instead of comparing it. The result is `status: baseline` with `updated: true` when a reference was replaced (`false` for a first baseline); no diff image. |
| `PRESTAFLOW_CDP_TIMEOUT` | Timeout in milliseconds of the synchronous Chrome calls (evaluate, querySelector…), for every suite, not only visual ones. Default: `5000`, chrome-php's own default. Visual captures are always written with a 30 s timeout. |

```bash
PRESTAFLOW_VISUAL_ONLY=home,footer PRESTAFLOW_VISUAL_UPDATE=1 ./vendor/bin/prestaflow run tests/Visual
```

### Back-office suites

A visual suite with `protected string $area = 'bo';` captures the back office. Every admin URL carries a token, so a checkpoint names a sidebar entry instead of a path:

| Key | Default | Effect |
|---|---|---|
| `menu` | `null` | Sidebar entry selector, or a comma-separated list (first present wins). The suite reads its link, which carries the token, and opens it. `null`: back-office root. |
| `auth` | `true` | `false`: captured logged out (the login page). Logged-out checkpoints run first; the suite then logs in once with `PRESTAFLOW_BO_EMAIL` / `PRESTAFLOW_BO_PASSWD`. |
| `hide` | `[]` | Selectors set to `display: none` during the capture (popups, modal backdrops). Works in both areas. |

The back-office area also uses `PRESTAFLOW_BO_URL` (admin URL, default `admin-dev/`), `PRESTAFLOW_BO_EMAIL` and `PRESTAFLOW_BO_PASSWD` (login). A relative `PRESTAFLOW_BO_URL` is completed from `PRESTAFLOW_FO_URL` with the same rule as `$backOfficeUrl` below (shop path kept, query and fragment dropped, `//host` takes the shop scheme); nothing is refused for environment variables.

CSS transitions are frozen during back-office captures (`protected ?bool $freezeTransitions`; `null` = on for `bo`, off for `fo`). See `src/Tests/Suites/Visual/BackOffice.php`.

### Shop and back-office URLs in the suite file

A visual suite may pin its own URLs (the PrestaFlow app writes them from its editor). A non-empty value replaces `PRESTAFLOW_FO_URL` / `PRESTAFLOW_BO_URL` (or the URLs sent by the app) before the first checkpoint:

```php
protected ?string $shopUrl = 'http://localhost:8093/';
protected ?string $backOfficeUrl = 'admin123/'; // relative: appended to the effective shop URL
```

`null` or empty: the environment URL. Rules:

- Only `http(s)` URLs with a host; no whitespace; `$shopUrl` takes no query (`?`) or fragment (`#`).
- A relative back-office URL is appended to the effective shop URL: the shop path is kept, its query and fragment are dropped (`http://x.test/shop/` + `admin123/` gives `http://x.test/shop/admin123/`). `//host/admin` takes the shop URL scheme. A relative path must be safe: no `?`/`#`, no segment starting with a dot, no dot in the first segment unless it starts with `/` (`shop.test/admin` is refused, not guessed). It needs an `http(s)` shop URL, else it is refused.
- When only `$shopUrl` is set, a back-office URL that the library completed from the environment shop URL (relative `PRESTAFLOW_BO_URL`, or the default `admin-dev/`) follows the new shop URL; an absolute `PRESTAFLOW_BO_URL`, or one sent by the app, is kept.
- Credentials (`user:pass@`) are refused at launch, without echoing the URL: use `PRESTAFLOW_BASIC_USER` / `PRESTAFLOW_BASIC_PASS`. The same goes for every other refusal: the message names the property, never the value.

### Visual picker API

The visual picker of the PrestaFlow app captures a page and lists its elements, so that a user can pick a checkpoint selector. It uses the run's own code, under short ceilings.

**Back-office page.** Open the checkpoint's page in the shared browser, capture it, log out, then close the browser:

```php
use PrestaFlow\Library\Exceptions\BackOfficeTimeoutException;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Visual\PageSnapshot;

TestsSuite::scopeBrowserFilesTo('picker-'.$jobId);
$browser = TestsSuite::getBrowser(force: true);
TestsSuite::applyEnvironment($browser, TestsSuite::getPage(), $boUrl); // Basic Auth, headers, cookies

$suite = new MyBackOfficeVisualSuite(loadGlobals: true, getBrowser: false);
try {
    $where = $suite->openBackOfficeCheckpoint(['name' => 'picker', 'menu' => '#subtab-AdminProducts']);
    $result = (new PageSnapshot())->captureCurrent(TestsSuite::getPage());
} catch (BackOfficeTimeoutException $e) {
    // the back office did not answer in time: readable message
} catch (\RuntimeException $e) {
    // run error, readable message: credentials refused, unexpected page, menu entry not found
} catch (\Exception $e) {
    // raw chrome-php error from captureCurrent() (OperationTimedOut, EvaluationFailed, ScreenshotFailed…)
} finally {
    $suite->closeBackOfficeSession();
    TestsSuite::resetBrowser();
    TestsSuite::clearEnvironment();
}
```

- `openBackOfficeCheckpoint(array $checkpoint, int $loginTimeoutMs = 25000, int $menuTimeoutMs = 15000): string` logs in (unless `auth => false`), opens the back-office root, then the `menu` entry. It returns the path and controller of the opened page, never its token. The login outcome is awaited until a deadline (`Login\Page::$loginOutcomeDeadline`, still bounded by `$loginOutcomeTimeout`), so a fast reload leaves the rest of the login budget to the outcome. The run's ceilings are restored before it returns, even on error.
- `PageSnapshot::captureCurrent(object $page, int $timeoutMs = 15000): SnapshotResult` captures the open tab without navigating or closing anything. It lets chrome-php exceptions through as they are (`OperationTimedOut`, `EvaluationFailed`, `ScreenshotFailed`…): they extend `\Exception`, not `\RuntimeException`, hence the last `catch` above. Their messages are not redacted.
- `closeBackOfficeSession(int $timeoutMs = 5000): void` follows the logout link of an open session. It never throws.
- `TestsSuite::resetBrowser()` closes the browser.

**Front-office page.** `(new PageSnapshot())->take($url, $device)` starts a dedicated browser, applies the run's environment (`TestsSuite::applyEnvironment()`, with `$url` as the cookies' default domain), opens the URL, captures the page and closes the browser. It starts from empty headers and restores `TestsSuite::$extraHttpHeaders` on return, so a run in the same process neither leaks into it nor is altered by it. It throws `SnapshotException` when Chrome cannot start or the page does not load in time. That list is not exhaustive: once the page is loaded, the capture (`captureCurrent()`) lets chrome-php exceptions through as they are.

**Exceptions of `openBackOfficeCheckpoint()`.**

| Exception | When |
|---|---|
| `BackOfficeTimeoutException` | The back office did not answer within the budget. It extends `\RuntimeException`: catch it first. |
| `\RuntimeException` | Run error with a readable message (credentials refused, unexpected page, menu entry not found, session already open for an `auth => false` checkpoint). |
| `\LogicException` | The suite's `$area` is not `bo`. |
| `\InvalidArgumentException` | An `auth => false` checkpoint with a `menu`. |

Messages never carry a token (`token`, `_token`, plain, encoded or double-encoded) nor URL credentials. `getPrevious()` keeps the original cause, which may hold the raw URL: never show or log it.

**Environment.** `TestsSuite::applyEnvironment($browser, $page, ?string $defaultUrl = null)` applies what `before()` applies to a run, in the same order: Basic Auth (`PRESTAFLOW_BASIC_USER` / `PRESTAFLOW_BASIC_PASS`), then the `PRESTAFLOW_EXTRA_HEADERS` headers on the connection and the page, then the `PRESTAFLOW_COOKIES` cookies. A cookie without `domain` takes the host of its own `url`, otherwise the host of `$defaultUrl` (the page is still on `about:blank`). Values are read from the environment only. Without these variables, nothing is set. The headers stay in `TestsSuite::$extraHttpHeaders` for the pages created afterwards: call `TestsSuite::clearEnvironment()` when releasing the browser, so that a persistent worker does not keep them for the next job. It only empties that array: headers already set on a browser's connection stay there, so close or reset that browser too (`TestsSuite::resetBrowser()`).

## Run a suite against a throwaway shop

`docker-compose.yml` boots a disposable PrestaShop from the official
[Flashlight](https://github.com/PrestaShop/prestashop-flashlight) images — one
service per version, each with its own database and its own port:

| Service | PrestaShop | Shop 1 | Shop 2 | Shop 1 theme | Shop 2 theme |
|---|---|---|---|---|---|
| `ps17` | 1.7.8.11 | 8017 | 8018 | classic | classic |
| `ps82` | 8.2.8 | 8082 | 8083 | classic | classic |
| `ps92` | 9.2.0 | 8092 | 8093 | hummingbird | classic |

The 9.2 container therefore gives you both themes from a single boot —
hummingbird on 8092, classic on 8093 — which is what you want for checking
that a theme-aware selector resolves per theme.

```bash
docker compose up ps92 -d
cp .env.flashlight.example .env.flashlight   # then point your run at it
php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php
```

Wait for `/admin-dev/` to answer `302` before running a suite, not for `/` to
answer `200`: the front office is served well before the post-scripts that
provision the second shop have finished. A failing post-script does **not**
stop the container, so a healthy container is not proof of a provisioned shop
— the smoke suite is.

Full walkthrough, including tear-down and the two traps worth knowing:
[Testing against a throwaway shop](docs/testing-with-flashlight.md).

The `Live smoke` workflow runs that same suite against all three versions on
every push to `main` and every pull request.

**The 1.7 and 8.2 rows of the live-smoke matrix are expected to fail today.**
Their page objects are nine-line stubs inheriting the v9 selectors, so the
matrix is reporting an absence of support rather than a regression. Each
failure names the page that differs, which is the inventory the v7/v8 work
needs. The rows are deliberately not skipped and not marked
`continue-on-error`; `fail-fast: false` keeps them from cancelling the 9.2 row.
