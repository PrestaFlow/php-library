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
}
