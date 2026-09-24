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
