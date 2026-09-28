<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Tests\TestsSuite;

final class VisualCheckpointMasksTest extends TestCase
{
    private string $cwd;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->tmpDir = sys_get_temp_dir() . '/pfvis_masks_' . getmypid() . '_' . uniqid();
        @mkdir($this->tmpDir, 0777, true);
        chdir($this->tmpDir);

        TestsSuite::$visualResults = [];
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        TestsSuite::$visualResults = [];
    }

    private function makePage(): CommonPage
    {
        $page = new class ('en', '8.1.0', []) extends CommonPage {
            public array $evaluatedLog = [];

            public function getPage()
            {
                $outer = $this;

                return new class ($outer) {
                    private $outer;

                    public function __construct($outer)
                    {
                        $this->outer = $outer;
                    }

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
                        $this->outer->evaluatedLog[] = $js;

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

    public function test_masks_are_injected_then_removed(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, 0.98, false, 'auto', ['.carousel', '.price']);

        $js = implode("\n", $page->evaluatedLog);
        $this->assertStringContainsString('pf-visual-masks', $js);
        $this->assertStringContainsString('.carousel, .price', $js);
        $this->assertStringContainsString("getElementById('pf-visual-masks')", $js);
        $inject = array_key_first(array_filter($page->evaluatedLog, fn ($s) => str_contains($s, 'visibility: hidden')));
        $remove = array_key_first(array_filter($page->evaluatedLog, fn ($s) => str_contains($s, '.remove()')));
        $this->assertLessThan($remove, $inject);
    }

    public function test_no_mask_js_without_masks(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, 0.98, false);
        $this->assertStringNotContainsString('pf-visual-masks', implode("\n", $page->evaluatedLog));
    }
}
