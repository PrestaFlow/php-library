<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v9\FrontOffice\Category\Page as CategoryPage;
use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as ListingPage;

/**
 * The listing's product link must stay reachable from the theme layer.
 *
 * goToProduct() used to append ' .product-title a' in PHP, so the anchor part
 * of the selector never passed through the theme merge: hummingbird could
 * override productArticle and still click nothing, because it renders
 * a.product-miniature__title instead.
 */
final class ListingProductLinkTest extends TestCase
{
    private function globals(string $theme): array
    {
        return [
            'PS_VERSION' => '9.0.0',
            'LOCALE' => 'en',
            'PREFIX_LOCALE' => false,
            'THEME' => $theme,
            'BO' => ['URL' => 'http://localhost/admin/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'DEBUG' => false,
            'VERBOSE' => false,
        ];
    }

    private function page(string $theme): ListingPage
    {
        return new ListingPage(locale: 'en', patchVersion: '9.0.0', globals: $this->globals($theme), customs: []);
    }

    private function categoryPage(string $theme): CategoryPage
    {
        return new CategoryPage(locale: 'en', patchVersion: '9.0.0', globals: $this->globals($theme), customs: []);
    }

    public function testClassicLinkSelectorCarriesTheAnchor(): void
    {
        $selector = $this->page('classic')->selector('productArticleLink', ['index' => 2]);

        $this->assertSame(
            '#js-product-list .products div:nth-child(2) article .product-title a',
            $selector
        );
    }

    public function testHummingbirdOverridesTheWholeLinkSelector(): void
    {
        $selector = $this->page('hummingbird')->selector('productArticleLink', ['index' => 3]);

        $this->assertSame(
            '#js-product-list .products article:nth-child(3) a.product-miniature__title',
            $selector
        );
        $this->assertStringNotContainsString('.product-title', $selector);
    }

    public function testProductArticleIsUnchangedOnBothThemes(): void
    {
        $this->assertSame(
            '#js-product-list .products div:nth-child(1) article',
            $this->page('classic')->selector('productArticle', ['index' => 1])
        );
        $this->assertSame(
            '#js-product-list .products article:nth-child(1)',
            $this->page('hummingbird')->selector('productArticle', ['index' => 1])
        );
    }

    /**
     * Category extends Listing, but the theme merge keys off the concrete page
     * name, so a FrontOffice.Listing override never reaches FrontOffice.Category.
     * AddProductToCart drives the Category page, which is the path that was
     * actually broken on hummingbird.
     */
    public function testCategoryInheritsTheLinkFixOnHummingbird(): void
    {
        $this->assertSame(
            '#js-product-list .products article:nth-child(1) a.product-miniature__title',
            $this->categoryPage('hummingbird')->selector('productArticleLink', ['index' => 1])
        );
    }

    public function testCategoryKeepsTheClassicSelectorOnClassic(): void
    {
        $this->assertSame(
            '#js-product-list .products div:nth-child(1) article .product-title a',
            $this->categoryPage('classic')->selector('productArticleLink', ['index' => 1])
        );
    }
}
