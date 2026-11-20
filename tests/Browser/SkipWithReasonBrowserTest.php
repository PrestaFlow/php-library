<?php

namespace PrestaFlow\Tests\Browser;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * skipWithReason() through a real run(): states, counters, the reason shown
 * under the test, and skipRemaining on the tests that follow. A browser test
 * only because run() opens the page it records console errors on.
 */
final class SkipWithReasonBrowserTest extends TestCase
{
    protected function setUp(): void
    {
        TestsSuite::scopeBrowserFilesTo('pf-skip-with-reason-' . getmypid());
    }

    protected function tearDown(): void
    {
        TestsSuite::resetBrowser();
        TestsSuite::scopeBrowserFilesTo(null);
    }

    public function test_skips_carry_their_reason_and_skip_remaining_stops_the_suite(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
            public function init()
            {
                parent::init();

                $this
                ->describe('skipWithReason')
                ->it('runs', function () {
                    Expect::that(1)->equals(1);
                })
                ->it('skips alone', function () {
                    $this->skipWithReason('No wishlist module');
                })
                ->it('still runs after a plain skip', function () {
                    Expect::that(1)->equals(1);
                })
                ->it('skips the rest', function () {
                    $this->skipWithReason('No offline payment method', skipRemaining: true);
                })
                ->it('would fail', function () {
                    Expect::that(1)->equals(2);
                })
                ->it('would pass', function () {
                    Expect::that(1)->equals(1);
                });

                return $this;
            }
        };

        try {
            $suite->run();
        } catch (\Throwable $e) {
            if (getenv('CI') === false || getenv('CI') === '') {
                $this->markTestSkipped('Chrome indisponible : ' . $e->getMessage());
            }
            throw $e;
        }

        $results = $suite->results(false);
        $tests = $results['tests'];

        $this->assertSame(['pass', 'skip', 'pass', 'skip', 'skip', 'skip'], array_column($tests, 'state'));
        $this->assertSame(['No wishlist module'], $tests[1]['expect']['skip']);
        $this->assertSame(['No offline payment method'], $tests[3]['expect']['skip']);
        $this->assertSame(['No offline payment method'], $tests[4]['expect']['skip']);
        $this->assertSame(['No offline payment method'], $tests[5]['expect']['skip']);

        $this->assertSame(2, $results['stats']['passes']);
        $this->assertSame(4, $results['stats']['skips']);
        $this->assertSame(0, $results['stats']['failures']);
        $this->assertSame(0, $results['stats']['skippeds']);
    }
}
