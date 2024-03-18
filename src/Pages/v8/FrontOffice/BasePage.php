<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice;

use Exception;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Pages\CommonPage;

class BasePage extends CommonPage
{
    public function goToPage($page = null)
    {
        if ($page === null) {
            $page = 'index';
        }

        $url = $this->getPageURL($page);
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
        if (is_string($page)) {
            $url .= match ($page) {
                'home', 'index' => '',
            };
        } else if (is_object($page)) {
            $url .= '/'.$page->url;
        }

        return $url;
    }
}
