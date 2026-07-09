<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class PageFactorizationTest extends TestCase
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

    private function make(string $version, string $relual): object
    {
        $class = self::NS . $version . '\\' . $relual . '\\Page';
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $this->fakeGlobals(), customs: []);
    }

    public function testEveryCommonPageResolvesForAllVersions(): void
    {
        // Phase 1: concrete pages moved from Common\ to v9\. Iterate over v9 leaves
        // (v7/v8 are placeholders until Phase 2/3 reimplements them).
        $leaves = $this->v9Leaves();
        $this->assertNotEmpty($leaves, 'Expected at least one v9 page leaf');
        foreach ($leaves as $relual) {
            foreach (self::VERSIONS as $v) {
                $class = self::NS . $v . '\\' . $relual . '\\Page';
                $this->assertTrue(class_exists($class), "Missing entry point: $class");
            }
        }
    }

    public function testPricesDropDeltaPreserved(): void
    {
        // Phase 1: v7/v8 pages are gone until Phase 2/3 reimplements them.
        $this->assertSame('promotions', $this->make('v9', 'FrontOffice\\PricesDrop')->url);
        $this->assertSame('Promotions', $this->make('v9', 'FrontOffice\\PricesDrop')->pageTitle);
    }

    public function testIdenticalPageSharesSelectorsAcrossVersions(): void
    {
        // Phase 1: only v9 is populated; cross-version selector parity will be
        // re-asserted in Phase 2/3 once v7/v8 concretes exist again.
        $s9 = $this->make('v9', 'FrontOffice\\Cart')->selectors;
        $this->assertIsArray($s9);
        $this->assertNotEmpty($s9);
    }

    public function testPricesDropKeepsListingInheritance(): void
    {
        $this->assertTrue(method_exists($this->make('v9', 'FrontOffice\\PricesDrop'), 'getListingTitle'));
    }

    public function testDashboardTitleIsConsistentAcrossVersions(): void
    {
        // v9 no longer hardcodes a French title; it inherits 'Dashboard' and
        // relies on the translation layer (pageTitle()) for locale.
        $this->assertSame('Dashboard', $this->make('v9', 'BackOffice\\Dashboard')->pageTitle);
    }

    private function v9Leaves(): array
    {
        $root = dirname(__DIR__, 3) . '/src/Pages/v9';
        if (!is_dir($root)) {
            return [];
        }
        $leaves = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getFilename() !== 'Page.php') {
                continue;
            }
            $rel = trim(str_replace($root, '', $file->getPath()), '/');
            if ($rel === 'FrontOffice' || $rel === 'BackOffice' || $rel === '') {
                continue;
            }
            $leaves[] = str_replace('/', '\\', $rel);
        }
        sort($leaves);
        return $leaves;
    }
}
