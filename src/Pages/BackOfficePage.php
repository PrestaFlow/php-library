<?php

namespace PrestaFlow\Library\Pages;

use PrestaFlow\Library\Pages\CommonPage;

class BackOfficePage extends CommonPage
{
    public function __construct(string $locale, string $patchVersion, array $globals)
    {
        $this->globals = $globals;

        $selectors = [
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

    public function goToPage($page = null)
    {
        if ($page === null) {
            $page = 'login';
        }

        $url = $this->getPageURL($page);
        $this->getPage()->navigate($url)->waitForNavigation();
    }

    public function getPageURL($page, $index = null): string
    {
        $url = $this->getGlobals()['BO']['URL'];
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }

        if (is_string($page)) {
            $pageUrl = $this->url($page);
            if ($pageUrl !== '' && $pageUrl !== null) {
                $url .= $pageUrl;
            } else {
                $url .= match ($page) {
                    'login', 'index' => '',
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
}
