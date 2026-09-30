<?php

namespace PrestaFlow\Tests\Unit\Visual;

use HeadlessChromium\Clip;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Visual\PageScripts;
use PrestaFlow\Library\Visual\PageSnapshot;
use PrestaFlow\Library\Visual\SnapshotException;

final class PageSnapshotTest extends TestCase
{
    /** @var array{log: string[], closed: bool, options: array|null, clip: Clip|null, shot: array|null} */
    private array $rec;

    private function snapshot(array $values = [], ?\Throwable $navigateThrows = null, bool $factoryThrows = false): PageSnapshot
    {
        $this->rec = ['log' => [], 'closed' => false, 'options' => null, 'clip' => null, 'shot' => null];
        $rec = &$this->rec;
        $values += [
            'stable' => true,
            'status' => 200,
            'size' => [1920, 4000],
            'map' => json_encode([['i' => 0, 'p' => -1, 'tag' => 'body', 'id' => '', 'classes' => [], 'box' => [0, 0, 1920, 4000], 'selector' => 'body', 'matches' => 1]]),
        ];

        $factory = function (array $options) use (&$rec, $values, $navigateThrows, $factoryThrows) {
            if ($factoryThrows) {
                throw new \RuntimeException('Chrome not found');
            }
            $rec['options'] = $options;

            return new class ($rec, $values, $navigateThrows) {
                public function __construct(private array &$rec, private array $values, private ?\Throwable $navigateThrows)
                {
                }

                public function createPage()
                {
                    $rec = &$this->rec;
                    $values = $this->values;
                    $throws = $this->navigateThrows;

                    return new class ($rec, $values, $throws) {
                        public function __construct(private array &$rec, private array $values, private ?\Throwable $throws)
                        {
                        }

                        public function navigate(string $url)
                        {
                            $this->rec['log'][] = 'navigate '.$url;
                            $throws = $this->throws;

                            return new class ($throws) {
                                public function __construct(private ?\Throwable $throws)
                                {
                                }

                                public function waitForNavigation($event = null, $timeout = null)
                                {
                                    if ($this->throws) {
                                        throw $this->throws;
                                    }
                                }
                            };
                        }

                        public function evaluate(string $js)
                        {
                            $v = null;
                            if (str_contains($js, PageScripts::STABLE_CONDITION)) {
                                $this->rec['log'][] = 'stable';
                                $v = $this->values['stable'];
                            } elseif ($js === PageScripts::SETTLE_ANIMATIONS) {
                                $this->rec['log'][] = 'settle';
                            } elseif (str_contains($js, 'responseStatus')) {
                                $this->rec['log'][] = 'status';
                                $v = $this->values['status'];
                            } elseif (str_contains($js, 'scrollHeight')) {
                                $this->rec['log'][] = 'size';
                                $v = $this->values['size'];
                            } elseif (str_contains($js, 'scrollTo(0')) {
                                $this->rec['log'][] = 'top';
                            } elseif (str_contains($js, PageScripts::ELEMENT_MAP)) {
                                $this->rec['log'][] = 'map';
                                $v = $this->values['map'];
                            }

                            return new class ($v) {
                                public function __construct(private $v)
                                {
                                }

                                public function getReturnValue($timeout = null)
                                {
                                    return $this->v;
                                }
                            };
                        }

                        public function screenshot(array $opts = [])
                        {
                            $this->rec['log'][] = 'screenshot';
                            $this->rec['clip'] = $opts['clip'] ?? null;
                            $this->rec['shot'] = $opts;

                            return new class {
                                public function getBase64($timeout = null)
                                {
                                    return base64_encode('JPEGDATA');
                                }
                            };
                        }
                    };
                }

                public function close()
                {
                    $this->rec['closed'] = true;
                }
            };
        };

        return new PageSnapshot($factory, stableTimeoutMs: 50, pollMs: 10);
    }

    public function test_happy_path_order_result_and_browser_closed(): void
    {
        $result = $this->snapshot()->take('https://shop.test/fr/', 'mobile');

        $this->assertSame(['navigate https://shop.test/fr/', 'stable', 'settle', 'status', 'size', 'top', 'map', 'screenshot'], $this->rec['log']);
        $this->assertTrue($this->rec['closed']);
        $this->assertSame([390, 844], $this->rec['options']['windowSize']);
        $this->assertStringContainsString('iPhone', $this->rec['options']['userAgent']);
        $this->assertSame('JPEGDATA', $result->image);
        $this->assertSame('image/jpeg', $result->mime);
        $this->assertSame(['jpeg', 80], [$this->rec['shot']['format'], $this->rec['shot']['quality']]);
        $this->assertSame([1920, 4000], [$result->width, $result->height]);
        $this->assertSame('body', $result->elements[0]['selector']);
        $this->assertTrue($result->stable);
    }

    public function test_height_is_capped_and_clip_covers_the_captured_page(): void
    {
        $result = $this->snapshot(['size' => [1920, 40000]])->take('https://shop.test/', 'desktop');

        $this->assertSame(PageSnapshot::MAX_HEIGHT, $result->height);
        $clip = $this->rec['clip'];
        $this->assertSame([0.0, 0.0, 1920.0, 15000.0], array_map('floatval', [$clip->getX(), $clip->getY(), $clip->getWidth(), $clip->getHeight()]));
    }

    public function test_unstable_page_is_reported_not_fatal(): void
    {
        $result = $this->snapshot(['stable' => false])->take('https://shop.test/', 'desktop');

        $this->assertFalse($result->stable);
        $this->assertContains('screenshot', $this->rec['log']);
    }

    public function test_http_error_status_throws_and_closes_the_browser(): void
    {
        $snapshot = $this->snapshot(['status' => 404]);

        try {
            $snapshot->take('https://shop.test/x', 'desktop');
            $this->fail('SnapshotException attendue');
        } catch (SnapshotException $e) {
            $this->assertStringContainsString('404', $e->getMessage());
        }
        $this->assertTrue($this->rec['closed']);
    }

    public function test_navigation_timeout_throws_a_readable_error(): void
    {
        $snapshot = $this->snapshot([], new \HeadlessChromium\Exception\OperationTimedOut('timeout'));

        $this->expectException(SnapshotException::class);
        $this->expectExceptionMessage("n'a pas répondu");

        $snapshot->take('https://shop.test/', 'desktop');
    }

    public function test_browser_launch_failure_throws_a_readable_error(): void
    {
        $snapshot = $this->snapshot([], null, factoryThrows: true);

        $this->expectException(SnapshotException::class);
        $this->expectExceptionMessage('Navigateur');

        $snapshot->take('https://shop.test/', 'desktop');
    }
}
