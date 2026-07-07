<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class GuestCheckoutTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\GuestCheckout';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\GuestCheckout';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresGuestParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\GuestCheckout');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach (['guestEmail', 'firstName', 'lastName', 'addressStreet', 'addressCity', 'addressPostcode', 'addressCountry', 'addressPhone', 'productUrl'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
