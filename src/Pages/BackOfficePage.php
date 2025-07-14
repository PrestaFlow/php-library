<?php

namespace PrestaFlow\Library\Pages;

use PrestaFlow\Library\Pages\CommonPage;

class BackOfficePage extends CommonPage
{
    public function __construct(string $locale, string $patchVersion, array $globals)
    {
        $selectors = [
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

    public function getPageURL($page) : string
    {
        $url = $this->getGlobals()['BO']['URL'];
        if (is_string($page)) {
            $url .= match ($page) {
                'login', 'index' => '',
            };
        } else if (is_object($page)) {
            $url .= '/'.$page->url;
        }

        return $url;
    }
}
