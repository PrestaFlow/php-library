<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class OutOfStockProduct extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('A product without stock cannot be added to the cart (created and deleted in the BackOffice)')
        ->scenario(\PrestaFlow\Library\Scenarios\OutOfStockProduct::class);
    }
}
