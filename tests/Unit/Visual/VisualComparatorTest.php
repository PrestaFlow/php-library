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
}
