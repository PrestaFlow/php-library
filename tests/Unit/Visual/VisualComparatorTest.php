<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Visual\VisualComparator;

final class VisualComparatorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pfvis_' . getmypid();
        @mkdir($this->dir, 0777, true);
    }

    private function png(string $name, callable $draw, int $w = 60, int $h = 60): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $draw($img);
        $path = $this->dir . '/' . $name;
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }

    public function testIdenticalImagesScoreHigh(): void
    {
        $draw = fn ($img) => imagefilledrectangle($img, 10, 10, 40, 40, imagecolorallocate($img, 0, 0, 0));
        $this->assertGreaterThanOrEqual(0.99, (new VisualComparator())->compare($this->png('a.png', $draw), $this->png('b.png', $draw)));
    }

    public function testDifferentImagesScoreLower(): void
    {
        $a = $this->png('c.png', fn ($img) => imagefilledrectangle($img, 5, 5, 20, 20, imagecolorallocate($img, 0, 0, 0)));
        $b = $this->png('d.png', fn ($img) => imagefilledrectangle($img, 35, 35, 55, 55, imagecolorallocate($img, 0, 0, 0)));
        $this->assertLessThan(0.95, (new VisualComparator())->compare($a, $b));
    }

    public function testGenerateDiffWritesRedPixels(): void
    {
        $a = $this->png('e.png', fn ($img) => null);
        $b = $this->png('f.png', fn ($img) => imagefilledrectangle($img, 20, 20, 40, 40, imagecolorallocate($img, 0, 0, 0)));
        $out = $this->dir . '/diff.png';
        (new VisualComparator())->generateDiff($a, $b, $out);
        $this->assertFileExists($out);
        $img = imagecreatefrompng($out);
        $rgb = imagecolorat($img, 30, 30);
        $this->assertGreaterThan(150, ($rgb >> 16) & 0xFF);
        $this->assertLessThan(120, ($rgb >> 8) & 0xFF);
        imagedestroy($img);
    }

    /** Page « réelle » : fond clair, bandeau, blocs de texte simulés. */
    private function page(string $name, bool $changedLine = false, bool $noise = false, int $w = 1920, int $h = 1080): string
    {
        return $this->png($name, function ($img) use ($changedLine, $noise, $w, $h) {
            imagefilledrectangle($img, 0, 0, $w - 1, 120, imagecolorallocate($img, 30, 60, 90));
            for ($y = 200; $y < $h - 100; $y += 60) {
                imagefilledrectangle($img, 300, $y, 1400, $y + 18, imagecolorallocate($img, 40, 40, 40));
            }
            if ($changedLine) {
                // ligne de texte modifiée : bloc 200×20 dans une zone blanche
                imagefilledrectangle($img, 800, 530, 999, 549, imagecolorallocate($img, 10, 10, 10));
            }
            if ($noise) {
                // bruit d'anticrénelage : +/-5 par canal sur quelques pixels, sous la tolérance
                for ($i = 0; $i < 5000; $i++) {
                    $x = ($i * 7919) % $w;
                    $y = ($i * 104729) % $h;
                    $c = imagecolorat($img, $x, $y);
                    $r = max(0, (($c >> 16) & 0xFF) - 5);
                    $g = max(0, (($c >> 8) & 0xFF) - 5);
                    $b = max(0, ($c & 0xFF) - 5);
                    imagesetpixel($img, $x, $y, imagecolorallocate($img, $r, $g, $b));
                }
            }
        }, $w, $h);
    }

    public function testSmallTextChangeIsDetected(): void
    {
        $a = $this->page('p1.png');
        $b = $this->page('p2.png', changedLine: true);
        $score = (new VisualComparator())->compare($a, $b);
        $this->assertLessThan(0.999, $score);
        $this->assertGreaterThan(0.99, $score);
    }

    public function testIdenticalPagesScoreExactlyOne(): void
    {
        $this->assertSame(1.0, (new VisualComparator())->compare($this->page('p3.png'), $this->page('p4.png')));
    }

    public function testNoiseWithinToleranceScoresOne(): void
    {
        $this->assertSame(1.0, (new VisualComparator())->compare($this->page('p5.png'), $this->page('p6.png', noise: true)));
    }

    public function testCompareAndDiffReturnsScoreAndWritesDiff(): void
    {
        $a = $this->page('p7.png');
        $b = $this->page('p8.png', changedLine: true);
        $out = $this->dir . '/sub/diff2.png';
        $score = (new VisualComparator())->compareAndDiff($a, $b, $out);
        $this->assertEqualsWithDelta(1 - 4000 / (1920 * 1080), $score, 1e-9);
        $this->assertFileExists($out);
        $img = imagecreatefrompng($out);
        $this->assertSame([1920, 1080], [imagesx($img), imagesy($img)]);
        $changed = imagecolorat($img, 900, 540);
        $this->assertGreaterThan(150, ($changed >> 16) & 0xFF);
        $this->assertLessThan(120, ($changed >> 8) & 0xFF);
        // pixel inchangé blanc => gris clair (0.35 * 255 + 165 ≈ 254)
        $same = imagecolorat($img, 100, 1000);
        $this->assertSame(($same >> 16) & 0xFF, ($same >> 8) & 0xFF);
        $this->assertGreaterThan(240, ($same >> 16) & 0xFF);
        // pixel inchangé sombre => gris « lavé » (0.35 * ~40 + 165 ≈ 179)
        $dark = imagecolorat($img, 500, 205);
        $this->assertEqualsWithDelta(179, ($dark >> 8) & 0xFF, 6);
        imagedestroy($img);
    }

    public function testSizeMismatchCountsNonOverlappingAreaAsChanged(): void
    {
        $a = $this->png('s1.png', fn ($img) => null, 100, 100);
        $b = $this->png('s2.png', fn ($img) => null, 100, 120);
        $out = $this->dir . '/diff3.png';
        $score = (new VisualComparator())->compareAndDiff($a, $b, $out);
        $this->assertEqualsWithDelta(1 - 2000 / 12000, $score, 1e-9);
        $img = imagecreatefrompng($out);
        $this->assertSame([100, 120], [imagesx($img), imagesy($img)]);
        $this->assertGreaterThan(150, (imagecolorat($img, 50, 110) >> 16) & 0xFF);
        $this->assertLessThan(120, (imagecolorat($img, 50, 110) >> 8) & 0xFF);
        imagedestroy($img);
    }
}
