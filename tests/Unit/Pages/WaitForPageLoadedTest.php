<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\TimeoutException;
use PrestaFlow\Library\Pages\CommonPage;

final class WaitForPageLoadedTest extends TestCase
{
    public function testWaitForPageLoadedPollsTheBrowserUntilTheDocumentIsReady(): void
    {
        // readyState is "loading" on the first poll, then the DOM is ready.
        $fakePage = $this->fakeDomPage([false, true]);

        $this->makePage($fakePage)->waitForPageLoaded(1000);

        $this->assertCount(2, $fakePage->evaluated, 'waitForPageLoaded() should poll the page until it is ready');
        $this->assertStringContainsString('document.readyState', $fakePage->evaluated[0]);
    }

    public function testWaitForPageLoadedThrowsWhenThePageNeverFinishesLoading(): void
    {
        $fakePage = $this->fakeDomPage([false]);

        $this->expectException(TimeoutException::class);

        $this->makePage($fakePage)->waitForPageLoaded(50);
    }

    public function testUnknownMethodIsNotSilentlySwallowed(): void
    {
        $page = $this->makePage($this->fakeDomPage([true]));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('methodThatDoesNotExist');

        $page->methodThatDoesNotExist();
    }

    public function testKnownChromePageMethodIsStillProxied(): void
    {
        $fakePage = $this->fakeDomPage([true]);

        $result = $this->makePage($fakePage)->waitUntilContainsElement('#main', 10);

        $this->assertSame([['#main', 10]], $fakePage->waited);
        $this->assertSame($fakePage, $result, '__call should hand back what the chrome-php page returned');
    }

    private function makePage(object $fakePage): CommonPage
    {
        return new class ($fakePage) extends CommonPage {
            private object $fakePage;

            public function __construct(object $fakePage)
            {
                $this->fakePage = $fakePage;
            }

            public function getPage()
            {
                return $this->fakePage;
            }
        };
    }

    /**
     * Fake chrome-php Page: evaluate() records the expression and returns the
     * next scripted readiness value (the last one repeats).
     */
    private function fakeDomPage(array $readiness): object
    {
        return new class ($readiness) {
            public array $evaluated = [];
            public array $waited = [];

            public function __construct(private array $readiness)
            {
            }

            public function evaluate($js)
            {
                $this->evaluated[] = $js;
                $value = count($this->readiness) > 1 ? array_shift($this->readiness) : $this->readiness[0];

                return new class ($value) {
                    public function __construct(private $value)
                    {
                    }

                    public function getReturnValue($timeout = null)
                    {
                        return $this->value;
                    }
                };
            }

            public function waitUntilContainsElement($selector, int $timeout = 30000)
            {
                $this->waited[] = [$selector, $timeout];

                return $this;
            }
        };
    }
}
