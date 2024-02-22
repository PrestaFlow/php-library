<?php

namespace PrestaFlow\Library\Pages;

class CommonPage
{
    public string $pageTitle = '';

    public function pageTitle()
    {
        return $this->pageTitle;
    }

    public function getPageTitle($page)
    {
        return $page->evaluate('document.title')->getReturnValue();
    }

    public  function goTo(string $url, $page)
    {
        $page->navigate($url)->waitForNavigation();
    }

    public function getTextContent($page, $selector, $waitForSelector = true, $timeout = 10000)
    {
        if ($waitForSelector) {
            $page->waitUntilContainsElement($selector);
        }
        $element = $page->dom()->querySelector($selector);
        return trim($element->getText());
    }

    public function click($page, $selector)
    {
        $page->mouse()->find($selector)->click();
    }

    /**
     * Delete the existing text then type new value on input
     */
    public function setValue($page, $selector, $value)
    {
        $this->click($page, $selector);

        // Delete text from input before typing
        $page->keyboard()->typeRawKey('Del');
        $page->keyboard()->typeText($value);
    }
}
