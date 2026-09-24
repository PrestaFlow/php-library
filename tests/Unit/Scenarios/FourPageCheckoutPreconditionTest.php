<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

/**
 * Guards the defect that made the four-page tunnel look broken: the checkout
 * layout is a shop-wide setting, the One Page Checkout scenarios switch it on
 * and deliberately never switch it back, and nothing made the four-page
 * scenarios state that they need it off.
 *
 * The failure was silent in the usual way — the One Page Checkout renders its
 * own markup, FrontOffice\Checkout's selectors stopped matching, and click()
 * returns false on a missing selector instead of raising — so the scenario
 * spent a whole run filling a form that was no longer the one on screen.
 *
 * Structural rather than behavioural: driving these scenarios needs a browser
 * and a live shop. It pins the property that actually broke, so a new scenario
 * cannot walk the four-page tunnel without first setting the layout it needs.
 */
final class FourPageCheckoutPreconditionTest extends TestCase
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
    public function testScenarioDrivingTheFourPageTunnelSetsTheLayout(string $name, string $path): void
    {
        $source = file_get_contents($path);

        // The four-page tunnel is reached through FrontOffice\Checkout. The One
        // Page Checkout scenarios use FrontOffice\OnePageCheckout instead, so
        // they are not matched here and keep switching the layout the other way.
        $importsFourPageTunnel = str_contains($source, "importPage('FrontOffice\\Checkout')");

        if (!$importsFourPageTunnel) {
            $this->assertTrue(true, $name . ' does not drive the four-page tunnel.');

            return;
        }

        $this->assertStringContainsString(
            'requireFourPageCheckout($testSuite',
            $source,
            $name . " imports FrontOffice\\Checkout but never calls requireFourPageCheckout(),"
                . " so it walks the four-page tunnel on whatever layout the previous scenario left behind."
        );
    }

    public function testTheSharedPreconditionAssertsItsEndState(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../src/Scenarios/Scenario.php');

        // The precondition branches: it only clicks when the shop is on the
        // wrong layout. The assertion must therefore sit outside the branch, or
        // a run that took the "already correct" path would prove nothing.
        $this->assertStringContainsString(
            'isFourPageCheckoutSelected()',
            $source,
            'requireFourPageCheckout() must assert the layout it claims to have set.'
        );
    }
}
