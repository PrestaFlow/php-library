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

    private function makePage(bool $screenshotThrows = false): CommonPage
    {
        $page = new class ('en', '8.1.0', [], $screenshotThrows) extends CommonPage {
            public array $evaluatedLog = [];
            public bool $screenshotThrows;

            public function __construct($locale, $version, $options, bool $screenshotThrows = false)
            {
                parent::__construct($locale, $version, $options);
                $this->screenshotThrows = $screenshotThrows;
            }

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
                        if ($this->outer->screenshotThrows) {
                            throw new \RuntimeException('capture échouée (stub)');
                        }

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

                        return new class ($js) {
                            public function __construct(private string $js)
                            {
                            }

                            public function getReturnValue($timeout = null)
                            {
                                return str_contains($this->js, 'scrollY') ? [0, 287] : [1280, 720];
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
        // Une règle CSS par sélecteur, pas un sélecteur groupé : un sélecteur
        // invalide ne doit pas désactiver le masquage des autres.
        $this->assertStringContainsString(':is(.carousel), :is(.carousel) * { visibility: hidden', $js);
        $this->assertStringContainsString(':is(.price), :is(.price) * { visibility: hidden', $js);
        $this->assertStringContainsString("getElementById('pf-visual-masks')", $js);
        $inject = array_key_first(array_filter($page->evaluatedLog, fn ($s) => str_contains($s, 'visibility: hidden')));
        $remove = array_key_first(array_filter($page->evaluatedLog, fn ($s) => str_contains($s, '.remove()')));
        $this->assertNotNull($inject);
        $this->assertNotNull($remove);
        $this->assertLessThan($remove, $inject);
    }

    /**
     * visibility is overridable by descendants (Owl Carousel forces
     * `.owl-stage { visibility: visible }`): the mask must hide every
     * descendant too, and a selector list must stay grouped per mask.
     */
    public function test_masks_hide_descendants_and_keep_selector_lists_grouped(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, 0.98, false, 'auto', ['#logos', '.a, .b']);

        $js = implode("\n", $page->evaluatedLog);
        $this->assertStringContainsString(':is(#logos), :is(#logos) * { visibility: hidden !important; }', $js);
        $this->assertStringContainsString(':is(.a, .b), :is(.a, .b) * { visibility: hidden !important; }', $js);
    }

    /**
     * Animated backgrounds (e.g. an infinite `bgmoveleft` on a 404 page) never
     * render twice the same: animations are settled before the capture.
     */
    public function test_animations_are_settled_before_the_capture_even_without_masks(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, 0.98, false);

        $freeze = array_key_first(array_filter($page->evaluatedLog, fn ($s) => str_contains($s, 'getAnimations')));
        $this->assertNotNull($freeze);
        $js = $page->evaluatedLog[$freeze];
        $this->assertStringContainsString('.cancel()', $js);
        $this->assertStringContainsString('.finish()', $js);
    }

    public function test_no_mask_js_without_masks(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, 0.98, false);
        $this->assertStringNotContainsString('pf-visual-masks', implode("\n", $page->evaluatedLog));
    }

    public function test_masks_are_removed_even_when_capture_throws(): void
    {
        $page = $this->makePage(screenshotThrows: true);

        $this->expectException(\RuntimeException::class);

        try {
            $page->visualCheckpoint('hdr', null, 0.98, false, 'auto', ['.carousel']);
        } finally {
            $js = implode("\n", $page->evaluatedLog);
            $this->assertStringContainsString("getElementById('pf-visual-masks')", $js);
            $this->assertStringContainsString('.remove()', $js);
        }
    }

    public function test_viewport_capture_records_the_scroll_offset_as_origin(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, 0.98, false);

        $this->assertSame([0, 287], TestsSuite::$visualResults[0]['origin']);
    }

    public function test_full_page_capture_records_a_zero_origin(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, 0.98, true);

        $this->assertSame([0, 0], TestsSuite::$visualResults[0]['origin']);
    }
}
