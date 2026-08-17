<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\TimeoutException;
use PrestaFlow\Library\Pages\CommonPage;

final class CommonPageWaitTest extends TestCase
{
    public function testWaitUntilStopsWhenConditionIsMet(): void
    {
        $page = $this->makePage();
        $attempts = 0;

        $page->waitUntil(function () use (&$attempts) {
            ++$attempts;

            return $attempts === 2;
        }, 500);

        $this->assertSame(2, $attempts);
    }

    public function testWaitUntilUsesProvidedFailureMessage(): void
    {
        $page = $this->makePage();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected condition did not happen.');

        $page->waitUntil(fn () => false, 1, 'Expected condition did not happen.');
    }

    public function testWaitUntilThrowsTimeoutException(): void
    {
        $page = $this->makePage();

        $this->expectException(TimeoutException::class);

        $page->waitUntil(fn () => false, 1);
    }

    public function testWaitUntilRespectsDeadline(): void
    {
        $page = $this->makePage();

        $start = microtime(true);
        try {
            $page->waitUntil(fn () => false, 200);
        } catch (TimeoutException $e) {
            // expected
        }
        $elapsedMs = (microtime(true) - $start) * 1000;

        // Should be close to 200ms, well below 400ms (i.e. no 100ms overshoot per iteration).
        $this->assertLessThan(350, $elapsedMs);
        $this->assertGreaterThanOrEqual(200, $elapsedMs);
    }

    public function testWaitVisibleWaitsForVisibleElement(): void
    {
        $page = $this->mockPage('isVisible');
        $page->expects($this->exactly(2))
            ->method('isVisible')
            ->with('#save', 100)
            ->willReturnOnConsecutiveCalls(false, true);

        $page->waitVisible('#save', 500);
    }

    public function testWaitHiddenWaitsForElementToDisappear(): void
    {
        $page = $this->mockPage('isVisible');
        $page->expects($this->exactly(2))
            ->method('isVisible')
            ->with('.loading', 100)
            ->willReturnOnConsecutiveCalls(true, false);

        $page->waitHidden('.loading', 500);
    }

    public function testWaitVisibleBuildsUsefulDefaultMessage(): void
    {
        $page = $this->mockPage('isVisible');
        $page->method('isVisible')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Element did not become visible: #missing');

        $page->waitVisible('#missing', 1);
    }

    public function testWaitForTextWaitsUntilTextAppears(): void
    {
        $page = $this->mockPage('getTextContent');
        $calls = [];
        $page->expects($this->exactly(2))
            ->method('getTextContent')
            ->willReturnCallback(function (...$args) use (&$calls) {
                $calls[] = $args;
                return count($calls) === 1 ? 'Loading' : 'Order confirmed';
            });

        $page->waitForText('Order confirmed', 500);

        // First poll waits for the selector; subsequent polls skip it.
        $this->assertSame(['body', 1, true, 100], $calls[0]);
        $this->assertSame(['body', 1, false, 100], $calls[1]);
    }

    public function testWaitForTextSupportsScopedSelector(): void
    {
        $page = $this->mockPage('getTextContent');
        $page->expects($this->once())
            ->method('getTextContent')
            ->with('.notification', 1, true, 100)
            ->willReturn('Saved');

        $page->waitForText('Saved', 500, '.notification');
    }

    public function testWaitForTextUsesProvidedFailureMessage(): void
    {
        $page = $this->mockPage('getTextContent');
        $page->method('getTextContent')->willReturn('Still loading');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Confirmation message did not appear.');

        $page->waitForText('Done', 1, 'body', 'Confirmation message did not appear.');
    }

    private function makePage(): CommonPage
    {
        return new CommonPage('en', '8.1.0', []);
    }

    private function mockPage(string $method): CommonPage
    {
        return $this->getMockBuilder(CommonPage::class)
            ->setConstructorArgs(['en', '8.1.0', []])
            ->onlyMethods([$method])
            ->getMock();
    }
}
