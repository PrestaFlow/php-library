<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\Suites\Visual\BackOffice;

final class BackOfficeSuiteTest extends TestCase
{
    public function test_definition(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends BackOffice {
            protected function importVisualPage(): void {}
        };
        $cps = $suite->checkpoints();

        $this->assertSame('bo', $suite->area());
        $this->assertSame(['desktop'], $suite->devices());
        $this->assertSame(['en'], $suite->locales());
        $this->assertSame(['login', 'dashboard', 'products', 'orders', 'customers', 'modules'], array_column($cps, 'name'));
        $this->assertFalse($cps[0]['auth']);
        $this->assertSame('#subtab-AdminDashboard, #tab-AdminDashboard', $cps[1]['menu']);
        $this->assertSame('#subtab-AdminModulesSf', $cps[5]['menu']);
        $this->assertContains('.onboarding-popup', $cps[1]['hide']);
        $this->assertSame('login', $suite->orderedCheckpoints()[0]['name']);
    }
}
