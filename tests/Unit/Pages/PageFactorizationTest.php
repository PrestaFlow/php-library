<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class PageFactorizationTest extends TestCase
{
    private const NS = 'PrestaFlow\\Library\\Pages\\';
    private const VERSIONS = ['v7', 'v8', 'v9'];

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
        foreach ($this->commonLeaves() as $relual) {
            foreach (self::VERSIONS as $v) {
                $class = self::NS . $v . '\\' . $relual . '\\Page';
                $this->assertTrue(class_exists($class), "Missing entry point: $class");
            }
        }
    }

    public function testPricesDropDeltaPreserved(): void
    {
        $this->assertSame('promotions', $this->make('v9', 'FrontOffice\\PricesDrop')->url);
        $this->assertSame('Promotions', $this->make('v9', 'FrontOffice\\PricesDrop')->pageTitle);
        $this->assertSame('prices-drop', $this->make('v7', 'FrontOffice\\PricesDrop')->url);
        $this->assertSame('prices-drop', $this->make('v8', 'FrontOffice\\PricesDrop')->url);
    }

    public function testIdenticalPageSharesSelectorsAcrossVersions(): void
    {
        $s7 = $this->make('v7', 'FrontOffice\\Cart')->selectors;
        $s8 = $this->make('v8', 'FrontOffice\\Cart')->selectors;
        $s9 = $this->make('v9', 'FrontOffice\\Cart')->selectors;
        $this->assertSame($s7, $s8);
        $this->assertSame($s7, $s9);
    }

    public function testPricesDropKeepsListingInheritance(): void
    {
        $this->assertTrue(method_exists($this->make('v9', 'FrontOffice\\PricesDrop'), 'getListingTitle'));
        $this->assertTrue(method_exists($this->make('v7', 'FrontOffice\\PricesDrop'), 'getListingTitle'));
    }

    public function testDashboardDeltaPreserved(): void
    {
        $this->assertSame('Tableau de bord', $this->make('v9', 'BackOffice\\Dashboard')->pageTitle);
        $this->assertSame('Dashboard', $this->make('v7', 'BackOffice\\Dashboard')->pageTitle);
    }

    private function commonLeaves(): array
    {
        $root = dirname(__DIR__, 3) . '/src/Pages/Common';
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
