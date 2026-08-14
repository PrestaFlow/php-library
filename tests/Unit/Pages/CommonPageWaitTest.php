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

    public function testWaitForTextWaitsUntilTextAppears(): void
    {
        $page = $this->makeTextPage(['Loading', 'Order confirmed']);

        $page->waitForText('Order confirmed', 500);

        $this->assertSame(2, $page->textChecks);
        $this->assertSame('body', $page->lastSelector);
    }

    public function testWaitForTextSupportsScopedSelector(): void
    {
        $page = $this->makeTextPage(['Saved']);

        $page->waitForText('Saved', 500, '.notification');

        $this->assertSame('.notification', $page->lastSelector);
    }

    public function testWaitForTextUsesProvidedFailureMessage(): void
    {
        $page = $this->makeTextPage(['Still loading']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Confirmation message did not appear.');

        $page->waitForText('Done', 1, 'body', 'Confirmation message did not appear.');
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

    private function makeTextPage(array $contents): CommonPage
    {
        return new class ('en', '8.1.0', [], [], $contents) extends CommonPage {
            public int $textChecks = 0;

            public string $lastSelector = '';

            private array $contents;

            public function __construct(string $locale, string $patchVersion, array $globals, array $customs, array $contents)
            {
                parent::__construct($locale, $patchVersion, $globals, $customs);
                $this->contents = $contents;
            }

            public function getTextContent($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
            {
                ++$this->textChecks;
                $this->lastSelector = $selector;

                if ($this->contents === []) {
                    return false;
                }

                if (count($this->contents) === 1) {
                    return $this->contents[0];
                }

                return array_shift($this->contents);
            }
        };
    }
}
