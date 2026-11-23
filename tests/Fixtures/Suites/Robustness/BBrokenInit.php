<?php

namespace PrestaFlow\Tests\Fixtures\Suites\Robustness;

use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Its init() calls a class that does not exist, as a suite does when it uses
 * PrestaShop classes on the runner: it must fail alone (ExecuteSuiteRobustnessTest).
 */
class BBrokenInit extends TestsSuite
{
    public function __construct()
    {
        parent::__construct(loadGlobals: true, getBrowser: false);
    }

    public function init()
    {
        \PrestaFlowFixtureMissingClass::get('PS_SHOP_DOMAIN');
    }
}
