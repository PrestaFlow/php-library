<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class OrderLifecycle extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Create an order then manage its lifecycle in the BackOffice')
        ->scenario(\PrestaFlow\Library\Scenarios\CheckoutOrder::class)
        ->scenario(\PrestaFlow\Library\Scenarios\ManageOrder::class);
    }
}
