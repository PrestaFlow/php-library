<?php

namespace PrestaFlow\Library\Pages;

class CommonPage
{
    public function getPageTitle($page)
    {
        return $page->evaluate('document.title')->getReturnValue();
    }
}
