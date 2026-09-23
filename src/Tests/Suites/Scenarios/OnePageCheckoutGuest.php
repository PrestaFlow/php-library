<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class OnePageCheckoutGuest extends TestsSuite
{
    // The runner's folder argument only takes a directory, so a group is the
    // only way to run the One Page Checkout suites on their own — they target a
    // 9.2 shop, where the other scenario suites do not.
    protected $groups = ['opc'];

    public function init()
    {
        $this
        ->describe('Place an order through the One Page Checkout as a guest')
        ->scenario(\PrestaFlow\Library\Scenarios\OnePageCheckoutGuest::class);
    }
}
