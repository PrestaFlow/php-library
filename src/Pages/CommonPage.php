<?php

namespace PrestaFlow\Library\Pages;

use HeadlessChromium\Exception\ElementNotFoundException;
use HeadlessChromium\Exception\OperationTimedOut;

class CommonPage
{
    protected $page;
    protected $globals;
    public $selectors = [];

    public string $url = '';
    public string $pageTitle = '';

    public function __construct($page = null)
    {
        if ($page !== null) {
            $this->page = $page;
        }

        return $this;
    }

    public function selector($selector)
    {
        return $this->getSelector($selector);
    }

    public function getSelector($selector)
    {
        if (isset($this->selectors[$selector])) {
            return $this->selectors[$selector];
        }

        return null;
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->getPage(), $name)) {
            call_user_func_array([$this->getPage(), $name], $arguments);
        }
    }

    public function setGlobals($globals)
    {
        $this->globals = $globals;
    }

    public function getGlobals() : array
    {
        return $this->globals;
    }

    public function getGlobal($index)
    {
        $globals = $this->getGlobals();

        if (!str_contains($index, '_')) {
            if (isset($globals[$index])) {
                return $globals[$index];
            }
        } else {
            $indexes = explode('_', $index);
            if (is_array($indexes) && count($indexes)) {
                foreach ($indexes as $_index) {
                    if (isset($globals[$_index])) {
                        $globals = $globals[$_index];
                    }
                }
                return $globals;
            }
        }

        return null;
    }

    public function setPage($page)
    {
        $this->page = $page;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function pageTitle()
    {
        return $this->pageTitle;
    }

    public function getPageTitle()
    {
        return $this->getPage()->evaluate('document.title')->getReturnValue();
    }

    public function goTo(string $url)
    {
        $this->getPage()->navigate($url)->waitForNavigation();
    }

    public function getTextContent($selector, $waitForSelector = true, $timeout = 10000)
    {
        if ($waitForSelector) {
            $this->getPage()->waitUntilContainsElement($selector);
        }
        $element = $this->getPage()->dom()->querySelector($selector);
        return trim($element->getText());
    }

    public function click($selector)
    {
        $this->getPage()->mouse()->find($selector)->click();
    }

    /**
     * Delete the existing text then type new value on input
     */
    public function setValue($selector, $value)
    {
        $this->click($selector);

        // Delete text from input before typing
        $this->getPage()->keyboard()->typeRawKey('Del');
        $this->getPage()->keyboard()->typeText($value);
    }

    public function elementIsVisible($selector, $timeout)
    {
        try {
            $this->getPage()->waitUntilContainsElement($selector, $timeout);
        } catch (OperationTimedOut $exception) {
            return false;
        } catch (ElementNotFoundException $exception) {
            return false;
        }

        return true;
    }
}
