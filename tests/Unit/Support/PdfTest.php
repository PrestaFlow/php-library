<?php

namespace PrestaFlow\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\Pdf;

final class PdfTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        $this->fixture = __DIR__ . '/fixtures/facture.pdf';
    }

    public function testFromFileReadsText(): void
    {
        $pdf = Pdf::fromFile($this->fixture);
        $text = $pdf->text();
        $this->assertStringContainsString('Facture', $text);
        $this->assertStringContainsString('TVA', $text);
    }

    public function testFromStringReadsSameText(): void
    {
        $pdf = Pdf::fromString((string) file_get_contents($this->fixture));
        $this->assertStringContainsString('123', $pdf->text());
    }

    public function testFromFileThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/introuvable/');
        Pdf::fromFile('/does/not/exist.pdf');
    }

    public function testFromStringThrowsOnEmpty(): void
    {
        $this->expectException(\RuntimeException::class);
        Pdf::fromString('');
    }

    public function testPagesReturnsAtLeastOne(): void
    {
        $pdf = Pdf::fromFile($this->fixture);
        $pages = $pdf->pages();
        $this->assertGreaterThanOrEqual(1, count($pages));
        $this->assertSame(count($pages), $pdf->pageCount());
    }

    public function testContainsIsCaseInsensitiveByDefault(): void
    {
        $pdf = Pdf::fromFile($this->fixture);
        $this->assertTrue($pdf->contains('facture'));   // fixture a "Facture"
        $this->assertTrue($pdf->contains('FACTURE'));
        $this->assertFalse($pdf->contains('facture', caseSensitive: true));
    }

    public function testContainsEmptyNeedleIsTrue(): void
    {
        $this->assertTrue(Pdf::fromFile($this->fixture)->contains(''));
    }

    public function testMatchesReturnsCapturedGroup(): void
    {
        $pdf = Pdf::fromFile($this->fixture);
        // « Facture #123 » ou « Facture 123 » selon le rendu
        $ids = $pdf->matches('/Facture\s*#?\s*(\d+)/', 1);
        $this->assertNotEmpty($ids);
        $this->assertSame('123', $ids[0]);
    }

    public function testMatchesReturnsEmptyOnNoMatch(): void
    {
        $pdf = Pdf::fromFile($this->fixture);
        $this->assertSame([], $pdf->matches('/absolument-absent-du-doc/'));
    }

    public function testMatchesThrowsOnInvalidRegex(): void
    {
        $this->expectException(\RuntimeException::class);
        Pdf::fromFile($this->fixture)->matches('not-a-regex');
    }
}
