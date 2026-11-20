<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class OrderLifecycle extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Create an order then manage its lifecycle in the BackOffice')
        // French demo shop: friendly URLs in French.
        ->scenario(\PrestaFlow\Library\Scenarios\CheckoutOrder::class, ['locale' => 'fr'])
        ->scenario(\PrestaFlow\Library\Scenarios\ManageOrder::class, ['locale' => 'fr']);
    }
}
