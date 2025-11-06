<?php

namespace PrestaFlow\Library\Expects;

use Exception;
use HeadlessChromium\Exception\ElementNotFoundException;
use HeadlessChromium\Exception\FilesystemException;
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

    public static function getExpectMessage(): array
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

    private function format($expectedMessage, $arguments = [])
    {
        $arguments["actual"] = $this->getValue();

        if (is_bool($arguments["actual"])) {
            $arguments["actual"] = $arguments["actual"] ? 'true' : 'false';
        }

        return preg_replace_callback(
            '/\\{(?<parameterName>.*?)(\\:(?<formatOptions>.*))?\\}/',
            function ($match) use ($arguments) {
                $parameterName = $match["parameterName"];
                if (isset($arguments[$parameterName])) {
                    $result = $arguments[$parameterName];
                    if (is_object($result) || is_array($result)) {
                        $result = print_r($result, true);
                    } else if (is_bool($result)) {
                        $result = $result ? 'true' : 'false';
                    }
                    return $result;
                }
            },
            $expectedMessage
        );
    }

    protected function getExceptionConstructor($explanation, $arguments = array())
    {
        try {
            $page = TestsSuite::getPage();
            if ($page instanceof HeadlessChromiumPage) {
                sleep(3);
                $fileName = 'error_' . $page->getSession()->getTargetId() . '-' . time() . '.png';
                self::$latestError = $fileName;
                $screenshot = $page->screenshot([
                    'captureBeyondViewport' => true,
                    'clip' => $page->getFullPageClip(),
                    'format' => 'png',
                ]);
                if (function_exists('storage_path')) {
                    $screenshot->saveToFile(storage_path() . '/screens/errors/' . $fileName);
                } else {
                    $screenshot->saveToFile('./prestaflow/screens/errors/' . $fileName);
                }
            }
        } catch (OperationTimedOut $e) {
            self::$latestError = null;
        } catch (FilesystemException $e) {
            self::$latestError = null;
        } catch (Exception $e) {
            self::$latestError = null;
        }

        if (self::$overrideMessage !== null) {
            $explanation = self::$overrideMessage;
        }

        if (isset(self::$expectMessage['pass'][(count(self::$expectMessage['pass']) - 1)])) {
            self::$expectMessage['fail'][] = self::$expectMessage['pass'][(count(self::$expectMessage['pass']) - 1)];
            unset(self::$expectMessage['pass'][(count(self::$expectMessage['pass']) - 1)]);
        }

        return $this->getConditionViolationExceptionConstructor(
            $this->format($explanation, $arguments),
            $arguments
        );
    }

    protected function getUnexpectedValueExceptionConstructor($explanation, $arguments = array())
    {
        $arguments["actual"] = $this->getValue();

        if (is_bool($arguments["actual"])) {
            $arguments["actual"] = $arguments["actual"] ? 'true' : 'false';
        }

        return $this->getExceptionConstructor($explanation, $arguments);
    }

    public function elementIsVisible($selector = null, $timeout = 30000, $avoidExpectMessage = false, ?string $expectedMessage = null)
    {
        $isVisible = $this->_elementIsVisible($selector, $timeout);

        Expect::that($isVisible, true)->visible($selector, $avoidExpectMessage, expectedMessage: $expectedMessage);
    }

    public function elementIsNotVisible($selector = null, $timeout = 30000, $avoidExpectMessage = false, ?string $expectedMessage = null)
    {
        $isVisible = $this->_elementIsVisible($selector, $timeout);

        Expect::that($isVisible, true)->notVisible($selector, $avoidExpectMessage, expectedMessage: $expectedMessage);
    }

    protected function _elementIsVisible($selector, $timeout, ?string $expectedMessage = null)
    {
        try {
            TestsSuite::getPage()->waitUntilContainsElement($selector, $timeout);
        } catch (OperationTimedOut | ElementNotFoundException | FatalError | Exception $e) {
            return false;
        }

        return true;
    }

    public function shopIsInMaintenance($page, $timeout = 1000, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "shop is in maintenance";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__("shop is not in maintenance")->elementIsVisible($page->selector('maintenanceBlock'), $timeout, true);

        return $this;
    }

    public function shopIsNotInMaintenance($page, $timeout = 1000, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "shop is not in maintenance";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__("shop is in maintenance")->elementIsNotVisible($page->selector('maintenanceBlock'), $timeout, true);

        return $this;
    }

    public function shopIsVisible($page, $timeout = 1000, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "shop is visible";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__("shop is not visible")->elementIsVisible($page->selector('desktopLogo'), $timeout, true);

        return $this;
    }

    public function shopIsNotVisible($page, $timeout = 1000, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "shop is not visible";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__("shop is visible")->elementIsNotVisible($page->selector('desktopLogo'), $timeout, true);

        return $this;
    }

    public function visible($selector = null, $avoidExpectMessage = false, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{selector} must be visible";
        }

        if (!$avoidExpectMessage) {
            self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("selector" => $selector));
        }

        $this->isDefined();
        if ($selector === null) {
            $selector = 'Element';
        }
        if ($this->getValue() != true) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("selector" => $selector));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function notVisible($selector = null, $avoidExpectMessage = false, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{selector} must be not visible";
        }

        if (!$avoidExpectMessage) {
            self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("selector" => $selector));
        }

        $this->isDefined();
        if ($selector === null) {
            $selector = 'Element';
        }
        if ($this->getValue() != false) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("selector" => $selector));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function customerIsLogged($selector, $timeout = 30000, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "customer is not logged";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__("customer is not logged")->elementIsVisible($selector, $timeout);

        return $this;
    }

    public function customerIsNotLogged($selector, $timeout = 30000, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "customer is logged";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__("customer is logged")->elementIsNotVisible($selector, $timeout);

        return $this;
    }

    public function contains($needle, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "expected '{value}' to contains '{expected}'";
        }

        self::$expectMessage['pass'][] = $this->format($expectedMessage, array("expected" => $needle, "value" => $this->getValue()));

        $this->isDefined();

        if (str_contains($this->getValue(), $needle) === false) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $needle, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function notContains($needle, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "expected '{expected}' to not include '{value}'";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("expected" => $needle, "value" => $this->getValue()));

        $this->isDefined();

        if (!str_contains($this->getValue(), $needle) === false) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $needle, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function startsWith($needle, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "'{actual}' to starts with {expected}";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("expected" => $needle));

        $this->isDefined();

        if (str_starts_with($this->getValue(), $needle) === false) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function endsWith($needle, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "'{actual}' to ends with {expected}";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("expected" => $needle));

        $this->isDefined();

        if (str_ends_with($this->getValue(), $needle) === false) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function isTheSameAs($expected, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{value} must be the same as {expected}";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("expected" => $expected, "value" => $this->getValue()));

        $this->isDefined();
        if ($this->getValue() !== $expected) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $expected, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function samePriceAs($price, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{value} must be the same price as {expected}";
        }

        return $this->isTheSameAs(expected: $price, expectedMessage: $expectedMessage);
    }

    public function equals($other, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{value} must be equal to {expected}";
        }

        $other = trim($other);

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("expected" => $other, "value" => $this->getValue()));

        $this->isDefined();
        if ($this->getValue() != $other) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isNull(?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "must be null";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        return parent::isNull();
    }

    public function isNotNull(?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "cannot be null";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        return parent::isNotNull();
    }

    public function isEmpty(?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "must be empty";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        return parent::isEmpty();
    }

    public function isNotEmpty(?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "cannot be empty";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        return parent::isNotEmpty();
    }

    public function isBetween($min, $max, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{actual} must be between {min} and {max}";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("min" => $min, "max" => $max));

        return parent::isBetween($min, $max);
    }

    public function isGreaterThan($other, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{actual} must be greater than {other}";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("other" => $other));

        return parent::isGreaterThan($other);
    }

    public function isLessThan($other, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{actual} must be less than {other}";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("other" => $other));

        return parent::isLessThan($other);
    }

    public function isGreaterThanOrEqualTo($other, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{actual} must be greater or equal than {other}";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("other" => $other));

        return parent::isGreaterThanOrEqualTo($other);
    }

    public function isLessThanOrEqualTo($other, ?string $expectedMessage = null)
    {
        if ($expectedMessage === null) {
            $expectedMessage = "{actual} must be less or equal than {other}";
        }

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("other" => $other));

        return parent::isLessThanOrEqualTo($other);
    }
}
