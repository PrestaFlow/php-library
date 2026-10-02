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

The back-office area also uses `PRESTAFLOW_BO_URL` (admin URL), `PRESTAFLOW_BO_EMAIL` and `PRESTAFLOW_BO_PASSWD` (login).

CSS transitions are frozen during back-office captures (`protected ?bool $freezeTransitions`; `null` = on for `bo`, off for `fo`). See `src/Tests/Suites/Visual/BackOffice.php`.

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
