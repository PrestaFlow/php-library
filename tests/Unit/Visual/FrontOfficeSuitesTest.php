<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\Suites\Visual\FrontOfficeClassic;
use PrestaFlow\Library\Tests\Suites\Visual\FrontOfficeHummingbird;
use PrestaFlow\Library\Tests\VisualTestsSuite;

/**
 * URL réellement ouvertes par les suites visuelles livrées (celles que lance
 * .github/workflows/visual.yml).
 *
 * Le panier vide passe par cart?action=show : en 1.7, /cart avec un panier
 * vide redirige vers l'accueil, et la capture « cart-empty » aurait été celle
 * de la home. La query string traverse resolvePath() / resolveUrl() telle quelle.
 */
final class FrontOfficeSuitesTest extends TestCase
{
    /** @return array<string, array{class-string<VisualTestsSuite>}> */
    public static function suites(): array
    {
        return [
            'classic' => [FrontOfficeClassic::class],
            'hummingbird' => [FrontOfficeHummingbird::class],
        ];
    }

    /** @param class-string<VisualTestsSuite> $class */
    private function suite(string $class, bool $prefixLocale): VisualTestsSuite
    {
        $suite = new $class(loadGlobals: false, getBrowser: false);
        $suite->setGlobals([
            'FO' => ['URL' => 'http://localhost:8017/'],
            'PREFIX_LOCALE' => $prefixLocale,
        ]);

        return $suite;
    }

    private function cartUrl(VisualTestsSuite $suite, string $locale): string
    {
        foreach ($suite->checkpoints() as $cp) {
            if ($cp['name'] === 'cart-empty') {
                return $suite->resolveUrl((string) $suite->resolvePath($cp, $locale), $locale);
            }
        }

        $this->fail(sprintf('%s : pas de checkpoint « cart-empty »', $suite::class));
    }

    #[DataProvider('suites')]
    public function test_cart_is_opened_with_action_show_in_both_locales(string $class): void
    {
        $suite = $this->suite($class, prefixLocale: true);

        $this->assertSame('http://localhost:8017/en/cart?action=show', $this->cartUrl($suite, 'en'));
        $this->assertSame('http://localhost:8017/fr/panier?action=show', $this->cartUrl($suite, 'fr'));
    }

    #[DataProvider('suites')]
    public function test_cart_without_locale_prefix_keeps_the_query_string(string $class): void
    {
        $suite = $this->suite($class, prefixLocale: false);

        $this->assertSame('http://localhost:8017/cart?action=show', $this->cartUrl($suite, 'en'));
        $this->assertSame('http://localhost:8017/panier?action=show', $this->cartUrl($suite, 'fr'));
    }
}
