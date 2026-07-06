<?php

namespace PrestaFlow\Library\Expects;

use Exception;
use HeadlessChromium\Exception\ElementNotFoundException;
use HeadlessChromium\Exception\FilesystemException;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page as HeadlessChromiumPage;
use Nunzion\Expect as ExpectLibrary;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Utils\Screenshots;
use Symfony\Component\ErrorHandler\Error\FatalError;

class Expect extends ExpectLibrary
{
    protected static $expectedValue;
    protected static $keepOverrideMessage = false;
    protected static $overrideMessage = null;

    public static $expectMessage = [];

    public static $latestWarning = '';
    public static $latestError = '';
    public static $latestScreenshotError = null;
    public static $nbAssertions = 0;

    /** Attaches (chemins relatifs) à joindre au testcase courant (régression visuelle). */
    public static array $latestAttachments = [];

    public static function setVisualAttachments(array $paths): void
    {
        self::$latestAttachments = $paths;
    }

    protected static string $locale = 'en';
    protected static ?array $translations = null;

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
        self::$translations = null;
    }

    protected static function trans(string $key): string
    {
        if (self::$translations === null) {
            self::$translations = require __DIR__ . '/translations.php';
        }

        return self::$translations[self::$locale][$key]
            ?? self::$translations['en'][$key]
            ?? $key;
    }

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
        self::$latestScreenshotError = null;
        try {
            $page = TestsSuite::getPage();
            if ($page instanceof HeadlessChromiumPage) {
                sleep(Screenshots::captureDelay());
                $fileName = 'error_' . $page->getSession()->getTargetId() . '-' . time() . '.png';
                self::$latestError = $fileName;
                $screenshot = $page->screenshot([
                    'captureBeyondViewport' => true,
                    'clip' => $page->getFullPageClip(),
                    'format' => 'png',
                ]);
                $screenshot->saveToFile(Screenshots::errorPath($fileName, create: true));
            }
        } catch (OperationTimedOut $e) {
            self::$latestError = null;
            self::$latestScreenshotError = $e->getMessage();
        } catch (FilesystemException $e) {
            self::$latestError = null;
            self::$latestScreenshotError = $e->getMessage();
        } catch (Exception $e) {
            self::$latestError = null;
            self::$latestScreenshotError = $e->getMessage();
        }

        if (self::$overrideMessage !== null) {
            $explanation = self::$overrideMessage;
        }

        if (!empty(self::$expectMessage['pass'])) {
            $lastIndex = count(self::$expectMessage['pass']) - 1;
            self::$expectMessage['fail'][] = self::$expectMessage['pass'][$lastIndex];
            unset(self::$expectMessage['pass'][$lastIndex]);
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
        $expectedMessage ??= self::trans('shop_in_maintenance');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__(self::trans('shop_not_in_maintenance'))->elementIsVisible($page->selector('maintenanceBlock'), $timeout, true);

        return $this;
    }

    public function shopIsNotInMaintenance($page, $timeout = 1000, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('shop_not_in_maintenance');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__(self::trans('shop_in_maintenance'))->elementIsNotVisible($page->selector('maintenanceBlock'), $timeout, true);

        return $this;
    }

    public function shopIsVisible($page, $timeout = 1000, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('shop_visible');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__(self::trans('shop_not_visible'))->elementIsVisible($page->selector('desktopLogo'), $timeout, true);

        return $this;
    }

    public function shopIsNotVisible($page, $timeout = 1000, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('shop_not_visible');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__(self::trans('shop_visible'))->elementIsNotVisible($page->selector('desktopLogo'), $timeout, true);

        return $this;
    }

    public function visible($selector = null, $avoidExpectMessage = false, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('element_must_be_visible');

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
        $expectedMessage ??= self::trans('element_must_not_be_visible');

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
        $expectedMessage ??= self::trans('customer_logged');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__(self::trans('customer_not_logged'))->elementIsVisible($selector, $timeout);

        return $this;
    }

    public function customerIsNotLogged($selector, $timeout = 30000, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('customer_not_logged');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        Expect::that(null, true)->__(self::trans('customer_logged'))->elementIsNotVisible($selector, $timeout);

        return $this;
    }

    public function contains($needle, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('contains');

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
        $expectedMessage ??= self::trans('not_contains');

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
        $expectedMessage ??= self::trans('starts_with');

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
        $expectedMessage ??= self::trans('ends_with');

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
        $expectedMessage ??= self::trans('is_the_same_as');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("expected" => $expected, "value" => $this->getValue()));

        $this->isDefined();
        if ($this->getValue() !== $expected) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $expected, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isNotTheSameAs($expected, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_not_the_same_as');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("expected" => $expected, "value" => $this->getValue()));

        $this->isDefined();
        if ($this->getValue() === $expected) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $expected, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function samePriceAs($price, ?string $expectedMessage = null)
    {
        return $this->isTheSameAs(expected: $price, expectedMessage: $expectedMessage ?? self::trans('same_price_as'));
    }

    public function equals($other, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('equals');

        $other = trim($other);

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("expected" => $other, "value" => $this->getValue()));

        $this->isDefined();
        if ($this->getValue() != $other) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $other, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function notEquals($other, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('not_equals');

        $other = trim($other);

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("expected" => $other, "value" => $this->getValue()));

        $this->isDefined();
        if ($this->getValue() == $other) {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => $other, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isNull(?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_null');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        $this->isDefined();
        if ($this->getValue() !== null)
        {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("expected" => null, "value" => $this->getValue()));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isNotNull(?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_not_null');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        if ($this->getValue() === null)
        {
            $e = $this->getExceptionConstructor($expectedMessage);
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isEmpty(?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_empty');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        $this->isDefined();
        if (!empty($this->getValue()))
        {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage);
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isNotEmpty(?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_not_empty');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage);

        if (empty($this->getValue()))
        {
            $e = $this->getExceptionConstructor($expectedMessage);
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isBetween($min, $max, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_between');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("min" => $min, "max" => $max));
        $this->isNumber();
        if ($this->getValue() < $min || $this->getValue() > $max)
        {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("min" => $min, "max" => $max));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isGreaterThan($other, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_greater_than');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("other" => $other));

        $this->isNumber();
        if ($this->getValue() <= $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("other" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isLessThan($other, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_less_than');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("other" => $other));

        $this->isNumber();
        if ($this->getValue() >= $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("other" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isGreaterThanOrEqualTo($other, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_greater_than_or_equal_to');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("other" => $other));

        $this->isNumber();
        if ($this->getValue()< $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("other" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    public function isLessThanOrEqualTo($other, ?string $expectedMessage = null)
    {
        $expectedMessage ??= self::trans('is_less_than_or_equal_to');

        self::$expectMessage['pass'][] = $this->format(expectedMessage: $expectedMessage, arguments: array("other" => $other));

        $this->isNumber();
        if ($this->getValue() > $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor($expectedMessage, array("other" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }
}
