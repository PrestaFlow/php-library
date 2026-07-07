<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;

final class CommonPageIoTest extends TestCase
{
    private function body(string $method): string
    {
        $ref = new \ReflectionMethod(CommonPage::class, $method);
        $lines = file($ref->getFileName());
        return implode('', array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
    }

    public function testGetInputValueReadsValuePropertyViaJs(): void
    {
        $b = $this->body('getInputValue');
        $this->assertStringContainsString('evaluate', $b);
        $this->assertStringContainsString('.value', $b);
        $this->assertStringNotContainsString("getAttribute('value')", $b);
    }

    public function testSelectOptionUsesQuerySelectorAndChange(): void
    {
        $b = $this->body('selectOption');
        $this->assertStringContainsString('querySelector', $b);
        $this->assertStringContainsString('change', $b);
        $this->assertStringNotContainsString('file_put_contents', $b);
    }

    public function testHasSetValueByJs(): void
    {
        $this->assertTrue(method_exists(CommonPage::class, 'setValueByJs'));
    }
}
