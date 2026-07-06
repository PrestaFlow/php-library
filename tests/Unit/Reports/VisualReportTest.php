<?php

namespace PrestaFlow\Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Reports\VisualReport;

final class VisualReportTest extends TestCase
{
    private function tmpPng(): string
    {
        $p = tempnam(sys_get_temp_dir(), 'pf') . '.png';
        $img = imagecreatetruecolor(4, 4);
        imagepng($img, $p); imagedestroy($img);
        return $p;
    }

    public function testHtmlContainsCheckpointAndImages(): void
    {
        $ref = $this->tmpPng(); $actual = $this->tmpPng(); $diff = $this->tmpPng();
        $html = (new VisualReport())->renderHtml([
            ['name'=>'login','status'=>'fail','score'=>0.82,'threshold'=>0.98,'reference'=>$ref,'actual'=>$actual,'diff'=>$diff],
            ['name'=>'header','status'=>'baseline','score'=>null,'threshold'=>0.98,'reference'=>$ref,'actual'=>null,'diff'=>null],
        ]);
        $this->assertStringContainsString('login', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('82', $html);
        $this->assertStringContainsString('baseline', $html);
    }

    public function testJsonSummary(): void
    {
        $json = (new VisualReport())->renderJson([
            ['name'=>'login','status'=>'pass','score'=>0.99,'threshold'=>0.98,'reference'=>null,'actual'=>null,'diff'=>null],
        ]);
        $data = json_decode($json, true);
        $this->assertSame('login', $data[0]['name']);
        $this->assertSame('pass', $data[0]['status']);
    }
}
