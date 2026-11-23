<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class AddressBook extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Address book: add an address, change its city, delete it, other addresses untouched')
        ->scenario(\PrestaFlow\Library\Scenarios\AddressBook::class);
    }
}
