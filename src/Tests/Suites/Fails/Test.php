<?php

namespace PrestaFlow\Library\Tests\Suites\Fails;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Test extends TestsSuite
{
    public function init()
    {
        $this->importPage('FrontOffice\Home');

        extract($this->pages);

        $this
        ->describe('Failed tests')
        ->it('will fail, obviously', function () use ($frontOfficeHomePage) {
            Expect::that(true)->equals(true);
            Expect::that(false)->equals(true);
        });
    }
}
