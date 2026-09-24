<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class SwitchTheme extends TestsSuite
{
    protected $groups = ['theme'];

    public function init()
    {
        $this
        ->describe('Put the shop on a given front-office theme')
        ->scenario(\PrestaFlow\Library\Scenarios\SwitchTheme::class);
    }
}
