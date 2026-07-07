<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

final class AfterLifecycleTest extends TestCase
{
    public function testAfterDoesNotCloseTheBrowser(): void
    {
        $ref = new \ReflectionMethod(TestsSuite::class, 'after');
        $lines = file($ref->getFileName());
        $body = implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        ));

        $this->assertStringNotContainsString(
            '->close()',
            $body,
            'after() must not close the keepAlive browser (that breaks series runs)'
        );
    }
}
