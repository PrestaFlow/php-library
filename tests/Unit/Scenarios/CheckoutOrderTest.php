<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class CheckoutOrderTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\CheckoutOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\CheckoutOrder';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresCheckoutParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\CheckoutOrder');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['customerEmail', 'customerPassword', 'productUrl', 'cartQuantity'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
