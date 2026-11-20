<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class CartQuantities extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Cart quantities: raise, lower and remove a product, the totals follow')
        ->scenario(\PrestaFlow\Library\Scenarios\CartQuantities::class);
    }
}
