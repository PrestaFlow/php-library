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

    public function test_hidden_elements_are_removed_from_layout_then_restored(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false, 'auto', [], null, ['.onboarding-popup', '.a, .b']);
        $js = implode("\n", $page->evaluatedLog);

        $this->assertStringContainsString('pf-visual-hide', $js);
        $this->assertStringContainsString(':is(.onboarding-popup) { display: none !important; }', $js);
        $this->assertStringContainsString(':is(.a, .b) { display: none !important; }', $js);
        $this->assertStringContainsString("getElementById('pf-visual-hide')", $js);
    }

    public function test_transitions_are_frozen_before_settling_then_restored(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false, 'auto', [], null, [], true);
        $log = $page->evaluatedLog;

        $freeze = array_key_first(array_filter($log, fn ($s) => str_contains($s, 'transition: none !important')));
        $settle = array_key_first(array_filter($log, fn ($s) => str_contains($s, 'getAnimations')));
        $remove = array_key_first(array_filter($log, fn ($s) => str_contains($s, "getElementById('pf-visual-freeze')")));
        $this->assertNotNull($freeze);
        $this->assertNotNull($settle);
        $this->assertNotNull($remove);
        $this->assertLessThan($settle, $freeze);
        $this->assertLessThan($remove, $settle);
    }

    public function test_no_hide_nor_freeze_style_by_default(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false, 'auto', ['.price']);
        $js = implode("\n", $page->evaluatedLog);

        $this->assertStringNotContainsString('pf-visual-hide', $js);
        $this->assertStringNotContainsString('pf-visual-freeze', $js);
    }

    public function test_hide_and_freeze_styles_are_removed_when_the_capture_fails(): void
    {
        $page = $this->makePage(screenshotThrows: true);
        try {
            $page->visualCheckpoint('hdr', null, null, false, 'auto', [], null, ['.popup'], true);
            $this->fail('la capture devait lever');
        } catch (\RuntimeException) {
        }
        $js = implode("\n", $page->evaluatedLog);

        $this->assertStringContainsString("getElementById('pf-visual-hide')", $js);
        $this->assertStringContainsString("getElementById('pf-visual-freeze')", $js);
    }
    private static function firstIndex(array $log, callable $match): ?int
    {
        return array_key_first(array_filter($log, $match));
    }

    private static function isScreenshotMarker(string $js): bool
    {
        // le stub ne journalise pas screenshot() : en viewport, l'offset de scroll
        // est lu juste avant la capture
        return str_contains($js, 'window.scrollY');
    }

    public function test_hide_is_also_applied_inline_after_the_style_and_before_the_capture(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false, 'auto', [], null, ['.header-top', '.a, .b']);
        $log = $page->evaluatedLog;

        $style = self::firstIndex($log, fn ($s) => str_contains($s, ':is(.header-top) { display: none !important; }'));
        $inline = self::firstIndex($log, fn ($s) => str_contains($s, '__pfVisualInline') && str_contains($s, '"hide"') && !str_contains($s, 'removeProperty'));
        $shot = self::firstIndex($log, fn ($s) => self::isScreenshotMarker($s));
        $this->assertNotNull($style);
        $this->assertNotNull($inline);
        $this->assertNotNull($shot);
        $this->assertLessThan($inline, $style);
        $this->assertLessThan($shot, $inline);

        $js = $log[$inline];
        $this->assertStringContainsString(json_encode(['.header-top', '.a, .b']), $js);
        $this->assertStringContainsString('"display"', $js);
        $this->assertStringContainsString('"none"', $js);
        $this->assertStringContainsString("'important'", $js);
        $this->assertStringContainsString('getPropertyPriority', $js);
    }

    public function test_hide_inline_styles_are_restored_after_the_capture(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false, 'auto', [], null, ['.popup']);
        $log = $page->evaluatedLog;

        $shot = self::firstIndex($log, fn ($s) => self::isScreenshotMarker($s));
        $restore = self::firstIndex($log, fn ($s) => str_contains($s, '__pfVisualInline') && str_contains($s, '"hide"') && str_contains($s, 'removeProperty'));
        $this->assertNotNull($restore);
        $this->assertLessThan($restore, $shot);
    }

    public function test_masks_are_also_applied_inline_on_elements_and_descendants(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false, 'auto', ['#logos', '.a, .b']);
        $log = $page->evaluatedLog;

        $style = self::firstIndex($log, fn ($s) => str_contains($s, 'pf-visual-masks') && str_contains($s, 'createElement'));
        $inline = self::firstIndex($log, fn ($s) => str_contains($s, '__pfVisualInline') && str_contains($s, '"masks"') && !str_contains($s, 'removeProperty'));
        $shot = self::firstIndex($log, fn ($s) => self::isScreenshotMarker($s));
        $restore = self::firstIndex($log, fn ($s) => str_contains($s, '__pfVisualInline') && str_contains($s, '"masks"') && str_contains($s, 'removeProperty'));
        $this->assertNotNull($style);
        $this->assertNotNull($inline);
        $this->assertNotNull($restore);
        $this->assertLessThan($inline, $style);
        $this->assertLessThan($shot, $inline);
        $this->assertLessThan($restore, $shot);

        $js = $log[$inline];
        $this->assertStringContainsString(json_encode(['#logos', '.a, .b']), $js);
        $this->assertStringContainsString('"visibility"', $js);
        $this->assertStringContainsString('"hidden"', $js);
        $this->assertStringContainsString('true', $js); // descendants inclus
        $this->assertStringContainsString("querySelectorAll('*')", $js);
    }

    public function test_inline_styles_are_restored_even_when_the_capture_throws(): void
    {
        $page = $this->makePage(screenshotThrows: true);
        try {
            $page->visualCheckpoint('hdr', null, null, false, 'auto', ['.carousel'], null, ['.popup']);
            $this->fail('la capture devait lever');
        } catch (\RuntimeException) {
        }
        $restores = array_filter($page->evaluatedLog, fn ($s) => str_contains($s, '__pfVisualInline') && str_contains($s, 'removeProperty'));
        $joined = implode("\n", $restores);

        $this->assertStringContainsString('"masks"', $joined);
        $this->assertStringContainsString('"hide"', $joined);
    }

    public function test_no_inline_js_without_hide_nor_masks(): void
    {
        $page = $this->makePage();
        $page->visualCheckpoint('hdr', null, null, false);

        $this->assertStringNotContainsString('__pfVisualInline', implode("\n", $page->evaluatedLog));
    }
}
