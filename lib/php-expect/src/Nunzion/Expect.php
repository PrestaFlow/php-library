<?php

/**
 * @author Henning Dieterichs <henning.dieterichs@hediet.de>
 * @copyright 2013-2014 Henning Dieterichs <henning.dieterichs@hediet.de>
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Nunzion;

/**
 * Defines methods to ensure expectations.
 */
class Expect
{

    /**
     * Returns a new expect object to ensure expectations.
     * 
     * @param mixed $value the value to ensure expectations for.
     * @return Expect the expect object.
     */
    public static function that($value)
    {
        return new static($value, "", null, true);
    }

    /**
     * The value to ensure expectations for.
     * @var mixed
     */
    private $value;

    /**
     * Is true, if the value is defined, otherwise false.
     * Is only false for results of itsArrayElement() if the key is not defined.
     * @var bool
     */
    private $isDefined;

    /**
     * The key of value. Only set for results of itsArrayElement().
     * @var string
     */
    private $key;

    /**
     * The path to this element. 
     * Is only different from "" for results of itsArrayElement().
     * @var string
     */
    private $path;

    private function __construct($value, $path, $key, $isDefined)
    {
        $this->value = $value;
        $this->path = $path;
        $this->key = $key;
        $this->isDefined = $isDefined;
    }

    // <editor-fold defaultstate="collapsed" desc="General tests">

    /**
     * Throws an appropiate exception, depending on the current test subject.
     * This method does not perform any tests!
     * 
     * @param string $message the message of the exception
     * @param array $arguments the arguments used within message.
     */
    public function _($message, $arguments = null)
    {
        $e = $this->getExceptionConstructor($message, $arguments);
        throw call_user_func_array($e[0], $e[1]);
    }

    /**
     * Ensures that the value is defined and the same as $other. 
     * Two objects are the same, if the '===' comparison succeeds.
     * 
     * @param mixed $other the reference value.
     * @return self
     */
    public function isTheSameAs($other)
    {
        $this->isDefined();
        if ($this->value !== $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must be the same as {expected}", array("expected" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is defined and equal to $other.
     * Two objects are equal, if the '==' comparison suceeds.
     * 
     * @param mixed $other the reference value.
     * @return self
     */
    public function equals($other)
    {
        $this->isDefined();
        if ($this->value != $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must be equal to {expected}", array("expected" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is defined and null.
     * 
     * @return self
     */
    public function isNull()
    {
        $this->isDefined();
        if ($this->value !== null)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must be null", array("expected" => null));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures the the value is not null.
     * 
     * @return self
     */
    public function isNotNull()
    {
        if ($this->value === null)
        {
            $e = $this->getExceptionConstructor("cannot be null");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures the the value is defined and empty.
     * 
     * @return self
     */
    public function isEmpty()
    {
        $this->isDefined();
        if (!empty($this->value))
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must be empty");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures the the value is not empty.
     * 
     * @return self
     */
    public function isNotEmpty()
    {
        if (empty($this->value))
        {
            $e = $this->getExceptionConstructor("cannot be empty");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Type tests">

    /**
     * Ensures that the value is of the given type.
     * If an appropriate "is*" method exists, it will be called, 
     * otherwise isInstanceOf will be called.
     * 
     * @param string $type the type.
     * @return self
     */
    public function is($type)
    {
        $supportedTests = array(
            "int" => "isInt", 
            "number" => "isNumber",
            "float" => "isFloat",
            "string" => "isString",
            "array" => "isArray",
            "callable" => "isCallable",
            "object" => "isObject");
        
        if (array_key_exists($type, $supportedTests))
        {
            $m = $supportedTests[$type];
            $this->$m();
        }
        else
        {
            $this->isInstanceOf($type);
        }
        
        return $this;
    }
    
    /**
     * Ensures that the value is an integer.
     * 
     * @return self
     */
    public function isInt()
    {
        if (!is_int($this->value))
        {
            $e = $this->getTypeMismatchExceptionConstructor("int");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is a float.
     * 
     * @return self
     */
    public function isFloat()
    {
        if (!is_float($this->value))
        {
            $e = $this->getTypeMismatchExceptionConstructor("float");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is a number, i.e. either an integer or a float.
     * 
     * @return self
     */
    public function isNumber()
    {
        if (!is_float($this->value) && !is_int($this->value))
        {
            $e = $this->getTypeMismatchExceptionConstructor("number");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is a string.
     * 
     * @return self
     */
    public function isString()
    {
        if (!is_string($this->value))
        {
            $e = $this->getTypeMismatchExceptionConstructor("string");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is callable.
     * 
     * @return self
     */
    public function isCallable()
    {
        if (!is_callable($this->value))
        {
            $e = $this->getTypeMismatchExceptionConstructor("callable");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is an array.
     * 
     * @return self
     */
    public function isArray()
    {
        if (!is_array($this->value))
        {
            $e = $this->getTypeMismatchExceptionConstructor("array");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is an array whose items are type of $itemType.
     * For each item, the method "is" will be called.
     * 
     * @param string $itemType the type of the items.
     * @return self
     */
    public function isArrayOf($itemType)
    {
        if (!is_array($this->value))
        {
            $e = $this->getTypeMismatchExceptionConstructor($itemType . "[]");
            throw call_user_func_array($e[0], $e[1]);
        }
        
        foreach ($this->value as $key => $item)
        {
            //throw exception with appropriate error message
            $this->itsArrayElement($key)->is($itemType);
        }
        
        return $this;
    }
    
    /**
     * Ensures that the value is an object.
     * 
     * @return self
     */
    public function isObject()
    {
        if (!is_object($this->value))
        {
            $e = $this->getTypeMismatchExceptionConstructor("object");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is of type $classOrInterface.
     * 
     * @param string $classOrInterface the class or interface name.
     * @return self
     */
    public function isInstanceOf($classOrInterface)
    {
        if (!($this->value instanceof $classOrInterface))
        {
            $message = class_exists($classOrInterface, false) ?
                    "must be an instance of" : "must implement";
            $message .= " '{expected}', but is {actualText}";
            
            $e = $this->getTypeMismatchExceptionConstructor($classOrInterface, $message);
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Number tests">

    /**
     * Ensures that the value is a number and in the interval [$min, $max].
     * 
     * @param int|float $min the lower bound, inclusive.
     * @param int|float $max the upper bound, inclusive.
     * @return self
     */
    public function isBetween($min, $max)
    {
        $this->isNumber();
        if ($this->value < $min || $this->value > $max)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must be between {min} and {max}", array("min" => $min, "max" => $max));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is a number and greater than $other.
     * 
     * @param int|float $other the reference value.
     * @return self
     */
    public function isGreaterThan($other)
    {
        $this->isNumber();
        if ($this->value <= $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must be greater than {other}", array("other" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is a number and less than $other.
     * 
     * @param int|float $other the reference value.
     * @return self
     */
    public function isLessThan($other)
    {
        $this->isNumber();
        if ($this->value >= $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must be less than {other}", array("other" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is a number and greater than or equal to $other.
     * 
     * @param int|float $other the reference value.
     * @return self
     */
    public function isGreaterThanOrEqualTo($other)
    {
        $this->isNumber();
        if ($this->value < $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must be greater or equal than {other}", array("other" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * @deprecated since version 1.0.0
     * @see isGreaterThanOrEqualTo()
     */
    public function isGreaterOrEqualThan($other)
    {
        return $this->isGreaterThanOrEqualTo($other);
    }

    /**
     * Ensures that the value is a number and less than or equal to $other.
     * 
     * @param int|float $other the reference value.
     * @return self
     */
    public function isLessThanOrEqualTo($other)
    {
        $this->isNumber();
        if ($this->value > $other)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must be less or equal than {other}", array("other" => $other));
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * @deprecated since version 1.0.0
     * @see isGreaterThanOrEqualTo()
     */
    public function isLessOrEqualThan($other)
    {
        return $this->isLessThanOrEqualTo($other);
    }

    // </editor-fold>


    /**
     * Checks whether the called method begins with "isNullOr" or "isUndefinedOr"
     * and calls the method with name "is" concatenized with the rest of the string.
     * 
     * Example call: isNullOrString() or isNullOrBetween(1, 2)
     * 
     * @param string $name
     * @param array $arguments
     * @return self
     */
    public function __call($name, array $arguments)
    {
        $isNullOr = "isNullOr";
        $isUndefinedOr = "isUndefinedOr";

        if (stripos($name, $isNullOr) === 0)
        {
            if ($this->isDefined && $this->value === null)
                return;

            $newMethod = "is" . substr($name, strlen($isNullOr));
        }
        else if (stripos($name, $isUndefinedOr) === 0)
        {
            if (!$this->isDefined)
                return;

            $newMethod = "is" . substr($name, strlen($isUndefinedOr));
        }
        else
            throw new \BadMethodCallException("Method '" . $name . "' does not exist.");

        //this is faster than call_user_func
        switch (count($arguments))
        {
            case 0: return $this->$newMethod();
            case 1: return $this->$newMethod($arguments[0]);
            case 2: return $this->$newMethod($arguments[0], $arguments[1]);
            case 3: return $this->$newMethod($arguments[0], $arguments[1], $arguments[2]);
            default: throw new \InvalidArgumentException("Too many arguments.");
        }
        return $this;
    }

    // <editor-fold defaultstate="collapsed" desc="Array tests">
    
    /**
     * Ensures the the value is defined. This can only fail for results of
     * itsArrayElement().
     * 
     * @return self
     */
    public function isDefined()
    {
        if (!$this->isDefined)
        {
            $e = $this->getExceptionConstructor("must be defined");
            throw call_user_func_array($e[0], $e[1]);
        }
        return $this;
    }

    /**
     * Ensures that the value is an array and gets an expect object for an array element.
     * 
     * @param mixed $key the key.
     * @return self
     */
    public function itsArrayElement($key)
    {
        $this->isArray();
        
        $isDefined = array_key_exists($key, $this->value);
        $value = $isDefined ? $this->value[$key] : null;
        
        return new static($value, $this->path . "[" . $key . "]", $key, $isDefined);
    }
    
    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="Helper">
    
     /**
     * Checks whether $variableName is a parameter name.
     * 
     * @param string $variableName the name of the variable.
     * @param array $callTraceElement the call trace element of the expect method call.
     * @return boolean true, if $variableName is a parameter, otherwise false.
     */
    private function isParameter($variableName, array $callTraceElement)
    {
        if (!isset($callTraceElement["function"]))
            return false;

        try
        {
            if (isset($callTraceElement["class"]))
                $m = new \ReflectionMethod($callTraceElement["class"], $callTraceElement["function"]);
            else
                $m = new \ReflectionFunction($callTraceElement["function"]);
        }
        catch (\ReflectionException $e)
        {
            return false;
        }

        if ($m !== null)
        {
            foreach ($m->getParameters() as $p)
                if ($p->name === $variableName)
                    return true;
        }

        return false;
    }

    /**
     * Finds ::that(.*) in or before the line the is*-method is called from.
     * @param array $callTraceElement
     * @return string the parameter expression like "$this->foo", or null if it could not be found.
     */
    private function getThatParameterExpression(array $callTraceElement)
    {
        if (!isset($callTraceElement["file"]) || !isset($callTraceElement["line"]))
            return null;
        
        $lines = file($callTraceElement["file"]);
        $lineCount = count($lines);
        
        $functionCallLine = $callTraceElement["line"];
        if ($functionCallLine >= $lineCount)
            return null;

        for ($i = 1; $i <= $lineCount; $i++)
        {
            $line = $lines[$functionCallLine - $i];
            $matches = array();
            preg_match("/\\::that\\((?<parameter>.*?)\\)/", $line, $matches);
            if (count($matches) > 0)
                return trim($matches["parameter"]);
        }
        return null;
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

    
    protected function getPreconditionViolationExceptionConstructor($message, $arguments)
    {
        return array(
              array(new \ReflectionClass("\InvalidArgumentException"), "newInstance"), 
              array($message)
          );
    }

    protected function getInvariantViolationExceptionConstructor($message, $arguments)
    {
        return array(
              array(new \ReflectionClass("\Nunzion\InvariantViolationException"), "newInstance"), 
              array($message)
          );
    }

    protected function getConditionViolationExceptionConstructor($message, $arguments)
    {
        return array(
              array(new \ReflectionClass("\UnexpectedValueException"), "newInstance"), 
              array($message)
          );
    }
    
    
    protected function getExceptionConstructor($explanation, $arguments = array())
    {
        $trace = debug_backtrace();
        $methodCount = 0;
        $ignoredClasses = array(get_class($this), "Nunzion\\Expect");
        while (isset($trace[$methodCount]["class"]) 
                && in_array($trace[$methodCount]["class"], $ignoredClasses)) {
            $methodCount++;
        }
        $methodCount--;

        $callerStackFrame = $trace[$methodCount];
        $callersCallerStackFrame = null;
        if (isset($trace[$methodCount + 1]))
            $callersCallerStackFrame = $trace[$methodCount + 1];
            
        $expression = $this->getThatParameterExpression($callerStackFrame) . $this->path;
        $arguments["path"] = $this->path;
        $arguments["expression"] = $expression;

        $matches = array();
        preg_match("/\\$(?<variableName>[a-zA-Z_][a-zA-Z0-9_]*)/", $expression, $matches);
        if (count($matches) > 0)
        {
            $pathParts = explode("->", str_replace("[", "->", ltrim($expression, "$")));

            $variableName = $matches["variableName"];
            if ($variableName === "this")
            {
                //e.g. Member $this->foo is invalid: $this->foo->bar cannot be smaller than 3.
                //e.g. Member $this->foo cannot be smaller than 3.

                $arguments["member"] = $pathParts[1];

                if (count($pathParts) > 2)
                    $message = "Member '{member}' is invalid: '{expression}' " . $explanation;
                else
                    $message = "Member '{member}' " . $explanation;

                return $this->getInvariantViolationExceptionConstructor(
                        $this->format($message . ".", $arguments), $arguments);
            }
            else if ($callersCallerStackFrame !== null
                && $this->isParameter($variableName, $callersCallerStackFrame))
            {
                $arguments["parameter"] = $pathParts[0];

                if (count($pathParts) > 1)
                    $message = "Parameter '{parameter}' is invalid: '{expression}' " . $explanation;
                else
                    $message = "Parameter '{parameter}' " . $explanation;

                return $this->getPreconditionViolationExceptionConstructor(
                        $this->format($message . ".", $arguments), $arguments);
            }
            else
            {
                return $this->getConditionViolationExceptionConstructor(
                        $this->format("'{expression}' " . $explanation . ".", $arguments), $arguments);
            }
        }

        return $this->getConditionViolationExceptionConstructor(
                $this->format("The value " . $explanation . ".", $arguments), $arguments);
    }

    protected function getUnexpectedValueExceptionConstructor($explanation, $arguments = array())
    {
        $arguments["actual"] = $this->value;

        return $this->getExceptionConstructor($explanation . ", but is {actual}", $arguments);
    }

    
    protected function getTypeMismatchExceptionConstructor($expectedType, $message = null)
    {
        if (!$this->isDefined)
            $typeStr = $type = "undefined";
        else if ($this->value === null)
            $typeStr = $type = "null";
        else
            $typeStr = "type of '" . ($type = is_object($this->value) ? get_class($this->value) : gettype($this->value)) . "'";
        
        if ($message === null)
            $message = "must be type of '{expected}', but is {actualText}";
        
        return $this->getExceptionConstructor($message, 
                array("expected" => $expectedType, 
                      "actual" => $type, 
                      "actualText" => $typeStr));
    }
    
    // </editor-fold>
}
