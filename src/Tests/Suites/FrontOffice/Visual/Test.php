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
        ->it('compare without masked image', function () use ($frontOfficePage) {
            $score = $frontOfficePage->compare();
            //67.2
            Expect::that($score)->isGreaterThanOrEqualTo(90);
        })
        ->it('compare with masked image', function () use ($frontOfficePage) {
            $score = $frontOfficePage->compareWithMaskedImage();
            Expect::that($score)->isGreaterThanOrEqualTo(90);
        });
    }
}
