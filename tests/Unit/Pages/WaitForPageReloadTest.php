<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;

final class WaitForPageReloadTest extends TestCase
{
    public function testUsesRealBoundedReloadWait(): void
    {
        $ref = new \ReflectionMethod(CommonPage::class, 'waitForPageReload');
        $lines = file($ref->getFileName());
        $body = implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        ));

        $this->assertStringNotContainsString(
            'some js that will reload the page',
            $body,
            'waitForPageReload() must not evaluate the placeholder JS string'
        );
        $this->assertStringContainsString(
            'waitForReload',
            $body,
            'waitForPageReload() should use the real chrome-php reload wait'
        );
    }
}
