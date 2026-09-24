<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

/**
 * `#js-product-list-header` is a CONTAINER, not a heading. Classic puts the
 * category description in it; hummingbird additionally puts the subcategory
 * nav in it, so reading it returned "Home Clothes Accessories Art" — a value
 * that satisfies any non-emptiness check while naming nothing.
 *
 * Measured on live shops, both listings, all four combinations:
 *
 *   port/theme      #js-product-list-header            h1 inside it
 *   8017 classic    "Home"                             "Home"
 *   8082 classic    "Home"                             "Home"
 *   8093 classic    "Home"                             "Home"
 *   8092 humming.   "Home Clothes Accessories Art"     "Home"
 *   (category 3)    "Clothes Discover our favori..."   "Clothes"
 */
final class ListingTitleSelectorTest extends TestCase
{
    private const HEADING = '#js-product-list-header h1';

    private function fakeGlobals(string $theme = 'classic'): array
    {
        return [
            'PS_VERSION' => '9.2.0',
            'LOCALE' => 'en',
            'PREFIX_LOCALE' => false,
            'THEME' => $theme,
            'BO' => ['URL' => 'http://localhost/admin-dev/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'DEBUG' => false,
            'VERBOSE' => false,
        ];
    }

    private function make(string $version, string $name, string $theme = 'classic'): object
    {
        $class = 'PrestaFlow\\Library\\Pages\\' . $version . '\\FrontOffice\\' . $name . '\\Page';

        return new $class(
            locale: 'en',
            patchVersion: '9.2.0',
            globals: $this->fakeGlobals($theme),
            customs: []
        );
    }

    public function testListingTitleTargetsTheHeadingNotTheHeaderContainer(): void
    {
        $this->assertSame(self::HEADING, $this->make('v9', 'Listing')->getSelector('pageTitle'));
    }

    public function testCategoryAgreesWithListingOnEveryVersion(): void
    {
        foreach (['v7', 'v8', 'v9'] as $version) {
            $this->assertSame(
                self::HEADING,
                $this->make($version, 'Listing')->getSelector('pageTitle'),
                $version . ' Listing'
            );
            $this->assertSame(
                self::HEADING,
                $this->make($version, 'Category')->getSelector('pageTitle'),
                $version . ' Category'
            );
        }
    }

    /**
     * hummingbird is the theme where the container reading was worst, and it
     * overrides neither page's heading: the fix has to hold through the theme
     * layer, not only in the base map.
     */
    public function testTheHeadingSurvivesTheHummingbirdThemeLayer(): void
    {
        $this->assertSame(self::HEADING, $this->make('v9', 'Listing', 'hummingbird')->getSelector('pageTitle'));
        $this->assertSame(self::HEADING, $this->make('v9', 'Category', 'hummingbird')->getSelector('pageTitle'));
    }

    /**
     * Category used to carry a second copy of this selector because Listing's
     * was wrong. One source now, so the two cannot drift apart.
     */
    public function testCategoryNoLongerRedeclaresTheHeading(): void
    {
        $method = new \ReflectionMethod(
            'PrestaFlow\\Library\\Pages\\v9\\FrontOffice\\Category\\Page',
            'defineSelectors'
        );

        $this->assertSame(
            'PrestaFlow\\Library\\Pages\\v9\\FrontOffice\\Listing\\Page',
            $method->getDeclaringClass()->getName()
        );
    }
}
