<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class GuestCheckout extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Place an order as a guest and reach the FrontOffice confirmation')
        ->scenario(\PrestaFlow\Library\Scenarios\GuestCheckout::class);
    }
}
