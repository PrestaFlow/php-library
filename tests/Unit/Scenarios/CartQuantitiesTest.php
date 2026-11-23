<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class CartQuantitiesTest extends TestCase
{
    public function test_the_product_is_discovered_by_default(): void
    {
        $params = (new \ReflectionClass(\PrestaFlow\Library\Scenarios\CartQuantities::class))->getDefaultProperties()['params'];

        $this->assertNull($params['locale']);
        $this->assertNull($params['productUrl']);
    }

    public function test_parse_price_is_on_every_front_office_page(): void
    {
        $this->assertTrue(method_exists(\PrestaFlow\Library\Pages\v9\FrontOffice\Cart\Page::class, 'parsePrice'));
        $this->assertTrue(method_exists(\PrestaFlow\Library\Pages\v9\FrontOffice\Product\Page::class, 'parsePrice'));
    }
}
