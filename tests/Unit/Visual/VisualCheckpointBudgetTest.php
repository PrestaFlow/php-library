<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Règle de passage d'un checkpoint : budget de pixels changés (défaut 100),
 * ou seuil de ratio historique quand $threshold est fourni seul.
 */
final class VisualCheckpointBudgetTest extends TestCase
{
    private string $cwd;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->tmpDir = sys_get_temp_dir() . '/pfvis_budget_' . getmypid() . '_' . uniqid();
        @mkdir($this->tmpDir, 0777, true);
        chdir($this->tmpDir);
        TestsSuite::$visualResults = [];
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        TestsSuite::$visualResults = [];
    }

    /** Capture 1920×1080 « page » : fond blanc, blocs sombres, éventuellement une ligne de texte / du bruit. */
    private function capture(string $name, ?string $text = null, bool $noise = false): string
    {
        $img = imagecreatetruecolor(1920, 1080);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        for ($y = 200; $y < 980; $y += 60) {
            imagefilledrectangle($img, 300, $y, 1400, $y + 18, imagecolorallocate($img, 40, 40, 40));
        }
        if ($text !== null) {
            imagestring($img, 5, 800, 532, $text, imagecolorallocate($img, 0, 0, 0));
        }
        if ($noise) {
            for ($y = 0; $y < 1080; $y += 3) {
                for ($x = 0; $x < 1920; $x += 3) {
                    $c = imagecolorat($img, $x, $y) & 0xFF;
                    $v = max(0, $c - 5);
                    imagesetpixel($img, $x, $y, imagecolorallocate($img, $v, $v, $v));
                }
            }
        }
        $path = $this->tmpDir . '/' . $name;
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    private function makePage(): CommonPage
    {
        $page = new class ('en', '8.1.0', []) extends CommonPage {
            public string $source = '';

            public function getPage()
            {
                $outer = $this;

                return new class ($outer) {
                    public function __construct(private $outer)
                    {
                    }

                    public function screenshot(array $opts = [])
                    {
                        $src = $this->outer->source;

                        return new class ($src) {
                            public function __construct(private string $src)
                            {
                            }

                            public function saveToFile(string $path): void
                            {
                                copy($this->src, $path);
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
                            public function getReturnValue($timeout = null)
                            {
                                return [1920, 1080];
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

    /** Pose la baseline puis capture $actual ; renvoie [exception?, dernier résultat enregistré]. */
    private function check(string $actual, ?float $threshold = null, ?int $maxDiffPixels = null): array
    {
        $page = $this->makePage();
        $page->source = $this->capture('ref.png', 'Pas de compte ? Creez-en un');
        $page->visualCheckpoint('login', null, $threshold, false, 'auto', [], $maxDiffPixels);

        $page->source = $actual;
        $error = null;
        try {
            $page->visualCheckpoint('login', null, $threshold, false, 'auto', [], $maxDiffPixels);
        } catch (\Throwable $e) {
            $error = $e;
        }

        return [$error, end(TestsSuite::$visualResults)];
    }

    public function test_single_text_line_change_fails_with_default_budget(): void
    {
        [$error, $result] = $this->check($this->capture('act.png', 'Test de regression visuelle'));

        $this->assertNotNull($error);
        $this->assertSame('fail', $result['status']);
        $this->assertGreaterThan(0.999, $result['score']); // invisible pour l'ancien seuil 0.999
        $this->assertGreaterThan(100, $result['changed_pixels']);
        $this->assertSame(1920 * 1080, $result['total_pixels']);
        $this->assertSame(100, $result['max_diff_pixels']);
        $this->assertEqualsWithDelta(1 - 100 / (1920 * 1080), $result['threshold'], 1e-12);
    }

    public function test_identical_capture_passes(): void
    {
        [$error, $result] = $this->check($this->capture('same.png', 'Pas de compte ? Creez-en un'));

        $this->assertNull($error);
        $this->assertSame('pass', $result['status']);
        $this->assertSame(0, $result['changed_pixels']);
    }

    public function test_noise_within_colour_tolerance_passes(): void
    {
        [$error, $result] = $this->check($this->capture('noise.png', 'Pas de compte ? Creez-en un', noise: true));

        $this->assertNull($error);
        $this->assertSame('pass', $result['status']);
    }

    public function test_explicit_budget_is_honoured(): void
    {
        [$error, $result] = $this->check($this->capture('act2.png', 'Test de regression visuelle'), maxDiffPixels: 100000);

        $this->assertNull($error);
        $this->assertSame('pass', $result['status']);
        $this->assertSame(100000, $result['max_diff_pixels']);
    }

    public function test_explicit_threshold_alone_uses_legacy_ratio_rule(): void
    {
        [$error, $result] = $this->check($this->capture('act3.png', 'Test de regression visuelle'), threshold: 0.999);

        $this->assertNull($error);
        $this->assertSame('pass', $result['status']);
        $this->assertSame(0.999, $result['threshold']);
        $this->assertNull($result['max_diff_pixels']);
        $this->assertGreaterThan(100, $result['changed_pixels']);
    }

    public function test_threshold_and_budget_together_use_the_budget(): void
    {
        [$error, $result] = $this->check($this->capture('act4.png', 'Test de regression visuelle'), threshold: 0.5, maxDiffPixels: 100);

        $this->assertNotNull($error);
        $this->assertSame('fail', $result['status']);
        $this->assertSame(100, $result['max_diff_pixels']);
    }

    public function test_baseline_records_numeric_threshold_and_budget(): void
    {
        $page = $this->makePage();
        $page->source = $this->capture('base.png');
        $page->visualCheckpoint('home', null, null, false);
        $result = end(TestsSuite::$visualResults);

        $this->assertSame('baseline', $result['status']);
        $this->assertIsFloat($result['threshold']);
        $this->assertSame(100, $result['max_diff_pixels']);
        $this->assertSame(1920 * 1080, $result['total_pixels']);
        $this->assertNull($result['changed_pixels']);
    }
}
