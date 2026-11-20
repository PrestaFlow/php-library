<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * skipWithReason() turns a missing shop capability (no offline payment method, no
 * wishlist module) into a skip with its reason, not a failure.
 */
final class SkipWithReasonTest extends TestCase
{
    private function suite(): TestsSuite
    {
        return new class (loadGlobals: false, getBrowser: false) extends TestsSuite {};
    }

    public function test_skip_test_throws_with_its_reason(): void
    {
        try {
            $this->suite()->skipWithReason('No offline payment method');
            $this->fail('skipWithReason() must throw');
        } catch (SkipTestException $e) {
            $this->assertSame('No offline payment method', $e->getMessage());
            $this->assertFalse($e->skipRemaining);
        }
    }

    public function test_skip_test_can_skip_the_rest_of_the_suite(): void
    {
        try {
            $this->suite()->skipWithReason('No offline payment method', skipRemaining: true);
            $this->fail('skipWithReason() must throw');
        } catch (SkipTestException $e) {
            $this->assertTrue($e->skipRemaining);
        }
    }

    // The run-loop behaviour (states, counters, skipRemaining) is covered by
    // tests/Browser/SkipWithReasonBrowserTest: run() needs a browser.

    /** No silent fallback on PrestaShop's demo accounts. */
    public function test_globals_carry_no_demo_account(): void
    {
        $keys = ['PRESTAFLOW_BO_EMAIL', 'PRESTAFLOW_BO_PASSWD', 'PRESTAFLOW_FO_EMAIL', 'PRESTAFLOW_FO_PASSWD'];
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = [$_ENV[$key] ?? null, getenv($key)];
            unset($_ENV[$key]);
            putenv($key);
        }

        try {
            $globals = (new class (loadGlobals: true, getBrowser: false) extends TestsSuite {})->getGlobals();
        } finally {
            foreach ($saved as $key => [$env, $process]) {
                if ($env !== null) {
                    $_ENV[$key] = $env;
                }
                if ($process !== false) {
                    putenv($key . '=' . $process);
                }
            }
        }

        $this->assertSame('', $globals['BO']['EMAIL']);
        $this->assertSame('', $globals['BO']['PASSWD']);
        $this->assertSame('', $globals['FO']['EMAIL']);
        $this->assertSame('', $globals['FO']['PASSWD']);
    }
}
