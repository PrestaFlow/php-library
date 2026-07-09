<?php

namespace PrestaFlow\Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Traits\Version;

final class VersionOverrideTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_ENV['PRESTAFLOW_PS_VERSION']);
    }

    private function makeSuite(?string $psVersion = null): object
    {
        $suite = new class {
            use Version;
            public array $globals = [];
            public ?string $psVersion = null;
        };
        // Reset the static state via the concrete class using the trait so no deprecation is raised.
        $suite::$versions = [
            'patchVersion' => null,
            'minorVersion' => null,
            'majorVersion' => null,
        ];
        $suite->psVersion = $psVersion;

        return $suite;
    }

    public function testPropertyOverrideResolvesTo17(): void
    {
        $suite = $this->makeSuite('1.7.8.11');
        $suite->resolveVersion();

        $this->assertSame('1.7', $suite->getMajorVersion());
        $this->assertSame('7', $suite->getMajorVersion(namespace: true));
    }

    public function testFluentOnVersionResolvesTo9(): void
    {
        $suite = $this->makeSuite();
        $returned = $suite->onVersion('9.0.1');
        $this->assertSame($suite, $returned, 'onVersion() must be chainable');

        $suite->resolveVersion();

        $this->assertSame('9', $suite->getMajorVersion());
    }

    public function testFluentOverridesProperty(): void
    {
        $suite = $this->makeSuite('1.7.8.11');
        $suite->onVersion('9.0.1');
        $suite->resolveVersion();

        $this->assertSame('9', $suite->getMajorVersion());
    }

    public function testEnvIsUsedWhenNoOverride(): void
    {
        $_ENV['PRESTAFLOW_PS_VERSION'] = '8.1.5';
        $suite = $this->makeSuite();
        $suite->resolveVersion();

        $this->assertSame('8', $suite->getMajorVersion());
    }

    public function testOnVersionThrowsOnInvalidInput(): void
    {
        $suite = $this->makeSuite();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid PS version");
        $suite->onVersion('garbage');
    }
}
