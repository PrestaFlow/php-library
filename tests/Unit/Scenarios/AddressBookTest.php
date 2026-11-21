<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Scenarios\Scenario;

final class AddressBookTest extends TestCase
{
    public function test_defaults_are_discovered_or_unique(): void
    {
        $params = (new \ReflectionClass(\PrestaFlow\Library\Scenarios\AddressBook::class))->getDefaultProperties()['params'];

        // The project's account, a per-run alias, the shop's own country.
        foreach (['locale', 'customerEmail', 'customerPassword', 'addressAlias', 'addressPostcode', 'addressCountry', 'addressState'] as $key) {
            $this->assertArrayHasKey($key, $params);
            $this->assertNull($params[$key], $key);
        }

        // The edit must change something the list shows.
        $this->assertNotSame($params['addressCity'], $params['addressNewCity']);
    }

    public function test_the_generated_alias_fits_the_alias_field(): void
    {
        // PrestaShop limits an address alias to 32 characters.
        $this->assertLessThanOrEqual(32, strlen('PF test ' . Scenario::pfUniqueSuffix()));
    }

    public function test_the_address_pages_resolve_through_the_shop(): void
    {
        $addresses = (new \ReflectionClass(\PrestaFlow\Library\Pages\v9\FrontOffice\Addresses\Page::class))->getDefaultProperties();
        $address = (new \ReflectionClass(\PrestaFlow\Library\Pages\v9\FrontOffice\Address\Page::class))->getDefaultProperties();

        $this->assertSame('addresses', $addresses['shopPage']);
        $this->assertSame('address', $address['shopPage']);
    }

    public function test_older_versions_inherit_the_v9_pages(): void
    {
        foreach (['v7', 'v8'] as $version) {
            $this->assertTrue(is_subclass_of("PrestaFlow\\Library\\Pages\\$version\\FrontOffice\\Addresses\\Page", \PrestaFlow\Library\Pages\v9\FrontOffice\Addresses\Page::class));
            $this->assertTrue(is_subclass_of("PrestaFlow\\Library\\Pages\\$version\\FrontOffice\\Address\\Page", \PrestaFlow\Library\Pages\v9\FrontOffice\Address\Page::class));
        }
    }
}
