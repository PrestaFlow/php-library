<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class ProductSearch extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Product search: a word of a product\'s name finds that product, on the search page and from the header')
        ->scenario(\PrestaFlow\Library\Scenarios\ProductSearch::class);
    }
}
