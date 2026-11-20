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
}
