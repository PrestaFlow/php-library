<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class ManageOrderTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\ManageOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testDeclaresManagementParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\ManageOrder');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['orderStatus', 'internalNote', 'trackingNumber'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }

    public function testSuiteComposesBothScenarios(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\OrderLifecycle';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
        $ref = new \ReflectionMethod($class, 'init');
        $body = implode('', array_slice(file($ref->getFileName()), $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
        $this->assertStringContainsString('CheckoutOrder', $body);
        $this->assertStringContainsString('ManageOrder', $body);
    }
}
