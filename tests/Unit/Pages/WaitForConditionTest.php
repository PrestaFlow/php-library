<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;

/**
 * Test double: CommonPage::getPage() normally returns the shared headless-Chrome
 * page through TestsSuite. Here it returns a fake that replays a scripted list of
 * evaluate() results, so the polling logic can be tested without a browser.
 */
final class FakeConditionPage extends CommonPage
{
    /** @var array<int, mixed> results replayed by successive evaluate() calls */
    public array $results = [];
    public int $calls = 0;
    /** @var bool when true, the first evaluate() call throws */
    public bool $throwOnFirstCall = false;
    /** @var bool when true, every evaluate() call throws */
    public bool $throwAlways = false;
    /** @var int when >= 0, every evaluate() call from this index onward throws */
    public int $throwFromCall = -1;
    /** @var array<int, string> the raw JS passed to each evaluate() call, in order */
    public array $receivedJs = [];

    // Deliberately bypasses the parent constructor: no locale, no globals, no browser.
    public function __construct()
    {
    }

    public function getPage()
    {
        return new class ($this) {
            public function __construct(private FakeConditionPage $owner)
            {
            }

            public function evaluate(string $js)
            {
                $index = $this->owner->calls;
                $this->owner->calls++;
                $this->owner->receivedJs[] = $js;

                $shouldThrow = $this->owner->throwAlways
                    || ($this->owner->throwOnFirstCall && $index === 0)
                    || ($this->owner->throwFromCall >= 0 && $index >= $this->owner->throwFromCall);

                if ($shouldThrow) {
                    throw new \RuntimeException('page is navigating');
                }

                $value = $this->owner->results[$index] ?? false;

                return new class ($value) {
                    public function __construct(private mixed $value)
                    {
                    }

                    public function getReturnValue(?int $timeout = null): mixed
                    {
                        return $this->value;
                    }
                };
            }
        };
    }
}

final class WaitForConditionTest extends TestCase
{
    /**
     * The deprecated alias is a public method callers may still be on, so it is
     * exercised rather than assumed. An alias nobody runs is how a rename
     * quietly breaks the people it was supposed to spare.
     */
    public function testDeprecatedAliasStillDelegates(): void
    {
        $page = new FakeConditionPage();
        $page->results = [false, true];

        $this->assertTrue($page->waitForCondition('window.ready === true', 2000, 20));
        $this->assertSame(2, $page->calls);
    }

    public function testDeprecatedAliasForwardsTimeoutAndInterval(): void
    {
        $page = new FakeConditionPage();
        $page->results = [];

        $started = microtime(true);
        $this->assertFalse($page->waitForCondition('window.ready === true', 150, 20));
        $elapsed = (microtime(true) - $started) * 1000;

        $this->assertLessThan(1000, $elapsed, 'the alias must pass the caller timeout through, not the default');
    }

    public function testReturnsTrueAndStopsPollingOnFirstTruthyResult(): void
    {
        $page = new FakeConditionPage();
        $page->results = [false, false, true, true];

        $this->assertTrue($page->waitForJsCondition('window.ready === true', 2000, 20));
        $this->assertSame(3, $page->calls, 'polling must stop as soon as the condition is met');
    }

    public function testReturnsFalseWhenTimeoutExpires(): void
    {
        $page = new FakeConditionPage();
        $page->results = [];

        $this->assertFalse($page->waitForJsCondition('window.ready === true', 200, 20));
        $this->assertGreaterThan(1, $page->calls, 'the helper must poll more than once before giving up');
    }

    public function testEvaluateExceptionCountsAsNotYetTrue(): void
    {
        $page = new FakeConditionPage();
        $page->throwOnFirstCall = true;
        $page->results = [1 => true];

        $this->assertTrue($page->waitForJsCondition('window.ready === true', 2000, 20));
    }

    public function testExpressionIsSplicedIntoTruthyGuardedWrapper(): void
    {
        $page = new FakeConditionPage();
        $page->results = [true];

        $page->waitForJsCondition('window.ready === true', 2000, 20);

        $this->assertCount(1, $page->receivedJs);
        $js = $page->receivedJs[0];
        $this->assertStringContainsString('!!(', $js, 'the expression must be coerced to a boolean');
        $this->assertStringContainsString('window.ready === true', $js, 'the caller expression must appear inside the wrapper');
        $this->assertStringContainsString('try{', $js, 'a runtime error in the expression must be swallowed by the wrapper');
        $this->assertStringContainsString('catch(e)', $js);
    }

    public function testTruthyButNotStrictlyTrueDoesNotSatisfyCondition(): void
    {
        $page = new FakeConditionPage();
        $page->results = [1, 'yes', []];

        $this->assertFalse($page->waitForJsCondition('window.ready', 60, 20));
    }

    public function testRethrowsWhenEveryPollFails(): void
    {
        $page = new FakeConditionPage();
        $page->throwAlways = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('page is navigating');

        $page->waitForJsCondition('x > 0 // malformed', 100, 20);
    }

    public function testTransientErrorAfterASuccessfulPollStillReturnsFalseAtTimeout(): void
    {
        $page = new FakeConditionPage();
        $page->results = [false];
        $page->throwFromCall = 1;

        $this->assertFalse($page->waitForJsCondition('window.ready === true', 150, 20));
        $this->assertGreaterThan(1, $page->calls, 'must have polled at least once successfully and then hit errors');
    }
}
