<?php

namespace PrestaFlow\Library\Pages\v8\BackOffice;

use PrestaFlow\Library\Pages\CommonPage;

class Page extends CommonPage
{
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
