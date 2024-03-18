<?php

namespace PrestaFlow\Library\Expects;

use HeadlessChromium\Page as HeadlessChromiumPage;
use Nunzion\Expect as ExpectLibrary;

class Expect extends ExpectLibrary
{
    protected $page;
    protected static $expectedValue;

    public static $latestWarning = '';
    public static $latestError = '';

    public static function setWarning($message)
    {
        self::$latestWarning = $message;
    }

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

    private function format($template, $arguments)
    {
        return preg_replace_callback('/\\{(?<parameterName>.*?)(\\:(?<formatOptions>.*))?\\}/',
                function ($match) use ($arguments)
        {
            $parameterName = $match["parameterName"];
            if (isset($arguments[$parameterName]))
            {
                $result = $arguments[$parameterName];
                if (is_object($result) || is_array($result))
                    $result = print_r($result, true);
                return $result;
            }
        }, $template);
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

        return $this->getConditionViolationExceptionConstructor(
            $this->format($explanation, $arguments), $arguments);
    }

    protected function getUnexpectedValueExceptionConstructor($explanation, $arguments = array())
    {
        $arguments["actual"] = $this->getValue();

        return $this->getExceptionConstructor($explanation, $arguments);
    }

    public function visible($selector = null)
    {
        $this->isDefined();
        if ($selector === null) {
            $selector = 'Element';
        }
        if ($this->getValue() != true)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("{selector} must be visible", array("selector" => $selector));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function notVisible($selector = null)
    {
        $this->isDefined();
        if ($selector === null) {
            $selector = 'Element';
        }
        if ($this->getValue() != false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("{selector} must be not visible", array("selector" => $selector));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function contains($needle)
    {
        $this->isDefined();

        if (str_contains($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("expected '{expected}' to include '{actual}'", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function notContains($needle)
    {
        $this->isDefined();

        if (!str_contains($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("expected '{expected}' to not include '{actual}'", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function startsWith($needle)
    {
        $this->isDefined();

        if (str_starts_with($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("'{actual}' to starts with {expected}", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function endsWith($needle)
    {
        $this->isDefined();

        if (str_ends_with($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("'{actual}' to ends with {expected}", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }
}
