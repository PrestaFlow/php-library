<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class OnePageCheckoutOrderTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\OnePageCheckoutOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\OnePageCheckoutOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresItsParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\OnePageCheckoutOrder');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['locale', 'customerEmail', 'customerPassword', 'productUrl', 'cartQuantity'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
