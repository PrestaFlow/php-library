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

    /** @var \Closure(array): object fabrique de navigateur du dernier snapshot() (pour obtenir une page factice) */
    private \Closure $factory;

    private function snapshot(array $values = [], ?\Throwable $navigateThrows = null, bool $factoryThrows = false): PageSnapshot
    {
        // timeouts : délais reçus par getReturnValue() / getBase64() (null non noté)
        $this->rec = ['log' => [], 'event' => null, 'closed' => false, 'options' => null, 'clip' => null, 'shot' => null, 'timeouts' => []];
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
                            $rec = &$this->rec;

                            return new class ($rec, $throws) {
                                public function __construct(private array &$rec, private ?\Throwable $throws)
                                {
                                }

                                public function waitForNavigation($event = null, $timeout = null)
                                {
                                    $this->rec['event'] = $event;
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

                            $rec = &$this->rec;

                            return new class ($rec, $v) {
                                public function __construct(private array &$rec, private $v)
                                {
                                }

                                public function getReturnValue($timeout = null)
                                {
                                    if ($timeout !== null) {
                                        $this->rec['timeouts'][] = 'value '.$timeout;
                                    }

                                    return $this->v;
                                }
                            };
                        }

                        public function screenshot(array $opts = [])
                        {
                            $this->rec['log'][] = 'screenshot';
                            $this->rec['clip'] = $opts['clip'] ?? null;
                            $this->rec['shot'] = $opts;

                            $rec = &$this->rec;

                            return new class ($rec) {
                                public function __construct(private array &$rec)
                                {
                                }

                                public function getBase64($timeout = null)
                                {
                                    if ($timeout !== null) {
                                        $this->rec['timeouts'][] = 'base64 '.$timeout;
                                    }

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

        $this->factory = $factory;

        return new PageSnapshot($factory, stableTimeoutMs: 50, pollMs: 10);
    }

    public function test_happy_path_order_result_and_browser_closed(): void
    {
        $result = $this->snapshot()->take('https://shop.test/fr/', 'mobile');

        $this->assertSame(['navigate https://shop.test/fr/', 'stable', 'settle', 'status', 'size', 'top', 'map', 'screenshot'], $this->rec['log']);
        $this->assertTrue($this->rec['closed']);
        // DOMContentLoaded : la stabilité (readyState complete, images, polices) est sondée ensuite, bornée
        $this->assertSame(\HeadlessChromium\Page::DOM_CONTENT_LOADED, $this->rec['event']);
        $this->assertSame([390, 844],$this->rec['options']['windowSize']);
        $this->assertStringContainsString('iPhone', $this->rec['options']['userAgent']);
        $this->assertSame('JPEGDATA', $result->image);
        $this->assertSame('image/jpeg', $result->mime);
        $this->assertSame(['jpeg', 80], [$this->rec['shot']['format'], $this->rec['shot']['quality']]);
        $this->assertSame([1920, 4000], [$result->width, $result->height]);
        $this->assertSame('body', $result->elements[0]['selector']);
        $this->assertTrue($result->stable);
        $this->assertSame(200, $result->status);
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

    public function test_http_error_page_is_captured_and_status_returned(): void
    {
        $result = $this->snapshot(['status' => 404])->take('https://shop.test/x', 'desktop');

        $this->assertSame(404, $result->status);
        $this->assertContains('screenshot', $this->rec['log']);
        $this->assertSame('JPEGDATA', $result->image);
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

    public function test_capture_current_captures_the_open_page_without_navigating_or_closing(): void
    {
        $snapshot = $this->snapshot(['status' => 404, 'size' => [1280, 2000]]);
        $page = ($this->factory)([])->createPage();

        $result = $snapshot->captureCurrent($page);

        $this->assertSame(['stable', 'settle', 'status', 'size', 'top', 'map', 'screenshot'], $this->rec['log']);
        $this->assertFalse($this->rec['closed']);
        $this->assertSame('JPEGDATA', $result->image);
        $this->assertSame('image/jpeg', $result->mime);
        $this->assertSame([1280, 2000], [$result->width, $result->height]);
        $this->assertSame(404, $result->status);
        $this->assertSame('body', $result->elements[0]['selector']);
    }

    public function test_take_gives_what_capture_current_gives_after_navigating(): void
    {
        $taken = $this->snapshot()->take('https://shop.test/', 'desktop');

        $snapshot = $this->snapshot();
        $current = $snapshot->captureCurrent(($this->factory)([])->createPage());

        $this->assertEquals($taken, $current);
    }

    public function test_take_bounds_the_map_and_the_screenshot_by_its_default_timeout(): void
    {
        $this->snapshot()->take('https://shop.test/', 'desktop');

        $this->assertSame(['value 20000', 'base64 20000'], $this->rec['timeouts']);
    }

    public function test_capture_current_bounds_the_map_and_the_screenshot_by_its_default_timeout(): void
    {
        $snapshot = $this->snapshot();
        $snapshot->captureCurrent(($this->factory)([])->createPage());

        $this->assertSame(['value 15000', 'base64 15000'], $this->rec['timeouts']);
    }
}
