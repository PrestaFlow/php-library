<?php

namespace PrestaFlow\Library\Pages;

use Exception;
use HeadlessChromium\Exception\ElementNotFoundException;
use HeadlessChromium\Exception\OperationTimedOut;
use PrestaFlow\Library\Resolvers\Translations;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Traits\Locale;

class CommonPage
{
    use Translations;
    use Locale;

    protected $globals = [];
    public $selectors = [];
    public $messages = [];

    public string $url = '';
    public string $pageTitle = '';

    protected $patchVersion = null;

    public function __construct(string $locale, string $patchVersion, array $globals)
    {
        $this->globals = $globals;
        $this->patchVersion = $patchVersion;
        $this->initLocale(locale: $locale);

        /*
        $this->init(
            $locale,
            $patchVersion
        );
        */

        return $this;
    }

    public function init(string $locale, string $patchVersion) : CommonPage
    {
        /*
        $this->initTranslations(
            $locale,
            $patchVersion
        );
        */

        /*
        $this->initUrls(
            $locale
        );
        */

        return $this;
    }

    public function message($message)
    {
        return $this->getMessage($message);
    }

    public function getMessage($message)
    {
        if (isset($this->messages[$message])) {
            return $this->messages[$message];
        }

        return null;
    }

    public function defineSelectors()
    {
        return [];
    }

    public function selector($selector, $replacements = [])
    {
        return $this->getSelector($selector, $replacements);
    }

    public function getSelector($selector, $replacements = [])
    {
        if (isset($this->selectors[$selector])) {
            $selector = $this->selectors[$selector];
            if (is_array($replacements)) {
                foreach ($replacements as $key => $value) {
                    $selector = str_replace('${'.$key.'}', $value, $selector);
                }
            }
            return $selector;
        }

        throw new Exception('Selector "' . $selector . '" is not defined');
    }

    public function __call($name, $arguments)
    {
        if (!is_null($this->getPage()) && method_exists($this->getPage(), $name)) {
            call_user_func_array([$this->getPage(), $name], $arguments);
        }
    }

    public function setGlobals($globals)
    {
        $this->globals = $globals;

        $locale = $this->getGlobal('LOCALE');

        /*
        $this->init(
            $locale,
            $this->patchVersion
        );
        */
    }

    public function getGlobals() : array
    {
        return $this->globals;
    }

    public function setGlobal(string $global, $value)
    {
        $this->globals[$global] = $value;

        if ($global === 'LOCALE') {
            $this->setLocale($value);

            /*
            $this->init(
                $value,
                $this->patchVersion
            );
            */
        }
    }

    public function getGlobal($index)
    {
        $globals = $this->getGlobals();

        if (!str_contains($index, '_')) {
            if (isset($globals[$index])) {
                return $globals[$index];
            }
        } else {
            // Test is, by the way, like PS_VERSION
            if (isset($globals[$index])) {
                return $globals[$index];
            }
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

    public function getPage()
    {
        return TestsSuite::getPage();
    }

    public function pageTitle()
    {
        return $this->translate($this->pageTitle);
    }

    public function getPageTitle()
    {
        return $this->getPage()->evaluate('document.title')->getReturnValue();
    }

    public function goToUrl(string $url)
    {
        $this->getPage()->navigate($url)->waitForNavigation();
    }

    public function getTextContent($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
    {
        try {
            if ($waitForSelector) {
                $this->getPage()->waitUntilContainsElement($selector, $timeout);
            }
            $element = $this->getPage()->dom()->querySelector($selector);
            $value = $element->getText();
            if ($value === null) {
                return '';
            }
            return trim(str_replace(['&nbsp;'], '', $value));
        } catch (OperationTimedOut | Exception $e) {
            return false;
        }
    }

    public function getInputValue($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
    {
        try {
            if ($waitForSelector) {
                $this->getPage()->waitUntilContainsElement($selector, $timeout);
            }
            $element = $this->getPage()->dom()->querySelector($selector);
            $value = $element->getAttribute('value');
            if ($value === null) {
                return '';
            }
            return trim(str_replace(['&nbsp;'], '', $value));
        } catch (OperationTimedOut | Exception $e) {
            return false;
        }
    }

    public function navigateTo($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
    {
        try {
            if ($waitForSelector) {
                $this->getPage()->waitUntilContainsElement($selector, $timeout);
            }
            $element = $this->getPage()->dom()->querySelector($selector);
            return $element->click();
        } catch (OperationTimedOut | Exception $e) {
            return false;
        }
    }

    public function click($selector, $nth = 1)
    {
        return $this->getPage()->mouse()->find($selector, $nth)->click();
    }

    public function waitForPageReload()
    {
        $this->getPage()->evaluate('some js that will reload the page')->waitForPageReload();
    }

    /**
     * Delete the existing text then type new value on input
     */
    public function setValue($selector, $value)
    {
        $this->click($selector);

        $textContent = $this->getInputValue($selector);

        if ($textContent !== null && $textContent !== '') {
            $element = $this->getPage()->dom()->querySelector($selector);
            if ($element !== null) {
                // Clear the input value
                $element->setAttributeValue('value', '');
            }
        }

        // Alternatively, you can use the keyboard to delete the text
        // $this->getPage()->keyboard()->typeRawKey('Del'); // Delete key
        $this->getPage()->keyboard()->typeText($value);
        // or
        // $element->sendKeys($value);
    }

    public function elementIsVisible($selector, $timeout = 1000)
    {
        try {
            $this->getPage()->waitUntilContainsElement($selector, $timeout);
        } catch (ElementNotFoundException | OperationTimedOut | Exception $e) {
            return false;
        }

        return true;
    }

    public function getStoragePath($dir)
    {
        if (function_exists('storage_path')) {
            return storage_path();
        }

        return $dir . '/../../../..';
    }

    public function getSelectors(array $selectors = []): array
    {
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

        return $mergedSelectors;
    }

    public function getMessages(): array
    {
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

        return $mergedMessages;
    }
}
