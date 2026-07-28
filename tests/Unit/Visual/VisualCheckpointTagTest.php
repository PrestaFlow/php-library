<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Tests\TestsSuite;

final class VisualCheckpointTagTest extends TestCase
{
    private string $cwd;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->tmpDir = sys_get_temp_dir() . '/pfvis_checkpoint_' . getmypid() . '_' . uniqid();
        @mkdir($this->tmpDir, 0777, true);
        chdir($this->tmpDir);

        TestsSuite::$visualResults = [];
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        TestsSuite::$visualResults = [];
    }

    private function png(string $path, int $w = 40, int $h = 40): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagepng($img, $path);
        imagedestroy($img);
    }

    private function makePage(): CommonPage
    {
        $page = new class ('en', '8.1.0', []) extends CommonPage {
            public function getPage()
            {
                return new class {
                    public function screenshot(array $opts = [])
                    {
                        return new class {
                            public function saveToFile(string $path): void
                            {
                                $img = imagecreatetruecolor(40, 40);
                                imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
                                imagepng($img, $path);
                                imagedestroy($img);
                            }
                        };
                    }

                    public function getFullPageClip()
                    {
                        return [];
                    }

                    public function evaluate(string $js)
                    {
                        return new class {
                            public function getReturnValue()
                            {
                                return [1280, 720];
                            }
                        };
                    }
                };
            }
        };

        $page->setMajorVersion('9');
        $page->setLocale('fr');

        return $page;
    }

    public function testDefaultAutoTagIsResolvedAndRecorded(): void
    {
        $page = $this->makePage();

        $page->visualCheckpoint('home');

        $this->assertCount(1, TestsSuite::$visualResults);
        $result = TestsSuite::$visualResults[0];

        $this->assertSame('home', $result['name']);
        $this->assertSame('auto-v9-1280x720-fr', $result['tag']);
        $this->assertSame('baseline', $result['status']);
        $this->assertStringContainsString('home--auto-v9-1280x720-fr.png', $result['actual']);
    }

    public function testExplicitTagIsPreservedVerbatimInResultAndPath(): void
    {
        $page = $this->makePage();

        $page->visualCheckpoint('home', tag: 'my-custom');

        $this->assertCount(1, TestsSuite::$visualResults);
        $result = TestsSuite::$visualResults[0];

        $this->assertSame('my-custom', $result['tag']);
        $this->assertStringContainsString('home--my-custom.png', $result['actual']);
        $this->assertStringContainsString('home--my-custom.png', $result['reference']);
    }
}
