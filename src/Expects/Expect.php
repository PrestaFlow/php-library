<?php

namespace PrestaFlow\Library\Expects;

use HeadlessChromium\Page as HeadlessChromiumPage;
use Nunzion\Expect as ExpectLibrary;

class Expect extends ExpectLibrary
{
    protected $page;
    protected static $expectedValue;

    public static $latestError = '';

    public function with($page)
    {
        $this->page = $page;
        return $this;
    }

    public static function that($value)
    {
        self::$expectedValue = $value;
        return parent::that($value);
    }

    protected function getValue()
    {
        return self::$expectedValue;
    }

    protected function getExceptionConstructor($explanation, $arguments = array())
    {
        if ($this->page instanceof HeadlessChromiumPage) {
            sleep(3);
            $fileName = 'error_'.$this->page->getSession()->getTargetId().'.png';
            self::$latestError = $fileName;
            if (function_exists('storage_path')) {
                $this->page->screenshot()->saveToFile(storage_path().'/screens/errors/'.$fileName);
            } else {
                $this->page->screenshot()->saveToFile('../../screens/errors/'.$fileName);
            }
        }
        return parent::getExceptionConstructor($explanation, $arguments);
    }

    //AssertionError: expected 'catalogue de modules • 1.7.6.9' to include 'marketplace'

    public function contains($needle)
    {
        $this->isDefined();

        if (str_contains($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("to include {expected}", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function startsWith($needle)
    {
        $this->isDefined();

        if (str_starts_with($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("to starts with {expected}", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function endsWith($needle)
    {
        $this->isDefined();

        if (str_ends_with($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("to ends with {expected}", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }
}
