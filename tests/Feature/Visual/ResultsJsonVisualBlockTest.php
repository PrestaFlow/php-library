<?php

namespace PrestaFlow\Tests\Feature\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Vérifie que le bloc `visual` (spec MVP2, section « results.json — nouveau
 * bloc ») est bien émis dans le JSON produit par TestsSuite::results(), avec
 * les clés attendues côté action CI / API (actual_relpath, diff_relpath en
 * chemins relatifs au projet).
 */
final class ResultsJsonVisualBlockTest extends TestCase
{
    protected function setUp(): void
    {
        TestsSuite::$visualResults = [];
    }

    protected function tearDown(): void
    {
        TestsSuite::$visualResults = [];
    }

    public function testResultsJsonContainsVisualBlockForFailingCheckpoint(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
        };

        $suite->title = 'Fake visual suite';

        $suite->it('home page visual check', function () {
            // Simule ce que CommonPage::visualCheckpoint() enregistre pour un
            // écart détecté (status=fail), sans dépendance à un vrai navigateur.
            TestsSuite::recordVisualResult([
                'name' => 'home',
                'tag' => 'auto-v9-1280x720-fr',
                'status' => 'fail',
                'score' => 0.72,
                'threshold' => 0.98,
                'reference' => '/tmp/visual-baseline/home--auto-v9-1280x720-fr.png',
                'actual' => '/tmp/prestaflow/screens/actual/home--auto-v9-1280x720-fr.png',
                'diff' => '/tmp/prestaflow/screens/diff/home--auto-v9-1280x720-fr.png',
            ]);
        });

        $suite->run();

        $results = $suite->results(false);
        $json = json_encode($results, JSON_PRETTY_PRINT);
        $decoded = json_decode($json, true);

        $this->assertCount(1, $decoded['tests']);
        $test = $decoded['tests'][0];

        $this->assertArrayHasKey('visual', $test);
        $this->assertCount(1, $test['visual']);

        $visual = $test['visual'][0];
        $this->assertSame('home', $visual['name']);
        $this->assertSame('auto-v9-1280x720-fr', $visual['tag']);
        $this->assertSame('fail', $visual['status']);
        $this->assertSame(0.72, $visual['score']);
        $this->assertSame(0.98, $visual['threshold']);
        $this->assertSame('prestaflow/screens/actual/home--auto-v9-1280x720-fr.png', $visual['actual_relpath']);
        $this->assertSame('prestaflow/screens/diff/home--auto-v9-1280x720-fr.png', $visual['diff_relpath']);
    }

    public function testBaselineStatusOmitsRelpaths(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
        };

        $suite->title = 'Fake visual suite baseline';

        $suite->it('first run creates baseline', function () {
            TestsSuite::recordVisualResult([
                'name' => 'checkout',
                'tag' => 'auto-v9-1280x720-fr',
                'status' => 'baseline',
                'score' => null,
                'threshold' => 0.98,
                'reference' => '/tmp/visual-baseline/checkout--auto-v9-1280x720-fr.png',
                'actual' => '/tmp/prestaflow/screens/actual/checkout--auto-v9-1280x720-fr.png',
                'diff' => null,
            ]);
        });

        $suite->run();

        $results = $suite->results(false);
        $visual = $results['tests'][0]['visual'][0];

        $this->assertSame('baseline', $visual['status']);
        $this->assertNull($visual['score']);
        $this->assertNull($visual['actual_relpath']);
        $this->assertNull($visual['diff_relpath']);
    }

    public function testSuiteScopedNameFlowsIntoVisualBlockAndRelpaths(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
        };

        $suite->title = 'Scoped visual suite';

        $suite->it('capture visuelle : header', function () {
            TestsSuite::recordVisualResult([
                'name' => 'nouvelle-scene.header',
                'tag' => 'auto-v1.7-390x844-fr',
                'status' => 'fail',
                'score' => 0.9,
                'threshold' => 0.98,
                'reference' => '/tmp/visual-baseline/nouvelle-scene.header--auto-v1.7-390x844-fr.png',
                'actual' => '/tmp/prestaflow/screens/actual/nouvelle-scene.header--auto-v1.7-390x844-fr.png',
                'diff' => '/tmp/prestaflow/screens/diff/nouvelle-scene.header--auto-v1.7-390x844-fr.png',
            ]);
        });

        $suite->run();

        $visual = $suite->results(false)['tests'][0]['visual'][0];

        $this->assertSame('nouvelle-scene.header', $visual['name']);
        $this->assertSame('auto-v1.7-390x844-fr', $visual['tag']);
        $this->assertSame('prestaflow/screens/actual/nouvelle-scene.header--auto-v1.7-390x844-fr.png', $visual['actual_relpath']);
        $this->assertSame('prestaflow/screens/diff/nouvelle-scene.header--auto-v1.7-390x844-fr.png', $visual['diff_relpath']);
    }

    public function testVisualBlockCarriesPixelBudgetFields(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
        };
        $suite->title = 'Budget visual suite';

        $suite->it('capture visuelle : login', function () {
            TestsSuite::recordVisualResult([
                'name' => 'login', 'tag' => 'auto-v1.7-1920x1080-fr', 'status' => 'fail',
                'score' => 0.99967, 'threshold' => 1 - 100 / 2073600,
                'changed_pixels' => 686, 'total_pixels' => 2073600, 'max_diff_pixels' => 100,
                'reference' => '/tmp/visual-baseline/login--auto-v1.7-1920x1080-fr.png',
                'actual' => '/tmp/prestaflow/screens/actual/login--auto-v1.7-1920x1080-fr.png',
                'diff' => '/tmp/prestaflow/screens/diff/login--auto-v1.7-1920x1080-fr.png',
            ]);
        });
        $suite->run();

        $visual = json_decode(json_encode($suite->results(false)), true)['tests'][0]['visual'][0];

        $this->assertSame(686, $visual['changed_pixels']);
        $this->assertSame(2073600, $visual['total_pixels']);
        $this->assertSame(100, $visual['max_diff_pixels']);
        $this->assertIsFloat($visual['threshold']);
        $this->assertSame(0.99967, $visual['score']);
    }

    public function testLegacyResultsWithoutPixelFieldsStillSerialise(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
        };
        $suite->it('legacy', function () {
            TestsSuite::recordVisualResult([
                'name' => 'home', 'tag' => null, 'status' => 'pass', 'score' => 1.0, 'threshold' => 0.98,
                'reference' => null, 'actual' => null, 'diff' => null,
            ]);
        });
        $suite->run();

        $visual = $suite->results(false)['tests'][0]['visual'][0];
        $this->assertNull($visual['changed_pixels']);
        $this->assertNull($visual['total_pixels']);
        $this->assertNull($visual['max_diff_pixels']);
        $this->assertSame(0.98, $visual['threshold']);
    }

    public function testVisualBlockCarriesOrigin(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
        };
        $suite->it('capture visuelle : origine', function () {
            TestsSuite::recordVisualResult([
                'name' => 'home', 'tag' => null, 'status' => 'pass', 'score' => 1.0, 'threshold' => 0.98,
                'origin' => [0, 287],
                'reference' => null, 'actual' => null, 'diff' => null,
            ]);
        });
        $suite->run();

        $visual = json_decode(json_encode($suite->results(false)), true)['tests'][0]['visual'][0];
        $this->assertSame([0, 287], $visual['origin']);
    }

    public function testVisualBlockOriginIsNullWhenAbsent(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
        };
        $suite->it('capture visuelle : sans origine', function () {
            TestsSuite::recordVisualResult([
                'name' => 'home', 'tag' => null, 'status' => 'pass', 'score' => 1.0, 'threshold' => 0.98,
                'reference' => null, 'actual' => null, 'diff' => null,
            ]);
        });
        $suite->run();

        $visual = json_decode(json_encode($suite->results(false)), true)['tests'][0]['visual'][0];
        $this->assertArrayHasKey('origin', $visual);
        $this->assertNull($visual['origin']);
    }

    public function testUpdatedFlagIsCarriedIntoVisualBlock(): void
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {
        };

        $suite->title = 'Visual update run';

        $suite->it('capture visuelle : home', function () {
            TestsSuite::recordVisualResult([
                'name' => 'scene.home', 'tag' => 'auto-v9-1280x720-fr', 'status' => 'baseline',
                'score' => null, 'threshold' => 0.99, 'reference' => '/tmp/visual-baseline/a.png',
                'actual' => '/tmp/prestaflow/screens/actual/a.png', 'diff' => null,
                'origin' => [0, 0], 'updated' => true,
            ]);
            TestsSuite::recordVisualResult([
                'name' => 'scene.footer', 'tag' => 'auto-v9-1280x720-fr', 'status' => 'baseline',
                'score' => null, 'threshold' => 0.99, 'reference' => '/tmp/visual-baseline/b.png',
                'actual' => '/tmp/prestaflow/screens/actual/b.png', 'diff' => null,
                'origin' => [0, 0], 'updated' => false,
            ]);
            TestsSuite::recordVisualResult([
                'name' => 'scene.legacy', 'tag' => null, 'status' => 'pass',
                'score' => 1.0, 'threshold' => 0.99, 'reference' => '/tmp/visual-baseline/c.png',
                'actual' => '/tmp/prestaflow/screens/actual/c.png', 'diff' => '/tmp/prestaflow/screens/diff/c.png',
            ]);
        });

        $suite->run();

        $visual = $suite->results(false)['tests'][0]['visual'];

        $this->assertTrue($visual[0]['updated']);
        $this->assertSame('baseline', $visual[0]['status']);
        $this->assertNull($visual[0]['diff_relpath']);
        $this->assertFalse($visual[1]['updated']);
        $this->assertFalse($visual[2]['updated']);
    }
}
