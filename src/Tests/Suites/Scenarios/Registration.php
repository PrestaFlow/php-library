<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class Registration extends TestsSuite
{
    protected $groups = ['account'];

    public function init()
    {
        $this
        ->describe('Create a customer account from the FrontOffice')
        ->scenario(\PrestaFlow\Library\Scenarios\Registration::class);
    }
}
