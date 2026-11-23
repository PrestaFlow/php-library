<?php

namespace PrestaFlow\Tests\Fixtures\Suites\Robustness;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Runs after the broken suite: the run must go on (ExecuteSuiteRobustnessTest).
 */
class CAlsoPasses extends TestsSuite
{
    public function __construct()
    {
        parent::__construct(loadGlobals: true, getBrowser: false);
    }

    public function init()
    {
        $this
            ->describe('Also passes')
            ->it('also passes', function () {
                Expect::that(1)->equals(1);
            });
    }
}
