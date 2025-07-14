<?php

namespace PrestaFlow\Library\Expects;

use Exception;
use HeadlessChromium\Exception\ElementNotFoundException;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page as HeadlessChromiumPage;
use Nunzion\Expect as ExpectLibrary;
use PrestaFlow\Library\Tests\TestsSuite;
use Symfony\Component\ErrorHandler\Error\FatalError;

class Expect extends ExpectLibrary
{
    protected static $expectedValue;
    protected static $keepOverrideMessage = false;
    protected static $overrideMessage = null;

    public static $expectMessage = [];

    public static $latestWarning = '';
    public static $latestError = '';
    public static $nbAssertions = 0;

    public function __call($methodName, $args = [])
    {
        $e = $this->getUnexpectedValueExceptionConstructor("{methodName} does not exists", array("methodName" => $methodName));
        throw call_user_func_array($e[0], $e[1]);
    }

    public static function setWarning($message)
    {
        self::$latestWarning = $message;
    }

    public static function getExpectMessage() : array
    {
        $expectMessage = self::$expectMessage;
        self::$expectMessage = [];
        return $expectMessage;
    }

    public static function getNbAssertions()
    {
        $nbAssertions = self::$nbAssertions;
        self::$nbAssertions = 0;
        return $nbAssertions;
    }

    public static function that($value = null, $internal = false)
    {
        if (!self::$keepOverrideMessage) {
            self::$overrideMessage = null;
        }
        self::$keepOverrideMessage = false;
        self::$expectedValue = $value;
        if (!$internal) {
            self::$nbAssertions++;
        }
        return parent::that($value);
    }

    public function keepOverrideMessage()
    {
        self::$keepOverrideMessage = true;
        return $this;
    }

    public function __($explanation)
    {
        $this->keepOverrideMessage();
        self::$overrideMessage = $explanation;
        return $this;
    }

    protected function getValue()
    {
        return self::$expectedValue;
    }

    private function format($template, $arguments = [])
    {
        $arguments["actual"] = $this->getValue();

        if (is_bool($arguments["actual"])) {
            $arguments["actual"] = $arguments["actual"] ? 'true' : 'false';
        }

        return preg_replace_callback('/\\{(?<parameterName>.*?)(\\:(?<formatOptions>.*))?\\}/',
                function ($match) use ($arguments)
        {
            $parameterName = $match["parameterName"];
            if (isset($arguments[$parameterName]))
            {
                $result = $arguments[$parameterName];
                if (is_object($result) || is_array($result)) {
                    $result = print_r($result, true);
                } else if (is_bool($result)) {
                    $result = $result ? 'true' : 'false';
                }
                return $result;
            }
        }, $template);
    }

    protected function getExceptionConstructor($explanation, $arguments = array())
    {
        try {
            $page = TestsSuite::getPage();
            if ($page instanceof HeadlessChromiumPage) {
                sleep(3);
                $fileName = 'error_'.$page->getSession()->getTargetId().'.png';
                self::$latestError = $fileName;
                $screenshot = $page->screenshot();
                if (function_exists('storage_path')) {
                    $screenshot->saveToFile(storage_path().'/screens/errors/'.$fileName);
                } else {
                    $screenshot->saveToFile('../../screens/errors/'.$fileName);
                }
            }
        } catch (OperationTimedOut $e) {
            self::$latestError = null;
        }

        if (self::$overrideMessage !== null) {
            $explanation = self::$overrideMessage;
        }

        if (isset(self::$expectMessage['pass'][(count(self::$expectMessage['pass'])-1)])) {
            self::$expectMessage['fail'][] = self::$expectMessage['pass'][(count(self::$expectMessage['pass'])-1)];
            unset(self::$expectMessage['pass'][(count(self::$expectMessage['pass'])-1)]);
        }

        return $this->getConditionViolationExceptionConstructor(
            $this->format($explanation, $arguments), $arguments);
    }

    protected function getUnexpectedValueExceptionConstructor($explanation, $arguments = array())
    {
        $arguments["actual"] = $this->getValue();

        if (is_bool($arguments["actual"])) {
            $arguments["actual"] = $arguments["actual"] ? 'true' : 'false';
        }

        return $this->getExceptionConstructor($explanation, $arguments);
    }

    public function elementIsVisible($selector = null, $timeout = 30000, $avoidExpectMessage = false)
    {
        $isVisible = $this->_elementIsVisible($selector, $timeout);

        Expect::that($isVisible, true)->visible($selector, $avoidExpectMessage);
    }

    public function elementIsNotVisible($selector = null, $timeout = 30000, $avoidExpectMessage = false)
    {
        $isVisible = $this->_elementIsVisible($selector, $timeout);

        Expect::that($isVisible, true)->notVisible($selector, $avoidExpectMessage);
    }

    protected function _elementIsVisible($selector, $timeout)
    {
        try {
            TestsSuite::getPage()->waitUntilContainsElement($selector, $timeout);
        } catch (OperationTimedOut | ElementNotFoundException | FatalError | Exception $e) {
            return false;
        }

        return true;
    }

    public function shopIsInMaintenance($page, $timeout = 1000)
    {
        self::$expectMessage['pass'][] = $this->format("shop is in maintenance");

        Expect::that(null, true)->__("shop is not in maintenance")->elementIsVisible($page->selector('maintenanceBlock'), $timeout, true);

        return $this;
    }

    public function shopIsNotInMaintenance($page, $timeout = 1000)
    {
        self::$expectMessage['pass'][] = $this->format("shop is not in maintenance");

        Expect::that(null, true)->__("shop is in maintenance")->elementIsNotVisible($page->selector('maintenanceBlock'), $timeout, true);

        return $this;
    }

    public function shopIsVisible($page, $timeout = 1000)
    {
        self::$expectMessage['pass'][] = $this->format("shop is visible");

        Expect::that(null, true)->__("shop is not visible")->elementIsVisible($page->selector('desktopLogo'), $timeout, true);

        return $this;
    }

    public function shopIsNotVisible($page, $timeout = 1000)
    {
        self::$expectMessage['pass'][] = $this->format("shop is not visible");

        Expect::that(null, true)->__("shop is visible")->elementIsNotVisible($page->selector('desktopLogo'), $timeout, true);

        return $this;
    }

    public function visible($selector = null, $avoidExpectMessage = false)
    {
        if (!$avoidExpectMessage) {
            self::$expectMessage['pass'][] = $this->format("{selector} must be visible", array("selector" => $selector));
        }

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

    public function notVisible($selector = null, $avoidExpectMessage = false)
    {
        if (!$avoidExpectMessage) {
            self::$expectMessage['pass'][] = $this->format("{selector} must be not visible", array("selector" => $selector));
        }

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

    public function customerIsLogged($selector, $timeout = 30000)
    {
        self::$expectMessage['pass'][] = $this->format("customer is not logged");

        Expect::that(null, true)->__("customer is not logged")->elementIsVisible($selector, $timeout);

        return $this;
    }

    public function customerIsNotLogged($selector, $timeout = 30000)
    {
        self::$expectMessage['pass'][] = $this->format("customer is logged");

        Expect::that(null, true)->__("customer is logged")->elementIsNotVisible($selector, $timeout);

        return $this;
    }

    public function contains($needle)
    {
        self::$expectMessage['pass'][] = $this->format("expected '{value}' to contains '{expected}'", array("expected" => $needle, "value" => $this->getValue()));

        $this->isDefined();

        if (str_contains($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("expected '{value}' to contains '{expected}'", array("expected" => $needle, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function notContains($needle)
    {
        self::$expectMessage['pass'][] = $this->format("expected '{expected}' to not include '{value}'", array("expected" => $needle, "value" => $this->getValue()));

        $this->isDefined();

        if (!str_contains($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("expected '{expected}' to not include '{value}'", array("expected" => $needle, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function startsWith($needle)
    {
        self::$expectMessage['pass'][] = $this->format("expected '{expected}' to starts with '{actual}'", array("expected" => $needle));

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
        self::$expectMessage['pass'][] = $this->format("expected '{expected}' to ends with '{actual}'", array("expected" => $needle));

        $this->isDefined();

        if (str_ends_with($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("'{actual}' to ends with {expected}", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function isTheSameAs($other)
    {
        self::$expectMessage['pass'][] = $this->format("{value} must be the same as {expected}", array("expected" => $other, "value" => $this->getValue()));

        return parent::isTheSameAs($other);
    }

    public function samePriceAs($price)
    {
        self::$expectMessage['pass'][] = $this->format("{value} must be the same price as {expected}", array("expected" => $price, "value" => $this->getValue()));

        return $this->contains($price);
    }

    public function equals($other)
    {
        $other = trim($other);

        var_dump($this->getValue());
        var_dump($other);

        self::$expectMessage['pass'][] = $this->format("{value} must be equal to {expected}", array("expected" => $other, "value" => $this->getValue()));

        return parent::equals($other);
    }

    public function isNull()
    {
        self::$expectMessage['pass'][] = $this->format("must be null");

        return parent::isNull();
    }

    public function isNotNull()
    {
        self::$expectMessage['pass'][] = $this->format("cannot be null");

        return parent::isNotNull();
    }

    public function isEmpty()
    {
        self::$expectMessage['pass'][] = $this->format("must be empty");

        return parent::isEmpty();
    }

    public function isNotEmpty()
    {
        self::$expectMessage['pass'][] = $this->format("cannot be empty");

        return parent::isNotEmpty();
    }

    public function isBetween($min, $max)
    {
        self::$expectMessage['pass'][] = $this->format("{actual} must be between {min} and {max}", array("min" => $min, "max" => $max));

        return parent::isBetween($min, $max);
    }

    public function isGreaterThan($other)
    {
        self::$expectMessage['pass'][] = $this->format("{actual} must be greater than {other}", array("other" => $other));

        return parent::isGreaterThan($other);
    }

    public function isLessThan($other)
    {
        self::$expectMessage['pass'][] = $this->format("{actual} must be less than '{other}'", array("other" => $other));

        return parent::isLessThan($other);
    }

    public function isGreaterThanOrEqualTo($other)
    {
        self::$expectMessage['pass'][] = $this->format("{actual} must be greater or equal than {other}", array("other" => $other));

        return parent::isGreaterThanOrEqualTo($other);
    }

    public function isLessThanOrEqualTo($other)
    {
        self::$expectMessage['pass'][] = $this->format("'{actual}' must be less or equal than {other}", array("other" => $other));

        return parent::isLessThanOrEqualTo($other);
    }
}
