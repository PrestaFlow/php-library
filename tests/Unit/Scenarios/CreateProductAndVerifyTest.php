<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class CreateProductAndVerifyTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\CreateProductAndVerify';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\CreateProductAndVerify';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresProductParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\CreateProductAndVerify');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        $this->assertArrayHasKey('productName', $params);
        $this->assertArrayHasKey('productPrice', $params);
        $this->assertArrayHasKey('productQuantity', $params);
    }
}
