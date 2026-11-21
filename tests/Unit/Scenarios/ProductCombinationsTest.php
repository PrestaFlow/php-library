<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v9\FrontOffice\Cart\Page as CartPage;
use PrestaFlow\Library\Pages\v9\FrontOffice\Product\Page as ProductPage;

final class ProductCombinationsTest extends TestCase
{
    public function test_the_product_is_discovered_by_default(): void
    {
        $params = (new \ReflectionClass(\PrestaFlow\Library\Scenarios\ProductCombinations::class))->getDefaultProperties()['params'];

        $this->assertNull($params['locale']);
        $this->assertNull($params['productUrl']);
    }

    public function test_the_skip_reason_is_an_english_sentence(): void
    {
        $this->assertSame(
            'No product with combinations that can be added to the cart was found among the first products of this shop.',
            ProductPage::NO_PRODUCT_WITH_COMBINATIONS
        );
    }

    public function test_the_pages_offer_the_combination_methods_on_every_version(): void
    {
        foreach (['v7', 'v8', 'v9'] as $version) {
            $product = "PrestaFlow\\Library\\Pages\\{$version}\\FrontOffice\\Product\\Page";
            $cart = "PrestaFlow\\Library\\Pages\\{$version}\\FrontOffice\\Cart\\Page";

            $this->assertTrue(is_a($product, ProductPage::class, true), $product);
            $this->assertTrue(is_a($cart, CartPage::class, true), $cart);
        }

        foreach (['goToProductWithCombinations', 'findProductWithCombinations', 'hasCombinations', 'selectOtherCombination',
            'getProductId', 'getCombinationId', 'getCombinationValues'] as $method) {
            $this->assertTrue(method_exists(ProductPage::class, $method), $method);
        }
        foreach (['findLineOfCombination', 'getLineCombinationValues', 'removeLine'] as $method) {
            $this->assertTrue(method_exists(CartPage::class, $method), $method);
        }
    }
}
