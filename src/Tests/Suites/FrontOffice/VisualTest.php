<?php

namespace PrestaFlow\Library\Tests\Suites\FrontOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class VisualTest extends TestsSuite
{
    public function __construct()
    {
        $globals = [
            'PS_VERSION' => '8.0.4',
            'LOCALE' => 'en',
            'FO' => [
                'URL' => 'https://8.0.4.test',
            ],
        ];

        $headless = true;
        $this->before($headless);

        $basePage = new \PrestaFlow\Library\Pages\v8\FrontOffice\BasePage();
        $basePage->setGlobals($globals);

        $this
        ->describe('Visual tests')
        ->it('should go to home page', function () use ($basePage) {
            $basePage->goToPage('home');
        })
        ->it('compare', function () use ($basePage) {
            $score = $basePage->compare();
            //67.2
            Expect::that($score)->isGreaterThanOrEqualTo(90);
        })
        ->skip('compare 2', function () use ($basePage) {
            $score = $basePage->compare2();
            Expect::that($score)->isGreaterThanOrEqualTo(90);
        });
    }
}
