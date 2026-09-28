<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Visual\VisualDevices;

final class VisualDevicesTest extends TestCase
{
    public function test_presets(): void
    {
        $this->assertSame(1920, VisualDevices::get('desktop')['width']);
        $this->assertSame([768, 1024], [VisualDevices::get('tablet')['width'], VisualDevices::get('tablet')['height']]);
        $this->assertStringContainsString('iPhone', VisualDevices::get('mobile')['userAgent']);
        $this->assertSame(['desktop', 'tablet', 'mobile'], VisualDevices::names());
    }

    public function test_unknown_device_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VisualDevices::get('watch');
    }
}
