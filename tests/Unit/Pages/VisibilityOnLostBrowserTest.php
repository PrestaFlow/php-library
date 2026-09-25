<?php

namespace PrestaFlow\Tests\Unit\Pages;

use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Exception\TargetDestroyed;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\FrontOfficePage;

/**
 * isVisible() answers "is this element on the page?", not "is the browser
 * still there?".
 *
 * When the Chrome a run drives is closed under it (another PrestaFlow process
 * releasing a shared browser, a crash), chrome-php throws TargetDestroyed
 * ("The session is destroyed."). elementIsVisible() used to catch every
 * Exception and return false, so the lost browser read as a missing element:
 * a suite asserting a block on the home page failed with "false must be the
 * same as true", which looks like a real regression of the shop, instead of
 * reporting the browser that went away.
 */
final class VisibilityOnLostBrowserTest extends TestCase
{
    private function pageThrowing(\Throwable $error): FrontOfficePage
    {
        $fakePage = new class($error) {
            public function __construct(private \Throwable $error) {}
            public function waitUntilContainsElement($selector, $timeout = 30000)
            {
                throw $this->error;
            }
        };

        return new class($fakePage) extends FrontOfficePage {
            public function __construct(private $fakePage) {}
            public function getPage() { return $this->fakePage; }
        };
    }

    public function testAMissingElementIsNotVisible(): void
    {
        $page = $this->pageThrowing(new OperationTimedOut('Operation timed out after 1000ms.'));

        $this->assertFalse($page->isVisible('#psflowdemo-block', 1000));
    }

    public function testALostBrowserIsReportedNotReadAsAMissingElement(): void
    {
        $page = $this->pageThrowing(new TargetDestroyed('The session is destroyed.'));

        $this->expectException(TargetDestroyed::class);
        $this->expectExceptionMessage('The session is destroyed.');

        $page->isVisible('#psflowdemo-block', 1000);
    }
}
