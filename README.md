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

## Run a suite against a throwaway shop

`docker-compose.yml` boots a disposable PrestaShop from the official
[Flashlight](https://github.com/PrestaShop/prestashop-flashlight) images — one
service per version, each with its own database and its own port:

| Service | PrestaShop | Shop 1 | Shop 2 | Theme |
|---|---|---|---|---|
| `ps17` | 1.7.8.11 | 8017 | 8018 | classic |
| `ps82` | 8.2.8 | 8082 | 8083 | classic |
| `ps92` | 9.2.0 | 8092 | 8093 | hummingbird |

```bash
docker compose up ps92 -d
cp .env.flashlight.example .env.flashlight   # then point your run at it
php bin/prestaflow run src/Tests/Suites/Smoke/FrontOfficeSmoke.php
```

Wait for `/admin-dev/` to answer `302` before running a suite, not for `/` to
answer `200`: the front office is served well before the post-scripts that
provision the second shop have finished.

The `Live smoke` workflow runs that same suite against all three versions on
every push to `main` and every pull request.

**The 1.7 and 8.2 rows of the live-smoke matrix are expected to fail today.**
Their page objects are nine-line stubs inheriting the v9 selectors, so the
matrix is reporting an absence of support rather than a regression. Each
failure names the page that differs, which is the inventory the v7/v8 work
needs. The rows are deliberately not skipped and not marked
`continue-on-error`; `fail-fast: false` keeps them from cancelling the 9.2 row.
