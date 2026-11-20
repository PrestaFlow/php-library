<?php

namespace PrestaFlow\Tests\Fixtures\Suites\Fails;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Fails on purpose: checks that a failing run exits non-zero and writes its
 * JUnit failure (bin/prestaflow run --junit=... tests/Fixtures/Suites/Fails).
 * Kept out of src/Tests/Suites, which the app offers to users as examples.
 */
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
