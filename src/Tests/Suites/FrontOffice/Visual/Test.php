<?php

namespace PrestaFlow\Library\Tests\Suites\FrontOffice\Visual;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class Test extends TestsSuite
{
    public function init()
    {
        $this->importPage('FrontOffice');

        extract($this->pages);

        $this
        ->describe('Visual tests')
        ->it('should go to home page', function () use ($frontOfficePage) {
            $frontOfficePage->goToPage('home');
        })
        ->it('compare', function () use ($frontOfficePage) {
            $score = $frontOfficePage->compare();
            //67.2
            Expect::that($score)->isGreaterThanOrEqualTo(90);
        })
        ->skip('compare 2', function () use ($frontOfficePage) {
            $score = $frontOfficePage->compare2();
            Expect::that($score)->isGreaterThanOrEqualTo(90);
        });
    }
}
