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
        $page = $this->makePage([false, true]);

        $page->waitVisible('#save', 500);

        $this->assertSame(2, $page->visibilityChecks);
    }

    public function testWaitHiddenWaitsForElementToDisappear(): void
    {
        $page = $this->makePage([true, false]);

        $page->waitHidden('.loading', 500);

        $this->assertSame(2, $page->visibilityChecks);
    }

    public function testWaitVisibleBuildsUsefulDefaultMessage(): void
    {
        $page = $this->makePage([false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Element did not become visible: #missing');

        $page->waitVisible('#missing', 1);
    }

    private function makePage(array $visibility = []): CommonPage
    {
        return new class ('en', '8.1.0', [], [], $visibility) extends CommonPage {
            public int $visibilityChecks = 0;

            private array $visibility;

            public function __construct(string $locale, string $patchVersion, array $globals, array $customs, array $visibility)
            {
                parent::__construct($locale, $patchVersion, $globals, $customs);
                $this->visibility = $visibility;
            }

            public function isVisible($selector, $timeout = 1000)
            {
                ++$this->visibilityChecks;

                if ($this->visibility === []) {
                    return false;
                }

                if (count($this->visibility) === 1) {
                    return $this->visibility[0];
                }

                return array_shift($this->visibility);
            }
        };
    }
}
