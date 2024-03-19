<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice;

use Exception;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Tests\TestsSuite;

class BasePage extends CommonPage
{
    public function __construct()
    {
        $selectors = [
            'maintenanceBlock' => '#content.page-maintenance',
            'desktopLogo' => '#_desktop_logo',
            'userInfoLink' => '#_desktop_user_info',
            'accountLink' => '#_desktop_user_info .user-info a[href*="/my-account"]',
            'logoutLink' => '#_desktop_user_info .user-info a[href*="/?mylogout="]',
        ];

        $pageSelectors = [];
        if (method_exists($this, 'defineSelectors')) {
            $pageSelectors = $this->defineSelectors();
        }

        $this->selectors = [...$selectors, ...$pageSelectors];

        parent::__construct();
    }

    public function goToPage($page = null)
    {
        if ($page === null) {
            $page = 'index';
        }

        $url = $this->getPageURL($page);
        TestsSuite::getPage()->close();
        TestsSuite::getBrowser()->createPage();
        $this->getPage()->navigate($url)->waitForNavigation();

        try {
            $bodyContent = $this->getTextContent('body');
            Expect::that($bodyContent)->notContains('[Debug] This page has moved');
        } catch (Exception $e) {
            Expect::setWarning('debug-mode');

            $this->click('a');
        }
    }

    public function getPageURL($page) : string
    {
        $url = $this->getGlobals()['FO']['URL'];
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }
        if (is_string($page)) {
            $url .= match ($page) {
                'home', 'index' => '',
                'login', 'authentification', 'connexion' => 'connexion',
                default => ''
            };
        } else if (is_object($page)) {
            $url .= $page->url;
        }

        return $url;
    }
}
