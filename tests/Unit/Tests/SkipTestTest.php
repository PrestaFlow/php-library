<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * skipTest() turns a missing shop capability (no offline payment method, no
 * wishlist module) into a skip with its reason, not a failure.
 */
final class SkipTestTest extends TestCase
{
    private function suite(): TestsSuite
    {
        return new class (loadGlobals: false, getBrowser: false) extends TestsSuite {};
    }

    public function test_skip_test_throws_with_its_reason(): void
    {
        try {
            $this->suite()->skipTest('No offline payment method');
            $this->fail('skipTest() must throw');
        } catch (SkipTestException $e) {
            $this->assertSame('No offline payment method', $e->getMessage());
            $this->assertFalse($e->skipRemaining);
        }
    }

    public function test_skip_test_can_skip_the_rest_of_the_suite(): void
    {
        try {
            $this->suite()->skipTest('No offline payment method', skipRemaining: true);
            $this->fail('skipTest() must throw');
        } catch (SkipTestException $e) {
            $this->assertTrue($e->skipRemaining);
        }
    }

    public function test_the_run_loop_catches_it_before_the_generic_failure(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(TestsSuite::class))->getFileName());

        $skip = strpos($source, 'catch (SkipTestException $e)');
        $generic = strpos($source, 'catch (OperationTimedOut | UnexpectedValueException');

        $this->assertNotFalse($skip);
        $this->assertNotFalse($generic);
        $this->assertLessThan($generic, $skip);
    }

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
