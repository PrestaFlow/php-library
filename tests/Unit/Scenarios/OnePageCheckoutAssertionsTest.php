<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

/**
 * Guards the regression that made a whole run meaningless: a step that only
 * clicks reports success even when nothing happened, because click() returns
 * false on a missing selector instead of raising. A run against a shop with
 * wrong back-office credentials reported four passing steps before the first
 * real assertion failed.
 *
 * Structural rather than behavioural — running these scenarios needs a browser
 * and a live 9.2 shop — but it pins the property that actually broke.
 */
final class OnePageCheckoutAssertionsTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function scenarioProvider(): array
    {
        return [
            'logged-in' => [__DIR__ . '/../../../src/Scenarios/OnePageCheckoutOrder.php'],
            'guest' => [__DIR__ . '/../../../src/Scenarios/OnePageCheckoutGuest.php'],
        ];
    }

    /**
     * @dataProvider scenarioProvider
     */
    public function testEveryStepAssertsSomething(string $scenarioPath): void
    {
        $this->assertFileExists($scenarioPath);

        $source = file_get_contents($scenarioPath);
        // Split on a real step call — `->it('` followed by its title — not on
        // the bare substring, which also occurs in prose comments.
        $chunks = preg_split("/->it\\(\\s*'/", $source);

        // The first chunk is everything before the first step.
        array_shift($chunks);

        $this->assertNotEmpty($chunks, 'the scenario declares no step at all');

        foreach ($chunks as $chunk) {
            // The step's title is the first quoted string of the chunk.
            preg_match('/^([^\']+)\'/', $chunk, $matches);
            $title = $matches[1] ?? '(untitled step)';

            $this->assertStringContainsString(
                'Expect::that',
                $chunk,
                sprintf('step "%s" asserts nothing, so it cannot fail', $title)
            );
        }
    }
}
