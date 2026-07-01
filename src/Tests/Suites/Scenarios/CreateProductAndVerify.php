<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class CreateProductAndVerify extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Create a product in the BackOffice and verify it in the FrontOffice')
        ->scenario(\PrestaFlow\Library\Scenarios\CreateProductAndVerify::class);
    }
}
