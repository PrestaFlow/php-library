<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Tests\VisualTestsSuite;

final class VisualTestsSuiteTest extends TestCase
{
    private function suite(array $globals = []): VisualTestsSuite
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends VisualTestsSuite {
            protected array $devices = ['desktop', 'mobile'];
            protected array $locales = ['fr', 'en'];
            protected array $checkpoints = [
                ['name' => 'login', 'path' => 'connexion', 'paths' => ['en' => 'login']],
                ['name' => 'footer', 'path' => '', 'zone' => 'element', 'selector' => '#footer', 'excludeDevices' => ['mobile']],
                ['name' => 'promos', 'path' => 'promotions', 'paths' => ['en' => false]],
            ];
            // pas de page réelle en test unitaire
            protected function importVisualPage(): void {}
        };
        $suite->setGlobals(array_merge([
            'PS_VERSION' => '8.1.0', 'LOCALE' => 'fr', 'PREFIX_LOCALE' => true,
            'FO' => ['URL' => 'https://shop.test', 'EMAIL' => '', 'PASSWD' => ''],
            'BO' => ['URL' => '', 'EMAIL' => '', 'PASSWD' => ''],
        ], $globals));

        return $suite;
    }

    protected function tearDown(): void
    {
        TestsSuite::useBrowserOptions(null, null, null);
    }

    public function test_resolve_path(): void
    {
        $s = $this->suite();
        $this->assertSame('connexion', $s->resolvePath(['path' => 'connexion', 'paths' => ['en' => 'login']], 'fr'));
        $this->assertSame('login', $s->resolvePath(['path' => 'connexion', 'paths' => ['en' => 'login']], 'en'));
        $this->assertNull($s->resolvePath(['path' => 'x', 'paths' => ['en' => false]], 'en'));
        $this->assertSame('x', $s->resolvePath(['path' => 'x', 'paths' => ['en' => '']], 'en'));
    }

    public function test_resolve_url(): void
    {
        $this->assertSame('https://shop.test/fr/connexion', $this->suite()->resolveUrl('connexion', 'fr'));
        $this->assertSame('https://shop.test/connexion', $this->suite(['PREFIX_LOCALE' => false])->resolveUrl('/connexion', 'fr'));
        $this->assertSame('https://shop.test/fr/', $this->suite()->resolveUrl('', 'fr'));
    }

    public function test_current_device_from_globals_then_default(): void
    {
        $this->assertSame('desktop', $this->suite()->currentDevice());
        $this->assertSame('mobile', $this->suite(['DEVICE' => 'mobile'])->currentDevice());
    }

    public function test_init_registers_tests_and_skips(): void
    {
        $s = $this->suite(['DEVICE' => 'mobile', 'LOCALE' => 'en']);
        $s->init();
        $tests = array_values($s->tests);

        $this->assertCount(3, $tests);
        $this->assertSame('capture visuelle : login', $tests[0]['title']);
        $this->assertArrayNotHasKey('skip', $tests[0]);
        $this->assertTrue($tests[1]['skip'] ?? false);   // footer exclu sur mobile
        $this->assertTrue($tests[2]['skip'] ?? false);   // promos sautée en EN
    }

    public function test_normalize_defaults(): void
    {
        $cp = VisualTestsSuite::normalize(['name' => 'x', 'path' => '']);
        $this->assertSame('viewport', $cp['zone']);
        $this->assertSame(0.98, $cp['threshold']);
        $this->assertSame([], $cp['masks']);
        $this->assertSame([], $cp['excludeDevices']);
    }
}
