<?php

namespace PrestaFlow\Tests\Fixtures\Suites\RobustnessExit;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Browser-free suite that passes, run next to a broken one: its result must
 * survive the other suite's crash (ExecuteSuiteRobustnessTest).
 */
class APasses extends TestsSuite
{
    public function __construct()
    {
        parent::__construct(loadGlobals: true, getBrowser: false);
    }

    public function init()
    {
        $this
            ->describe('Passes')
            ->it('passes', function () {
                Expect::that(true)->equals(true);
            });
    }
}
