<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Scenarios\Scenario;

/**
 * La commande en une page ne doit jamais laisser la boutique avec un tunnel
 * cassé : sur un thème sans gabarit pour ps_onepagecheckout (Classic, constaté
 * le 21 novembre 2026 sur PrestaShop 9.2.1), la page de commande ne rendait plus
 * que « Hello world! This is HTML5 Boilerplate. » et la boutique restait ainsi.
 */
final class OnePageCheckoutRenderGuardTest extends TestCase
{
    private function front(bool $rendered): object
    {
        return new class ($rendered) {
            public function __construct(private bool $rendered)
            {
            }

            public function isOnePageCheckoutActive(): bool
            {
                return $this->rendered;
            }
        };
    }

    private function back(bool $switchWorks): object
    {
        return new class ($switchWorks) {
            public array $calls = [];
            private bool $fourPage = false;

            public function __construct(private bool $switchWorks)
            {
            }

            public function goTo(int $idShop = 1): void
            {
                $this->calls[] = 'goTo:' . $idShop;
            }

            public function isFourPageCheckoutSelected(): bool
            {
                return $this->fourPage;
            }

            public function switchToFourPageCheckout(): void
            {
                $this->calls[] = 'switchToFourPageCheckout';
                $this->fourPage = $this->switchWorks;
            }
        };
    }

    public function testNothingHappensWhenTheOnePageCheckoutRenders(): void
    {
        $back = $this->back(true);

        Scenario::pfRequireOnePageCheckoutRendered($this->front(true), $back, 1);

        $this->assertSame([], $back->calls);
    }

    public function testRestoresTheFourPageCheckoutThenSkips(): void
    {
        $back = $this->back(true);

        try {
            Scenario::pfRequireOnePageCheckoutRendered($this->front(false), $back, 2);
            $this->fail('the suite must be skipped');
        } catch (SkipTestException $e) {
            $this->assertTrue($e->skipRemaining);
            $this->assertSame(Scenario::ONE_PAGE_CHECKOUT_UNAVAILABLE, $e->getMessage());
        }

        $this->assertSame(['goTo:2', 'switchToFourPageCheckout'], $back->calls);
    }

    public function testFailsLoudlyWhenTheShopCannotBeRestored(): void
    {
        try {
            Scenario::pfRequireOnePageCheckoutRendered($this->front(false), $this->back(false), 1);
            $this->fail('a shop left with a broken checkout must fail the step');
        } catch (SkipTestException $e) {
            $this->fail('a shop left with a broken checkout must not be reported as skipped');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('four-page', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function onePageScenarioProvider(): array
    {
        return [
            'OnePageCheckoutGuest' => ['OnePageCheckoutGuest'],
            'OnePageCheckoutOrder' => ['OnePageCheckoutOrder'],
        ];
    }

    /**
     * @dataProvider onePageScenarioProvider
     */
    public function testScenarioChecksTheFrontAndFillsTheCartBeforeSwitching(string $name): void
    {
        $source = file_get_contents(__DIR__ . '/../../../src/Scenarios/' . $name . '.php');

        $this->assertStringContainsString('pfRequireOnePageCheckoutRendered(', $source);

        // Un saut « aucun produit commandable » doit arriver avant la bascule,
        // jamais entre la bascule et la vérification du front.
        $cart = strpos($source, "->it('add a product to the cart'");
        $switch = strpos($source, "->it('switch the shop to the one page checkout'");
        $this->assertNotFalse($cart);
        $this->assertNotFalse($switch);
        $this->assertLessThan($switch, $cart);
    }
}
