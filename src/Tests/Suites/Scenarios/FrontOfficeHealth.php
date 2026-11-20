<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class FrontOfficeHealth extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Front office health: home, category, product, cart and order page, without errors')
        ->scenario(\PrestaFlow\Library\Scenarios\FrontOfficeHealth::class);
    }
}
