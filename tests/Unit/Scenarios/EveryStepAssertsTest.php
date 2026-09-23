<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

/**
 * Guards the regression that made a whole run meaningless: a step that only
 * clicks reports success even when nothing happened, because click() returns
 * false on a missing selector instead of raising. A run against a shop with
 * wrong back-office credentials reported four passing steps before the first
 * real assertion failed. Another reported a passing checkout step while the
 * browser had never left the address step.
 *
 * Structural rather than behavioural — running these scenarios needs a browser
 * and a live shop — but it pins the property that actually broke, for every
 * scenario in src/Scenarios/, so a new one can't ship mute steps unnoticed.
 */
final class EveryStepAssertsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function scenarioProvider(): array
    {
        $cases = [];

        foreach (glob(__DIR__ . '/../../../src/Scenarios/*.php') as $path) {
            $name = basename($path, '.php');

            if ($name === 'Scenario') {
                continue;
            }

            $cases[$name] = [$name, $path];
        }

        ksort($cases);

        return $cases;
    }

    /**
     * @dataProvider scenarioProvider
     */
    public function testEveryStepAssertsSomething(string $name, string $path): void
    {
        $this->assertFileExists($path);

        // Split on a real step call — `->it('` followed by its title — not on
        // the bare substring, which also occurs in prose comments.
        $chunks = preg_split("/->it\\(\\s*'/", file_get_contents($path));

        // The first chunk is everything before the first step.
        array_shift($chunks);

        $this->assertNotEmpty($chunks, $name . ' declares no step at all');

        foreach ($chunks as $chunk) {
            // The step's title is the first quoted string of the chunk.
            preg_match('/^([^\']+)\'/', $chunk, $matches);
            $title = $matches[1] ?? '(untitled step)';

            $this->assertStringContainsString(
                'Expect::that',
                $chunk,
                sprintf('step "%s" in %s asserts nothing, so it cannot fail', $title, $name)
            );
        }
    }
}
