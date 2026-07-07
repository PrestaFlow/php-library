<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class EditProductTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\EditProduct';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\EditProduct';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresEditParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\EditProduct');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['productName', 'initialPrice', 'newPrice', 'quantity'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
