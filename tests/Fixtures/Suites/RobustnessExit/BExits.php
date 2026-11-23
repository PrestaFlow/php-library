<?php

namespace PrestaFlow\Tests\Fixtures\Suites\RobustnessExit;

use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Calls exit during init(), as a PrestaShop source file does without
 * _PS_VERSION_: results.json must still be written (ExecuteSuiteRobustnessTest).
 */
class BExits extends TestsSuite
{
    public function __construct()
    {
        parent::__construct(loadGlobals: true, getBrowser: false);
    }

    public function init()
    {
        exit;
    }
}
