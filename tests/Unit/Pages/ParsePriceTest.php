<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

/**
 * Guards a defect that survived because nothing read the number back: the old
 * implementation answered 0.0 on hummingbird's markup and mangled every price
 * above a thousand, on every theme.
 */
final class ParsePriceTest extends TestCase
{
    private function page(): object
    {
        // The page constructor wants a locale, globals and a browser; none of
        // that is involved in parsing a string.
        return (new \ReflectionClass('PrestaFlow\\Library\\Pages\\v9\\FrontOffice\\Product\\Page'))
            ->newInstanceWithoutConstructor();
    }

    /**
     * @return array<string, array{0: string, 1: float}>
     */
    public static function priceProvider(): array
    {
        return [
            // The reported bug: hummingbird renders a visually-hidden label
            // inside the price element, and getText() returns it.
            'hummingbird visually-hidden label' => ['Price: €14.28', 14.28],
            'plain euro prefix' => ['€14.28', 14.28],
            'euro suffix, comma decimal' => ['14,28 €', 14.28],
            // These two were already wrong before hummingbird existed.
            'french grouping with a space' => ['1 234,56 €', 1234.56],
            'english grouping with a comma' => ['$1,234.56', 1234.56],
            'narrow no-break space grouping' => ["1\u{202F}234,56 €", 1234.56],
            'no decimals' => ['€1234', 1234.0],
            // The one genuinely ambiguous case, resolved by the three-digit rule.
            'dot grouping without decimals' => ['1.234 €', 1234.0],
            'label and grouping together' => ['Price: $1,234.56', 1234.56],
            'trailing separator' => ['14.28.', 14.28],
            'no digits at all' => ['Out of stock', 0.0],
            'empty string' => ['', 0.0],
        ];
    }

    /**
     * @dataProvider priceProvider
     */
    public function testParsePrice(string $rendered, float $expected): void
    {
        $this->assertSame($expected, $this->page()->parsePrice($rendered));
    }
}
