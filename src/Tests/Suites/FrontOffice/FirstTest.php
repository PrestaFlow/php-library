<?php

namespace PrestaFlow\Library\Tests\Suites\FrontOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class FirstTest extends TestsSuite
{
    public function __construct()
    {
        // TEMP
        $globals = [
            'PS_VERSION' => '8.0.4',
            'LOCALE' => 'en',
            'FO' => [
                'URL' => 'https://8.0.4.test',
            ],
            'BO' => [
                'URL' => 'https://8.0.4.test/admin-dev',
                'EMAIL' => '',
                'PASSWD' => '',
            ],
        ];
        // END

        $headless = true;
        $this->before($headless);
        $page = $this->page;

        $loginPage = new \PrestaFlow\Library\Pages\v8\BackOffice\Login\Page;

        $this->describe(
            'Test',
            [
                $this->it('should go to home page', function () use ($page, $globals, $loginPage) {
                    $page->setUserAgent('PrestaFlow');
                    $loginPage->goTo($globals['FO']['URL'], $page);
                    Expect::that($loginPage->getPageTitle($page))->with($page)->contains($loginPage->pageTitle());
                }),
            ]
        );
    }
}
