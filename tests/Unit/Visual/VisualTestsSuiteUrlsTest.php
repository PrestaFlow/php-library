<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\VisualTestsSuite;

/** $shopUrl / $backOfficeUrl : URL du fichier de suite, prioritaires sur les globals. Aucun Chrome. */
final class VisualTestsSuiteUrlsTest extends TestCase
{
    private function suite(?string $shopUrl, ?string $backOfficeUrl, string $area = 'fo', array $globals = []): VisualTestsSuite
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends VisualTestsSuite {
            protected array $devices = ['desktop'];
            protected array $locales = ['fr'];
            protected array $checkpoints = [];

            /** URL vues par les pages : copiées des globals à l'import. */
            public array $imported = [];

            protected function importVisualPage(): void
            {
                $this->imported[] = ['FO' => $this->globals['FO']['URL'] ?? null, 'BO' => $this->globals['BO']['URL'] ?? null];
            }

            public function define(?string $shopUrl, ?string $backOfficeUrl, string $area): void
            {
                $this->shopUrl = $shopUrl;
                $this->backOfficeUrl = $backOfficeUrl;
                $this->area = $area;
            }
        };
        $suite->define($shopUrl, $backOfficeUrl, $area);
        $suite->setGlobals(array_merge([
            'PS_VERSION' => '8.1.0', 'LOCALE' => 'fr', 'PREFIX_LOCALE' => false, 'DEVICE' => 'desktop',
            'FO' => ['URL' => 'https://env.test/', 'EMAIL' => 'c@env.test', 'PASSWD' => 'c'],
            'BO' => ['URL' => 'https://env.test/admin-env/', 'EMAIL' => 'e@env.test', 'PASSWD' => 'e'],
        ], $globals));

        return $suite;
    }

    public function test_suite_without_properties_keeps_the_globals(): void
    {
        $suite = $this->suite(null, null);
        $suite->init();

        $this->assertSame('https://env.test/', $suite->getGlobals()['FO']['URL']);
        $this->assertSame('https://env.test/admin-env/', $suite->getGlobals()['BO']['URL']);
        $this->assertSame('c@env.test', $suite->getGlobals()['FO']['EMAIL']);
    }

    public function test_empty_properties_keep_the_globals(): void
    {
        $suite = $this->suite('', '  ');
        $suite->init();

        $this->assertSame('https://env.test/', $suite->getGlobals()['FO']['URL']);
        $this->assertSame('https://env.test/admin-env/', $suite->getGlobals()['BO']['URL']);
    }

    public function test_shop_url_wins_over_the_globals_before_pages_are_imported(): void
    {
        $suite = $this->suite('http://localhost:8093', null);
        $suite->init();

        $this->assertSame('http://localhost:8093/', $suite->getGlobals()['FO']['URL']);
        $this->assertSame('http://localhost:8093/', $suite->imported[0]['FO']);
        $this->assertSame('http://localhost:8093/connexion', $suite->resolveUrl('connexion', 'fr'));
        // Identifiants client et URL BO : ceux de l'environnement.
        $this->assertSame('c@env.test', $suite->getGlobals()['FO']['EMAIL']);
        $this->assertSame('https://env.test/admin-env/', $suite->getGlobals()['BO']['URL']);
    }

    public function test_absolute_back_office_url_wins_over_the_globals(): void
    {
        $suite = $this->suite(null, 'https://admin.shop.test/admin123', 'bo');
        $suite->init();

        $this->assertSame('https://admin.shop.test/admin123/', $suite->getGlobals()['BO']['URL']);
        $this->assertSame('https://admin.shop.test/admin123/', $suite->imported[0]['BO']);
        $this->assertSame('e@env.test', $suite->getGlobals()['BO']['EMAIL']);
        $this->assertSame('https://env.test/', $suite->getGlobals()['FO']['URL']);
    }

    public function test_relative_back_office_url_is_completed_by_the_effective_shop_url(): void
    {
        // URL FO du fichier : chemin de la boutique gardé (même règle que App\Support\BackOfficeUrl::resolve).
        $suite = $this->suite('http://localhost:8093/shop', 'admin123/', 'bo');
        $suite->init();
        $this->assertSame('http://localhost:8093/shop/', $suite->getGlobals()['FO']['URL']);
        $this->assertSame('http://localhost:8093/shop/admin123/', $suite->getGlobals()['BO']['URL']);

        // Sans URL FO dans le fichier : celle des globals.
        $suite = $this->suite(null, '/admin-dev', 'bo');
        $suite->init();
        $this->assertSame('https://env.test/admin-dev/', $suite->getGlobals()['BO']['URL']);

        // Relative au protocole : schéma de l'URL FO effective.
        $suite = $this->suite('http://shop.test/', '//admin.shop.test/admin', 'bo');
        $suite->init();
        $this->assertSame('http://admin.shop.test/admin/', $suite->getGlobals()['BO']['URL']);
    }

    public function test_relative_back_office_url_without_any_shop_url_is_refused(): void
    {
        $suite = $this->suite(null, 'admin123/', 'bo', ['FO' => ['URL' => '', 'EMAIL' => '', 'PASSWD' => '']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$backOfficeUrl');
        $suite->init();
    }

    public function test_applying_twice_gives_the_same_urls(): void
    {
        $suite = $this->suite('http://localhost:8093/', 'admin123', 'bo');
        $suite->applySuiteUrls();
        $suite->applySuiteUrls();

        $this->assertSame('http://localhost:8093/', $suite->getGlobals()['FO']['URL']);
        $this->assertSame('http://localhost:8093/admin123/', $suite->getGlobals()['BO']['URL']);
    }

    public function test_credentials_in_a_url_are_refused_without_quoting_it(): void
    {
        $cases = [
            ['https://bob:s3cret@shop.test/', null, '$shopUrl'],
            ['https://bob@shop.test/', null, '$shopUrl'],
            [null, 'https://bob:s3cret@shop.test/admin/', '$backOfficeUrl'],
            [null, '//bob:s3cret@shop.test/admin/', '$backOfficeUrl'],
        ];
        foreach ($cases as [$shop, $backOffice, $property]) {
            try {
                $this->suite($shop, $backOffice, 'bo')->init();
                $this->fail('URL à identifiants acceptée : '.$property);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($property, $e->getMessage());
                $this->assertStringContainsString('PRESTAFLOW_BASIC_', $e->getMessage());
                $this->assertStringNotContainsString('s3cret', $e->getMessage());
                $this->assertStringNotContainsString('bob', $e->getMessage());
            }
        }
    }

    public function test_a_scheme_other_than_http_is_refused_without_quoting_the_url(): void
    {
        $cases = [
            ['ftp://shop.test/', null, '$shopUrl'],
            ['localhost:8093/', null, '$shopUrl'],
            ['shop.test', null, '$shopUrl'],
            ['https:///sans-hote', null, '$shopUrl'],
            [null, 'javascript:alert(1)', '$backOfficeUrl'],
            [null, 'ftp://shop.test/admin/', '$backOfficeUrl'],
        ];
        foreach ($cases as [$shop, $backOffice, $property]) {
            try {
                $this->suite($shop, $backOffice, 'bo')->init();
                $this->fail('URL refusable acceptée : '.$property);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($property, $e->getMessage());
                $this->assertStringContainsString('http', $e->getMessage());
                $this->assertStringNotContainsString((string) ($shop ?? $backOffice), $e->getMessage());
            }
        }
    }

    public function test_open_back_office_checkpoint_applies_the_suite_urls_before_importing_the_pages(): void
    {
        // Sélecteur visuel de l'app : init() n'est pas appelé.
        $suite = $this->suite('http://localhost:8093/', 'admin123/', 'bo');

        try {
            $suite->openBackOfficeCheckpoint(['name' => 'picker']);
            $this->fail('page BackOffice absente attendue');
        } catch (\RuntimeException $e) {
            $this->assertSame('page BackOffice absente', $e->getMessage());
        }

        $this->assertSame(['FO' => 'http://localhost:8093/', 'BO' => 'http://localhost:8093/admin123/'], $suite->imported[0]);
    }

    /** @return array<string, array{string}> */
    public static function unsafeRelativeBackOfficeUrls(): array
    {
        return [
            'hôte sans schéma' => ['shop.test/admin'],
            'premier segment à point' => ['admin.v2/'],
            'remontée' => ['../admin'],
            'segment caché' => ['.hidden/'],
            'segment caché encodé' => ['admin/%2e%2e/x'],
            'espace' => ['admin 1'],
            'requête' => ['admin?x=1'],
            'fragment' => ['admin#x'],
            'double slash interne' => ['admin//x'],
        ];
    }

    /** @dataProvider unsafeRelativeBackOfficeUrls */
    public function test_unsafe_relative_back_office_url_is_refused_without_quoting_it(string $value): void
    {
        try {
            $this->suite(null, $value, 'bo')->init();
            $this->fail('BO relative non sûre acceptée');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('$backOfficeUrl', $e->getMessage());
            $this->assertStringNotContainsString($value, $e->getMessage());
        }
    }

    public function test_safe_relative_back_office_urls_are_accepted(): void
    {
        foreach (['/admin.v2/' => 'https://env.test/admin.v2/', 'admin/v2.1' => 'https://env.test/admin/v2.1/', 'adm%C3%AFn' => 'https://env.test/adm%C3%AFn/'] as $value => $expected) {
            $suite = $this->suite(null, $value, 'bo');
            $suite->init();
            $this->assertSame($expected, $suite->getGlobals()['BO']['URL'], $value);
        }
    }

    public function test_internal_whitespace_is_refused_for_both_urls(): void
    {
        foreach ([["http://shop.test/a b", null], [null, "http://shop.test/a\tb"], [null, "admin\n1"]] as [$shop, $backOffice]) {
            try {
                $this->suite($shop, $backOffice, 'bo')->init();
                $this->fail('blanc interne accepté');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringNotContainsString('shop.test', $e->getMessage());
            }
        }
    }

    public function test_protocol_relative_back_office_url_without_http_shop_url_is_refused(): void
    {
        $suite = $this->suite(null, '//admin.shop.test/admin', 'bo', ['FO' => ['URL' => '', 'EMAIL' => '', 'PASSWD' => '']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$backOfficeUrl');
        $suite->init();
    }

    public function test_shop_url_with_query_or_fragment_is_refused(): void
    {
        foreach (['http://shop.test/?x=1', 'http://shop.test/#top'] as $shop) {
            try {
                $this->suite($shop, null)->init();
                $this->fail('$shopUrl avec requête ou fragment acceptée');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('$shopUrl', $e->getMessage());
                $this->assertStringNotContainsString('shop.test', $e->getMessage());
            }
        }
    }

    public function test_absolute_back_office_url_with_ipv6_and_port(): void
    {
        $suite = $this->suite(null, 'http://[::1]:8080/admin123', 'bo');
        $suite->init();

        $this->assertSame('http://[::1]:8080/admin123/', $suite->getGlobals()['BO']['URL']);
    }

    public function test_uppercase_scheme_is_accepted(): void
    {
        $suite = $this->suite('HTTPS://shop.test', 'HTTPS://shop.test/admin', 'bo');
        $suite->init();

        $this->assertSame('HTTPS://shop.test/', $suite->getGlobals()['FO']['URL']);
        $this->assertSame('HTTPS://shop.test/admin/', $suite->getGlobals()['BO']['URL']);
    }

    public function test_surrounding_blanks_are_trimmed(): void
    {
        $suite = $this->suite('  https://shop.test  ', null);
        $suite->init();

        $this->assertSame('https://shop.test/', $suite->getGlobals()['FO']['URL']);
    }

    public function test_query_and_fragment_of_the_globals_shop_url_are_dropped_for_a_relative_back_office_url(): void
    {
        $suite = $this->suite(null, 'admin123', 'bo', ['FO' => ['URL' => 'http://fo.test/fr?x=1#a', 'EMAIL' => '', 'PASSWD' => '']]);
        $suite->init();

        $this->assertSame('http://fo.test/fr/admin123/', $suite->getGlobals()['BO']['URL']);
    }

    private function cliSuite(array $env, ?string $shopUrl): VisualTestsSuite
    {
        $keys = ['PRESTAFLOW_FO_URL', 'PRESTAFLOW_BO_URL'];
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
            unset($_ENV[$key]);
        }
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
        }
        try {
            $suite = new class (loadGlobals: true, getBrowser: false) extends VisualTestsSuite {
                protected array $devices = ['desktop'];
                protected array $locales = ['fr'];
                protected array $checkpoints = [];

                protected function importVisualPage(): void
                {
                }

                public function pin(?string $shopUrl): void
                {
                    $this->shopUrl = $shopUrl;
                }
            };
        } finally {
            foreach ($saved as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value;
                }
            }
        }
        $suite->pin($shopUrl);

        return $suite;
    }

    public function test_cli_default_back_office_url_follows_the_suite_shop_url(): void
    {
        $suite = $this->cliSuite(['PRESTAFLOW_FO_URL' => 'https://env.test/'], 'http://localhost:8093');
        $suite->applySuiteUrls();

        $this->assertSame('http://localhost:8093/', $suite->getGlobals()['FO']['URL']);
        $this->assertSame('http://localhost:8093/admin-dev/', $suite->getGlobals()['BO']['URL']);
    }

    public function test_cli_relative_back_office_url_follows_the_suite_shop_url(): void
    {
        $suite = $this->cliSuite(['PRESTAFLOW_FO_URL' => 'https://env.test/', 'PRESTAFLOW_BO_URL' => 'admin123'], 'http://localhost:8093');
        $this->assertSame('https://env.test/admin123/', $suite->getGlobals()['BO']['URL']);
        $suite->applySuiteUrls();
        $suite->applySuiteUrls();

        $this->assertSame('http://localhost:8093/admin123/', $suite->getGlobals()['BO']['URL']);
    }

    public function test_cli_absolute_back_office_url_is_kept_with_the_suite_shop_url(): void
    {
        $suite = $this->cliSuite(['PRESTAFLOW_FO_URL' => 'https://env.test/', 'PRESTAFLOW_BO_URL' => 'https://bo.env.test/admin/'], 'http://localhost:8093');
        $suite->applySuiteUrls();

        $this->assertSame('https://bo.env.test/admin/', $suite->getGlobals()['BO']['URL']);
    }

    public function test_provided_globals_back_office_url_is_kept_with_the_suite_shop_url(): void
    {
        // Cas de l'app : setGlobals() fournit FO et BO ; $shopUrl ne touche pas à BO.
        $suite = $this->cliSuite(['PRESTAFLOW_FO_URL' => 'https://env.test/'], 'http://localhost:8093');
        $suite->setGlobals(['BO' => ['URL' => 'https://env.test/admin-app/', 'EMAIL' => 'e', 'PASSWD' => 'p']]);
        $suite->applySuiteUrls();

        $this->assertSame('http://localhost:8093/', $suite->getGlobals()['FO']['URL']);
        $this->assertSame('https://env.test/admin-app/', $suite->getGlobals()['BO']['URL']);
    }
}
