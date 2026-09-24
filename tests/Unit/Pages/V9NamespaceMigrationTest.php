<?php

declare(strict_types=1);

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1 migration: concrete page implementations moved from
 * PrestaFlow\Library\Pages\Common\{Area}\{Name}\Page down to
 * PrestaFlow\Library\Pages\v9\{Area}\{Name}\Page.
 *
 * These assertions only use class_exists() — no Page or TestsSuite is
 * instantiated, so the browser bootstrap is never triggered.
 */
final class V9NamespaceMigrationTest extends TestCase
{
    #[DataProvider('boPages')]
    public function testV9BackOfficePageExists(string $name): void
    {
        $fqcn = 'PrestaFlow\\Library\\Pages\\v9\\BackOffice\\' . $name . '\\Page';
        $this->assertTrue(
            class_exists($fqcn),
            "Expected v9 BackOffice concrete page to exist: {$fqcn}"
        );
    }

    #[DataProvider('boPages')]
    public function testCommonBackOfficePageIsGone(string $name): void
    {
        $fqcn = 'PrestaFlow\\Library\\Pages\\Common\\BackOffice\\' . $name . '\\Page';
        $this->assertFalse(
            class_exists($fqcn),
            "Expected Common BackOffice concrete page to be removed: {$fqcn}"
        );
    }

    #[DataProvider('foPages')]
    public function testV9FrontOfficePageExists(string $name): void
    {
        $fqcn = 'PrestaFlow\\Library\\Pages\\v9\\FrontOffice\\' . $name . '\\Page';
        $this->assertTrue(
            class_exists($fqcn),
            "Expected v9 FrontOffice concrete page to exist: {$fqcn}"
        );
    }

    #[DataProvider('foPages')]
    public function testCommonFrontOfficePageIsGone(string $name): void
    {
        $fqcn = 'PrestaFlow\\Library\\Pages\\Common\\FrontOffice\\' . $name . '\\Page';
        $this->assertFalse(
            class_exists($fqcn),
            "Expected Common FrontOffice concrete page to be removed: {$fqcn}"
        );
    }

    public function testCommonBaseClassesStillExist(): void
    {
        $this->assertTrue(class_exists('PrestaFlow\\Library\\Pages\\CommonPage'));
        $this->assertTrue(class_exists('PrestaFlow\\Library\\Pages\\BackOfficePage'));
        $this->assertTrue(class_exists('PrestaFlow\\Library\\Pages\\FrontOfficePage'));
        $this->assertTrue(class_exists('PrestaFlow\\Library\\Pages\\Common\\BackOffice\\Page'));
        $this->assertTrue(class_exists('PrestaFlow\\Library\\Pages\\Common\\FrontOffice\\Page'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function boPages(): array
    {
        return [
            'Carriers' => ['Carriers'],
            'Categories' => ['Categories'],
            'Customer' => ['Customer'],
            'Customers' => ['Customers'],
            'Dashboard' => ['Dashboard'],
            'Login' => ['Login'],
            'Modules' => ['Modules'],
            'OrderView' => ['OrderView'],
            'Orders' => ['Orders'],
            'Products' => ['Products'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function foPages(): array
    {
        return [
            'Account' => ['Account'],
            'Address' => ['Address'],
            'Addresses' => ['Addresses'],
            'BestSellers' => ['BestSellers'],
            'Brand' => ['Brand'],
            'Brands' => ['Brands'],
            'CMS' => ['CMS'],
            'Cart' => ['Cart'],
            'Category' => ['Category'],
            'Checkout' => ['Checkout'],
            'Contact' => ['Contact'],
            'Content' => ['Content'],
            'CreditSlip' => ['CreditSlip'],
            'GuestTracking' => ['GuestTracking'],
            'Home' => ['Home'],
            'Identity' => ['Identity'],
            'Information' => ['Information'],
            'Listing' => ['Listing'],
            'Login' => ['Login'],
            'Manufacturer' => ['Manufacturer'],
            'Manufacturers' => ['Manufacturers'],
            'NewProducts' => ['NewProducts'],
            'OrderConfirmation' => ['OrderConfirmation'],
            'OrderHistory' => ['OrderHistory'],
            'PricesDrop' => ['PricesDrop'],
            'Product' => ['Product'],
            'Registration' => ['Registration'],
            'Rgpd' => ['Rgpd'],
            'Sitemap' => ['Sitemap'],
            'Stores' => ['Stores'],
        ];
    }
}
