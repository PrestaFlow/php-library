<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * The per-suite version override has to reach a real suite, not only a bare
 * trait on which the test calls resolveVersion() itself: loadGlobals() used to
 * read PRESTAFLOW_PS_VERSION directly, so $psVersion and onVersion() changed
 * nothing and importPage() kept loading the environment's namespace.
 */
final class SuiteVersionWiringTest extends TestCase
{
    private ?string $savedEnv = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedEnv = $_ENV['PRESTAFLOW_PS_VERSION'] ?? null;
        $_ENV['PRESTAFLOW_PS_VERSION'] = '9.0.0';
        putenv('PRESTAFLOW_PS_VERSION=9.0.0');
    }

    protected function tearDown(): void
    {
        if ($this->savedEnv === null) {
            unset($_ENV['PRESTAFLOW_PS_VERSION']);
            putenv('PRESTAFLOW_PS_VERSION');
        } else {
            $_ENV['PRESTAFLOW_PS_VERSION'] = $this->savedEnv;
            putenv('PRESTAFLOW_PS_VERSION=' . $this->savedEnv);
        }
        parent::tearDown();
    }

    public function test_the_environment_version_applies_without_an_override(): void
    {
        $suite = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {};

        $this->assertSame('9', $suite->getMajorVersion(namespace: true));
        $this->assertSame('9.0.0', $suite->getGlobals()['PS_VERSION']);
    }

    public function test_the_ps_version_property_wins_over_the_environment(): void
    {
        $suite = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            protected $psVersion = '1.7.8.11';
        };

        $this->assertSame('7', $suite->getMajorVersion(namespace: true));
        $this->assertSame('1.7.8.11', $suite->getGlobals()['PS_VERSION']);
    }

    public function test_on_version_takes_effect_without_calling_resolve_version(): void
    {
        $suite = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {};

        $suite->onVersion('8.2.0');

        $this->assertSame('8', $suite->getMajorVersion(namespace: true));
        $this->assertSame('8.2.0', $suite->getGlobals()['PS_VERSION']);
    }

    public function test_on_version_in_init_decides_the_namespace_import_page_loads(): void
    {
        $suite = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            public function init()
            {
                $this->onVersion('8.2.0');
                $this->importPage('FrontOffice\Home');

                return $this;
            }
        };

        $suite->init();

        $this->assertInstanceOf(
            \PrestaFlow\Library\Pages\v8\FrontOffice\Home\Page::class,
            $suite->pages['frontOfficeHomePage']
        );
    }

    public function test_a_suite_without_globals_still_resolves_its_version(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
            protected $psVersion = '1.7.8.11';
        };

        $this->assertSame('1.7', $suite->getMajorVersion());
    }

    public function test_two_suites_import_pages_of_their_own_version(): void
    {
        $old = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            protected $psVersion = '1.7.8.11';
        };
        $old->importPage('FrontOffice\Home');

        $new = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            protected $psVersion = '9.0.0';
        };
        $new->importPage('FrontOffice\Home');

        $this->assertInstanceOf(\PrestaFlow\Library\Pages\v7\FrontOffice\Home\Page::class, $old->pages['frontOfficeHomePage']);
        $this->assertInstanceOf(\PrestaFlow\Library\Pages\v9\FrontOffice\Home\Page::class, $new->pages['frontOfficeHomePage']);
        $this->assertSame('1.7', $old->pages['frontOfficeHomePage']->getMajorVersion());
        $this->assertSame('9', $new->pages['frontOfficeHomePage']->getMajorVersion());
    }

    public function test_a_patch_version_global_decides_both_the_namespace_and_the_page_version(): void
    {
        $suite = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            protected $psVersion = '8.1.0';
        };
        $suite->importPage('FrontOffice\Home', globals: ['PATCH_VERSION' => '1.7.8.11']);

        $page = $suite->pages['frontOfficeHomePage'];
        $this->assertInstanceOf(\PrestaFlow\Library\Pages\v7\FrontOffice\Home\Page::class, $page);
        $this->assertSame('1.7', $page->getMajorVersion());
    }

    public function test_a_scenario_imports_pages_of_its_own_version(): void
    {
        $suite = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            protected $psVersion = '1.7.8.11';
        };
        $scenario = new \PrestaFlow\Library\Scenarios\Scenario($suite, ['locale' => 'en']);
        $scenario->importPage('FrontOffice\Home');

        $page = $scenario->pages['frontOfficeHomePage'];
        $this->assertInstanceOf(\PrestaFlow\Library\Pages\v7\FrontOffice\Home\Page::class, $page);
        $this->assertSame('1.7.8.11', $page->getPatchVersion());
    }

    public function test_a_patch_version_without_pages_is_a_clear_error(): void
    {
        $suite = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            protected $psVersion = '8.1.0';
        };

        $this->expectException(\PrestaFlow\Library\Exceptions\InvalidVersionException::class);
        $suite->importPage('FrontOffice\Home', globals: ['PATCH_VERSION' => '10.0.0']);
    }

    public function test_an_unsupported_old_version_is_a_clear_error(): void
    {
        $suite = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            protected $psVersion = '8.1.0';
        };

        $this->expectException(\PrestaFlow\Library\Exceptions\InvalidVersionException::class);
        $this->expectExceptionMessage('non prise en charge');
        $suite->importPage('FrontOffice\Home', globals: ['PATCH_VERSION' => '1.6.1.24']);
    }
}
