<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice;

use Exception;
use HeadlessChromium\Exception\OperationTimedOut;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Tests\TestsSuite;
use SapientPro\ImageComparator\ImageComparator;


class BasePage extends CommonPage
{
    public function __construct()
    {
        $selectors = [
            'maintenanceBlock' => '#content.page-maintenance',
            'desktopLogo' => '#_desktop_logo',
            'userInfoLink' => '#_desktop_user_info',
            'accountLink' => '#_desktop_user_info .user-info a[href*=\'/my-account\']',
            'logoutLink' => '#_desktop_user_info .user-info a[href*=\'mylogout\']',
        ];

        $pageSelectors = [];
        if (method_exists($this, 'defineSelectors')) {
            $pageSelectors = $this->defineSelectors();
        }

        $this->selectors = [...$selectors, ...$pageSelectors];

        $messages = [];

        $pageMessages = [];
        if (method_exists($this, 'defineMessages')) {
            $pageMessages = $this->defineMessages();
        }

        $this->messages = [...$messages, ...$pageMessages];

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
            Expect::that($bodyContent, true)->notContains('[Debug] This page has moved')->debug();
        } catch (OperationTimedOut | Exception $e) {
            Expect::setWarning('debug-mode');

            $this->click('a');
        }
    }

    public function getPageURL($page): string
    {
        $url = $this->getGlobals()['FO']['URL'];
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }
        if (is_string($page)) {
            $url .= match ($page) {
                'home', 'index' => '',
                'login', 'authentification', 'connexion' => 'connexion',
                'prices-drop' => 'promotions',
                default => ''
            };
        } else if (is_object($page)) {
            $url .= $page->url;
        }

        return $url;
    }

    public function logout()
    {
        $this->click($this->getSelector('logoutLink'));
        //TestsSuite::getPage()->waitForReload();
    }

    public function compare()
    {
        $score = 0;

        if (false) {
            sleep(3);
            $fileName = 'reference_' . $this->getPage()->getSession()->getTargetId() . '.jpeg';
            $screenshot = $this->getPage()->screenshot([
                'captureBeyondViewport' => true,
                'clip' => $this->getPage()->getFullPageClip(),
                'format' => 'jpeg',
            ]);
            if (function_exists('storage_path')) {
                $screenshot->saveToFile(storage_path() . '/screens/references/' . $fileName);
            } else {
                $screenshot->saveToFile('../../screens/references/' . $fileName);
            }
        }
        $reference = 'reference_2A63960A89AF7BF17D3A8A3C4A5EACF8.png';
        $actual = 'reference_06683FDCAA46193A3D20779F937D4394.jpeg';

        $imageComparator = new ImageComparator();
        $score = $imageComparator->compare(storage_path() . '/screens/references/' . $reference, storage_path() . '/screens/references/' . $actual);

        return $score;
    }

    public function compare2()
    {

        $reference = storage_path() . '/screens/references/' . 'reference_2A63960A89AF7BF17D3A8A3C4A5EACF8.png';
        $actual = storage_path() . '/screens/references/' . 'reference_06683FDCAA46193A3D20779F937D4394.jpeg';

        $mask = \Image::fromFile($reference);

        // Load images
        $image1 = \Image::fromFile($actual)->subtract($mask, 75)->save('masked_image1', storage_path() . '/');
    }
}
