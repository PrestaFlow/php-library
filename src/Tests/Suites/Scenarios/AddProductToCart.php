<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class AddProductToCart extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Open a category, open one of its products and add it to the cart')
        ->scenario(\PrestaFlow\Library\Scenarios\AddProductToCart::class);
    }
}
