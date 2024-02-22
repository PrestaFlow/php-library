<?php

namespace PrestaFlow\Library\Expects;

use Nunzion\Expect as ExpectLibrary;

class Expect extends ExpectLibrary
{
    protected static $expectedValue;

    public static function that($value)
    {
        self::$expectedValue = $value;
        return parent::that($value);
    }

    protected function getValue()
    {
        return self::$expectedValue;
    }

    public function contains($needle)
    {
        $this->isDefined();

        if (str_contains($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must contains {expected}", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function startsWith($needle)
    {
        $this->isDefined();

        if (str_starts_with($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must starts with {expected}", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }

    public function endsWith($needle)
    {
        $this->isDefined();

        if (str_ends_with($this->getValue(), $needle) === false)
        {
            $e = $this->getUnexpectedValueExceptionConstructor("must ends with {expected}", array("expected" => $needle));
            throw call_user_func_array($e[0], $e[1]);
        }

        return $this;
    }
}
