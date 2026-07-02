<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class BackOfficeOrdersTest extends TestCase
{
    private function make(): object
    {
        $class = 'PrestaFlow\\Library\\Pages\\v9\\BackOffice\\Orders\\Page';
        $globals = [
            'PS_VERSION' => '9.0.0',
            'LOCALE' => 'en',
            'PREFIX_LOCALE' => false,
            'BO' => ['URL' => 'http://localhost/admin/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'DEBUG' => false,
            'VERBOSE' => false,
        ];
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $globals, customs: []);
    }

    public function testDeclaresOrderLookupSelectors(): void
    {
        $selectors = $this->make()->selectors;
        foreach (['filterReferenceInput', 'searchButton', 'listRowReference'] as $key) {
            $this->assertArrayHasKey($key, $selectors, $key);
        }
    }

    public function testHasOrderLookupMethods(): void
    {
        $page = $this->make();
        $this->assertTrue(method_exists($page, 'filterByReference'));
        $this->assertTrue(method_exists($page, 'getOrderReferenceInList'));
    }
}
