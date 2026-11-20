<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class CustomerOrderHistory extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Order history: the order is listed, its detail opens and its invoice PDF downloads')
        ->scenario(\PrestaFlow\Library\Scenarios\CustomerOrderHistory::class);
    }
}
