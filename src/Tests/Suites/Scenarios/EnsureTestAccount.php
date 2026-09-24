<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class EnsureTestAccount extends TestsSuite
{
    protected $groups = ['account'];

    public function init()
    {
        $this
        ->describe('Provision the known test customer account')
        ->scenario(\PrestaFlow\Library\Scenarios\EnsureTestAccount::class);
    }
}
