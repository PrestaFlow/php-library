<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice;

use Exception;
use HeadlessChromium\Exception\OperationTimedOut;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Tests\TestsSuite;
use SapientPro\ImageComparator\ImageComparator;


class Page extends CommonPage
{
    public function __construct(string $locale, string $patchVersion)
    {
        $selectors = [
            'pageTitle' => 'h1',
            'maintenanceBlock' => '#content.page-maintenance',
            'desktopLogo' => '#_desktop_logo',
            'userInfoLink' => '#_desktop_user_info',
            'accountLink' => '#_desktop_user_info .user-info a[href*=\'/my-account\']',
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

        parent::__construct($locale, $patchVersion);
    }

    public function goToPage($page = null, $index = null)
    {
        if ($page === null) {
            $page = 'index';
        }

        $url = $this->getPageURL($page, $index);
        TestsSuite::getPage()->close();
        TestsSuite::getBrowser()->createPage();
        $this->getPage()->navigate($url)->waitForNavigation();

        try {
            $bodyContent = $this->getTextContent('body');
            Expect::that($bodyContent, true)->notContains('[Debug] This page has moved');
        } catch (OperationTimedOut | Exception $e) {
            Expect::setWarning('debug-mode');

            $this->click('a');

            $this->waitForNavigation();
        }
    }

    public function getPageURL($page, $index): string
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
                'category' => '{index}-category',
                default => ''
            };
        } else if (is_object($page)) {
            $url .= $page->url;
        }

        if (is_int($index)) {
            $url = str_replace('{index}', $index, $url);
        }

        return $url;
    }

    public function getTitle()
    {
        return $this->getTextContent($this->selector('pageTitle'));
    }

    public function getPageTitle()
    {
        return $this->getTitle();
    }

    public function compare()
    {
        ini_set('memory_limit', '-1');

        $score = 0;

        if (false) {
            sleep(3);
            $fileName = 'reference_' . $this->getPage()->getSession()->getTargetId() . '.jpeg';
            $screenshot = $this->getPage()->screenshot([
                'captureBeyondViewport' => true,
                'clip' => $this->getPage()->getFullPageClip(),
                'format' => 'jpeg',
            ]);
            $screenshot->saveToFile($this->getStoragePath(__DIR__) . '/screens/references/' . $fileName);
        }
        $reference = 'reference_1EF3347A43C8621081BB3865E78241F3.jpeg';
        $actual = 'reference_DE67A27A300C825129F2D5B4BE813202.jpeg';

        if (!file_exists($this->getStoragePath(__DIR__) . '/screens/references/' . $reference) || !file_exists($this->getStoragePath(__DIR__) . '/screens/references/' . $actual)) {
            return 0;
        }

        $imageComparator = new ImageComparator();
        $score = $imageComparator->compare($this->getStoragePath(__DIR__) . '/screens/references/' . $reference, $this->getStoragePath(__DIR__) . '/screens/references/' . $actual);

        return $score;
    }

    public function compareWithMaskedImage()
    {
        ini_set('memory_limit', '-1');

        $reference = 'reference_1EF3347A43C8621081BB3865E78241F3.jpeg';
        $actual = 'reference_DE67A27A300C825129F2D5B4BE813202.jpeg';

        if (!file_exists($this->getStoragePath(__DIR__) . '/screens/references/' . $reference) || !file_exists($this->getStoragePath(__DIR__) . '/screens/references/' . $actual)) {
            return 0;
        }

        $mask = \Image::fromFile($this->getStoragePath(__DIR__) . '/screens/references/' . $reference);

        \Image::fromFile($this->getStoragePath(__DIR__) . '/screens/references/' . $actual)->subtract($mask, 75)->save(time(), $this->getStoragePath(__DIR__) . '/screens/masked/');

        return $this->compare();
    }
}
