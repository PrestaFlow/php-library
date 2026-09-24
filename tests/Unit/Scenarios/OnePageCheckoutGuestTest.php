<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class OnePageCheckoutGuestTest extends TestCase
{
    public function testScenarioExistsAndExtendsScenario(): void
    {
        $class = 'PrestaFlow\\Library\\Scenarios\\OnePageCheckoutGuest';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
    }

    public function testSuiteExistsAndExtendsTestsSuite(): void
    {
        $class = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\OnePageCheckoutGuest';
        $this->assertTrue(class_exists($class));
        $this->assertTrue(is_subclass_of($class, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    public function testScenarioDeclaresGuestParams(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\OnePageCheckoutGuest');
        $params = $ref->getDefaultProperties()['params'] ?? [];
        foreach ([
            'locale', 'guestEmail', 'firstName', 'lastName', 'addressStreet',
            'addressCity', 'addressPostcode', 'addressCountry', 'addressPhone',
            'productUrl', 'cartQuantity',
        ] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
        }
    }
}
