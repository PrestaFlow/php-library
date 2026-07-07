<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class EditProduct extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Create a product, edit its price from the list, and verify')
        ->scenario(\PrestaFlow\Library\Scenarios\EditProduct::class);
    }
}
