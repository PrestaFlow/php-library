<?php

namespace PrestaFlow\Library\Pages;

use Exception;
use HeadlessChromium\Exception\OperationTimedOut;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Resolvers\Translations;
use PrestaFlow\Library\Resolvers\Urls;
use PrestaFlow\Library\Tests\TestsSuite;
use SapientPro\ImageComparator\ImageComparator;

class FrontOfficePage extends CommonPage
{
    use Translations;
    use Urls;

    public function __construct(string $locale, string $patchVersion, array $globals)
    {
        $this->globals = $globals;

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

        $baseSelectors = [...$selectors, ...$pageSelectors];

        $customPath = __DIR__.'/../../../../../Tests/Selectors/';

        $fileName = $this->getLocale().'.json';

        $customSelectors = [];
        $pathToCatalog = $customPath.$fileName;
        if (file_exists($pathToCatalog)) {
            $customSelectors = json_decode(file_get_contents($pathToCatalog), true);

            if (count($customSelectors)) {
                $pageName = str_replace('PrestaFlow\\Library\\Pages\\v'.$this->getMajorVersion(namespace: true).'\\', '', get_class($this));
                $pageNames = explode('\\', $pageName);

                foreach ($pageNames as $pageName) {
                    if ($pageName !== 'Page') {
                        if (isset($customSelectors[$pageName])) {
                            $customSelectors = $customSelectors[$pageName];
                        } else {
                            $customSelectors = [];
                        }
                    }
                }
            }
        }

        $mergedSelectors = [
            ...$baseSelectors,
            ...$customSelectors,
        ];

        $this->selectors = $mergedSelectors;

        $messages = [];

        $pageMessages = [];
        if (method_exists($this, 'defineMessages')) {
            $pageMessages = $this->defineMessages();
        }

        $baseMessages = [...$messages, ...$pageMessages];

        $customPath = __DIR__.'/../../../../../Tests/Messages/';
        $fileName = $this->getLocale().'.json';

        $customMessages = [];
        $pathToCatalog = $customPath.$fileName;
        if (file_exists($pathToCatalog)) {
            $customMessages = json_decode(file_get_contents($pathToCatalog), true);

            if (count($customMessages)) {
                $pageName = str_replace('PrestaFlow\\Library\\Pages\\v'.$this->getMajorVersion(namespace: true).'\\', '', get_class($this));
                $pageNames = explode('\\', $pageName);

                foreach ($pageNames as $pageName) {
                    if ($pageName !== 'Page') {
                        if (isset($customMessages[$pageName])) {
                            $customMessages = $customMessages[$pageName];
                        } else {
                            $customMessages = [];
                        }
                    }
                }
            }
        }

        $mergedMessages = [
            ...$baseMessages,
            ...$customMessages,
        ];

        $this->messages = $mergedMessages;

        parent::__construct(locale: $locale, patchVersion: $patchVersion, globals: $globals);
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

    public function getPageURL($page, $index = null): string
    {
        $url = $this->getGlobals()['FO']['URL'];
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }
        $useIsoCode = $this->getGlobals()['PREFIX_LOCALE'] ?? false;
        if ($useIsoCode) {
            $url .= $this->getLocale() . '/';
        }
        if (is_string($page)) {
            $pageUrl = $this->url($page);
            if ($pageUrl !== '' && $pageUrl !== null) {
                $url .= $pageUrl;
            } else {
                $url .= match ($page) {
                    'home', 'index' => '',
                    'login', 'authentification' => 'login',
                    'prices-drop' => 'prices-drop',
                    'category' => '{index}-category',
                    'product' => '{index}-product.html',
                    default => ''
                };
            }
        } else if (is_object($page)) {
            $pageUrl = $this->url($page->url);
            if ($pageUrl !== '' && $pageUrl !== null) {
                $url .= $pageUrl;
            } else {
                $url .= $page->url;
            }
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
