<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Utils\Screenshots;

/**
 * PRESTAFLOW_VISUAL_UPDATE : la capture (chemin identique) remplace la
 * référence au lieu d'être comparée.
 */
final class VisualUpdateModeTest extends TestCase
{
    private string $cwd;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->tmpDir = sys_get_temp_dir() . '/pfvis_update_' . getmypid() . '_' . uniqid();
        @mkdir($this->tmpDir, 0777, true);
        chdir($this->tmpDir);
        TestsSuite::$visualResults = [];
        $this->clearEnv();
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        TestsSuite::$visualResults = [];
        $this->clearEnv();
    }

    private function clearEnv(): void
    {
        unset($_ENV['PRESTAFLOW_VISUAL_UPDATE'], $_SERVER['PRESTAFLOW_VISUAL_UPDATE']);
        putenv('PRESTAFLOW_VISUAL_UPDATE');
    }

    /** Page factice : capture viewport 40×40 de la couleur $color. */
    private function makePage(array $color): CommonPage
    {
        return new class ('en', '8.1.0', [], $color) extends CommonPage {
            public int $captures = 0;

            public function __construct($locale, $version, $options, public array $color)
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

                    public function screenshot(array $opts = [])
                    {
                        $this->outer->captures++;
                        $color = $this->outer->color;

                        return new class ($color) {
                            public function __construct(private array $color)
                            {
                            }

                            public function saveToFile(string $path): void
                            {
                                $img = imagecreatetruecolor(40, 40);
                                imagefill($img, 0, 0, imagecolorallocate($img, ...$this->color));
                                imagepng($img, $path);
                                imagedestroy($img);
                            }
                        };
                    }

                    public function evaluate(string $js)
                    {
                        $value = str_contains($js, 'scrollY') ? [0, 0] : [1280, 720];

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

    private function referenceFor(string $name): string
    {
        $record = TestsSuite::$visualResults[array_key_last(TestsSuite::$visualResults)];
        $this->assertSame($name, $record['name']);

        return $record['reference'];
    }

    private function colorAt(string $png): int
    {
        return imagecolorat(imagecreatefrompng($png), 5, 5);
    }

    public function test_update_mode_overwrites_existing_reference(): void
    {
        // 1er passage : auto-baseline noire
        $this->makePage([0, 0, 0])->visualCheckpoint('home', null, null, false);
        $refPath = $this->referenceFor('home');
        $this->assertSame(0x000000, $this->colorAt($refPath));

        // une diff d'un run précédent traîne
        $diffPath = Screenshots::diffPath(basename($refPath), create: true);
        file_put_contents($diffPath, 'stale');

        $_ENV['PRESTAFLOW_VISUAL_UPDATE'] = 'true';
        TestsSuite::$visualResults = [];
        $page = $this->makePage([255, 0, 0]);
        $page->visualCheckpoint('home', null, null, false);

        $this->assertSame(1, $page->captures);
        $this->assertCount(1, TestsSuite::$visualResults);
        $record = TestsSuite::$visualResults[0];
        $this->assertSame('baseline', $record['status']);
        $this->assertTrue($record['updated']);
        $this->assertNull($record['diff']);
        $this->assertNull($record['score']);
        $this->assertSame([0, 0], $record['origin']);
        $this->assertSame($refPath, $record['reference']);
        $this->assertSame(0xFF0000, $this->colorAt($refPath), 'la référence doit être la nouvelle capture');
        $this->assertFileEquals($record['actual'], $refPath);
        $this->assertFileDoesNotExist($diffPath);
    }

    public function test_update_mode_without_reference_is_a_first_baseline(): void
    {
        $_ENV['PRESTAFLOW_VISUAL_UPDATE'] = '1';
        $this->makePage([0, 0, 255])->visualCheckpoint('footer', null, null, false);

        $record = TestsSuite::$visualResults[0];
        $this->assertSame('baseline', $record['status']);
        $this->assertFalse($record['updated'] ?? false);
        $this->assertNull($record['diff']);
        $this->assertFileExists($record['reference']);
        $this->assertFileDoesNotExist(Screenshots::diffPath(basename($record['reference'])));
    }

    public function test_default_mode_still_compares_against_existing_reference(): void
    {
        $this->makePage([0, 0, 0])->visualCheckpoint('home', null, null, false);
        $refPath = $this->referenceFor('home');

        $_ENV['PRESTAFLOW_VISUAL_UPDATE'] = 'false';
        TestsSuite::$visualResults = [];
        // budget large : on vérifie qu'on compare, sans déclencher le chemin d'échec
        $this->makePage([255, 255, 255])->visualCheckpoint('home', null, null, false, 'auto', [], 100000);

        $record = TestsSuite::$visualResults[0];
        $this->assertSame('pass', $record['status']);
        $this->assertSame(1600, $record['changed_pixels']);
        $this->assertFalse($record['updated'] ?? false);
        $this->assertNotNull($record['diff']);
        $this->assertSame(0x000000, $this->colorAt($refPath), 'la référence ne doit pas bouger hors mode update');
    }

    public function test_visual_update_mode_parses_booleans(): void
    {
        $this->assertFalse(CommonPage::visualUpdateMode());
        foreach (['1' => true, 'true' => true, 'yes' => true, 'on' => true, '0' => false, 'false' => false, '' => false, 'nope' => false] as $raw => $expected) {
            $_ENV['PRESTAFLOW_VISUAL_UPDATE'] = (string) $raw;
            $this->assertSame($expected, CommonPage::visualUpdateMode(), "valeur « {$raw} »");
        }
    }
}
