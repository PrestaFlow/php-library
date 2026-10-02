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
}
