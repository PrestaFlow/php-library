<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class BackOfficeNavigationTest extends TestCase
{
    private const NS = 'PrestaFlow\\Library\\Pages\\';
    // Phase 1 migration: concrete pages live under v9 only.
    // v7 and v8 are placeholders until Phase 2/3 reimplements them.
    private const VERSIONS = ['v9'];

    private function fakeGlobals(): array
    {
        return [
            'PS_VERSION' => '9.0.0',
            'LOCALE' => 'en',
            'PREFIX_LOCALE' => false,
            'BO' => ['URL' => 'http://localhost/admin/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'DEBUG' => false,
            'VERBOSE' => false,
        ];
    }

    private function make(string $version, string $name): object
    {
        $class = self::NS . $version . '\\BackOffice\\' . $name . '\\Page';
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $this->fakeGlobals(), customs: []);
    }

    public function testPagesResolveForAllVersions(): void
    {
        foreach (self::VERSIONS as $v) {
            foreach (['Products', 'Orders'] as $name) {
                $this->assertTrue(class_exists(self::NS . $v . '\\BackOffice\\' . $name . '\\Page'), "$v $name");
            }
        }
    }

    public function testProductsDeclaresMenu(): void
    {
        $p = $this->make('v9', 'Products');
        $this->assertSame('#subtab-AdminProducts', $p->menuSelector);
        $this->assertSame('#subtab-AdminCatalog', $p->parentMenuSelector);
        $this->assertNotSame('', $p->pageTitle);
        $this->assertTrue(method_exists($p, 'goTo'));
    }

    public function testOrdersDeclaresMenu(): void
    {
        $o = $this->make('v9', 'Orders');
        $this->assertSame('#subtab-AdminOrders', $o->menuSelector);
        $this->assertSame('#subtab-AdminParentOrders', $o->parentMenuSelector);
        $this->assertNotSame('', $o->pageTitle);
        $this->assertTrue(method_exists($o, 'goTo'));
    }

    public function testHelperExists(): void
    {
        $this->assertTrue(method_exists('PrestaFlow\\Library\\Pages\\BackOfficePage', 'goToSubMenu'));
    }

    public function testBatchPagesResolveForAllVersions(): void
    {
        foreach (self::VERSIONS as $v) {
            foreach (['Categories', 'Customers', 'Modules', 'Carriers'] as $name) {
                $this->assertTrue(class_exists(self::NS . $v . '\\BackOffice\\' . $name . '\\Page'), "$v $name");
            }
        }
    }

    public function testBatchPagesDeclareMenu(): void
    {
        $expected = [
            'Categories' => ['#subtab-AdminCategories', '#subtab-AdminCatalog'],
            'Customers'  => ['#subtab-AdminCustomers', '#subtab-AdminParentCustomer'],
            'Modules'    => ['#subtab-AdminModulesSf', '#subtab-AdminParentModulesSf'],
            'Carriers'   => ['#subtab-AdminCarriers', '#subtab-AdminParentShipping'],
        ];
        foreach ($expected as $name => [$menu, $parent]) {
            $page = $this->make('v9', $name);
            $this->assertSame($menu, $page->menuSelector, $name);
            $this->assertSame($parent, $page->parentMenuSelector, $name);
            $this->assertNotSame('', $page->pageTitle, $name);
            $this->assertTrue(method_exists($page, 'goTo'), $name);
        }
    }

    public function testCustomersListCoexistsWithCustomerEdit(): void
    {
        $this->assertTrue(class_exists(self::NS . 'v9\\BackOffice\\Customers\\Page'));
        $this->assertTrue(class_exists(self::NS . 'v9\\BackOffice\\Customer\\Page'));
    }
}
