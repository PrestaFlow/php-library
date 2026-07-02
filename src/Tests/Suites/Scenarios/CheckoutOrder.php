<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class CheckoutOrder extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Log in, add to cart, checkout, and verify the order in the BackOffice')
        ->scenario(\PrestaFlow\Library\Scenarios\CheckoutOrder::class);
    }
}
