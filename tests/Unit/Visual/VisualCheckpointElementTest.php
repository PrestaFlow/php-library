<?php

namespace PrestaFlow\Tests\Unit\Visual;

use HeadlessChromium\Clip;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Node::getClip() is viewport-relative (DOM box model) while the screenshot
 * clip is document-relative and cut to the viewport unless
 * captureBeyondViewport: an element below the fold (a footer) came out as a
 * blank image, and every run compared two blank images.
 */
final class VisualCheckpointElementTest extends TestCase
{
    private string $cwd;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->tmpDir = sys_get_temp_dir() . '/pfvis_element_' . getmypid() . '_' . uniqid();
        @mkdir($this->tmpDir, 0777, true);
        chdir($this->tmpDir);

        TestsSuite::$visualResults = [];
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        TestsSuite::$visualResults = [];
    }

    private function makePage(?Clip $nodeClip, array $scroll): CommonPage
    {
        return new class ('en', '8.1.0', [], $nodeClip, $scroll) extends CommonPage {
            public array $screenshotOptions = [];

            public function __construct($locale, $version, $options, public ?Clip $nodeClip, public array $scroll)
            {
                parent::__construct($locale, $version, $options);
            }

            public function getPage()
            {
                $outer = $this;

                return new class ($outer) {
                    public function __construct(private $outer)
                    {
                    }

                    public function dom()
                    {
                        $outer = $this->outer;

                        return new class ($outer) {
                            public function __construct(private $outer)
                            {
                            }

                            public function querySelector(string $selector)
                            {
                                $outer = $this->outer;

                                return new class ($outer) {
                                    public function __construct(private $outer)
                                    {
                                    }

                                    public function getClip(): ?Clip
                                    {
                                        return $this->outer->nodeClip;
                                    }
                                };
                            }
                        };
                    }

                    public function screenshot(array $opts = [])
                    {
                        $this->outer->screenshotOptions = $opts;

                        return new class {
                            public function saveToFile(string $path): void
                            {
                                $img = imagecreatetruecolor(40, 40);
                                imagepng($img, $path);
                                imagedestroy($img);
                            }
                        };
                    }

                    public function evaluate(string $js)
                    {
                        $value = str_contains($js, 'scrollY') ? $this->outer->scroll : [1280, 720];

                        return new class ($value) {
                            public function __construct(private $value)
                            {
                            }

                            public function getReturnValue($timeout = null)
                            {
                                return $this->value;
                            }
                        };
                    }
                };
            }
        };
    }

    public function test_element_clip_is_translated_to_document_coordinates_and_captured_beyond_the_viewport(): void
    {
        // Footer 400px below the top of a viewport scrolled by 5000px.
        $page = $this->makePage(new Clip(10, 400, 300, 1500), [0, 5000]);

        $page->visualCheckpoint('footer', '#footer');

        $opts = $page->screenshotOptions;
        $this->assertTrue($opts['captureBeyondViewport'] ?? false);
        $this->assertInstanceOf(Clip::class, $opts['clip']);
        $this->assertSame([10.0, 5400.0, 300.0, 1500.0], array_map('floatval', [
            $opts['clip']->getX(), $opts['clip']->getY(), $opts['clip']->getWidth(), $opts['clip']->getHeight(),
        ]));
    }

    public function test_element_without_a_render_box_fails_explicitly(): void
    {
        $page = $this->makePage(null, [0, 0]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('#footer');

        $page->visualCheckpoint('footer', '#footer');
    }
}
