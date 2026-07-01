<?php

namespace PrestaFlow\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Utils\Screenshots;

final class ScreenshotsTest extends TestCase
{
    private string $cwd;
    private string $tmp;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->tmp = sys_get_temp_dir() . '/pf_shots_' . uniqid();
        mkdir($this->tmp, 0777, true);
        chdir($this->tmp);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->tmp);
        unset($_ENV['PRESTAFLOW_SCREENSHOT_DELAY']);
    }

    public function testErrorsDirDoesNotCreateByDefault(): void
    {
        $dir = Screenshots::errorsDir();
        $this->assertStringEndsWith('prestaflow/screens/errors', $dir);
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testErrorsDirCreatesWhenRequested(): void
    {
        $dir = Screenshots::errorsDir(create: true);
        $this->assertDirectoryExists($dir);
        $this->assertStringEndsWith('prestaflow/screens/errors', $dir);
    }

    public function testErrorPathConcatenates(): void
    {
        $this->assertSame(Screenshots::errorsDir() . '/x.png', Screenshots::errorPath('x.png'));
    }

    public function testRelativeErrorPathIsStable(): void
    {
        $this->assertSame('prestaflow/screens/errors/x.png', Screenshots::relativeErrorPath('x.png'));
    }

    public function testCaptureDelayDefaultsToThree(): void
    {
        unset($_ENV['PRESTAFLOW_SCREENSHOT_DELAY']);
        $this->assertSame(3, Screenshots::captureDelay());
    }

    public function testCaptureDelayReadsEnv(): void
    {
        $_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] = '0';
        $this->assertSame(0, Screenshots::captureDelay());
        $_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] = '5';
        $this->assertSame(5, Screenshots::captureDelay());
    }

    public function testCaptureDelayNeverNegative(): void
    {
        $_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] = '-2';
        $this->assertSame(0, Screenshots::captureDelay());
    }
}
