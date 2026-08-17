<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
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
        $page->expects($this->exactly(2))
            ->method('getTextContent')
            ->with('body', 1, true, 100)
            ->willReturnOnConsecutiveCalls('Loading', 'Order confirmed');

        $page->waitForText('Order confirmed', 500);
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
