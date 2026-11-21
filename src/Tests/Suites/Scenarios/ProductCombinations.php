<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class ProductCombinations extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Product combinations: choose another combination of a product, add it to the cart and find it on the cart line')
        ->scenario(\PrestaFlow\Library\Scenarios\ProductCombinations::class);
    }
}
