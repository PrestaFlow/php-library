PHP Expect - Assertion Library for PHP
======================================

> **Vendored copy.** This is a bundled copy of `nunzion/php-expect` (MIT,
> by Henning Dieterichs), originally hosted at
> https://bitbucket.org/nunzion/php-expect. It is vendored into PrestaFlow
> because the upstream package is no longer reliably installable via Composer.
> Only `src/Nunzion/` is kept; it is autoloaded through PrestaFlow's
> `composer.json` (`Nunzion\` PSR-4 mapping). See `LICENSE` for terms.

PHP Expect is a lightweight assertion library for PHP to validate arguments and invariants.

Installation
------------
You can use [Composer](http://getcomposer.org/) to download and install PHP Expect.
To add PHP Expect to your project, simply add a dependency on nunzion/php-expect to your project's `composer.json` file.

Here is a minimal example of a `composer.json` file that just defines a dependency on PHP Expect:

``` json
{
    "require": {
        "nunzion/php-expect": "~1.0"
    }
}
```

Usage
-----

For larger projects the `Expect` class should be derived to keep dependencies to `Nunzion\Expect` in one place:

``` php
<?php

namespace MyCompany\MyApplication;

class Expect extends \Nunzion\Expect
{
    //If you need custom validations, you can define them here.
}

//Later you can call:
\MyCompany\MyApplication\Expect::that(3)->isBetween(1, 4);
//Instead of \Nunzion\Expect::that(3)->isBetween(1, 4);

```

### Supported Tests

``` php
<?php

use MyCompany\MyApplication\Expect;

Expect::that(null)->isNull();
Expect::that(0)->isNotNull(); // 0 !== null, so 0 is not null.
Expect::that("")->isEmpty();
Expect::that(2)->isNotEmpty();

Expect::that("foo")->equals("foo"); //"foo" == "foo"
Expect::that(1)->isTheSameAs(1); //1 === 1

Expect::that("bar")->isString();
Expect::that(4)->isInt();
Expect::that(new Foo())->isObject();
Expect::that("file_exists")->isCallable();
Expect::that(array(1, 2))->isArray();
Expect::that($a)->isTypeOf("\FooInterface");

//Chaining is possible too
Expect::that(1)->isBetween(0, 2)
               ->isGreaterThan(0)
               ->isLessThan(2)
               ->isGreaterThanOrEqualTo(1)
               ->isLessThanOrEqualTo(1);

$arr = array("bla" => 2, "foo" => null);
Expect::that($arr)->itsArrayElement("bla")->isDefined();
Expect::that($arr)->itsArrayElement("bla")->isInt();
Expect::that($arr)->itsArrayElement("blubb")->isUndefinedOrInt();
Expect::that($arr)->itsArrayElement("foo")->isNullOrInt();

$result = myTest($a); //Some custom test.
if ($result != null)
{
    //Will throw an \UnexpectedValueException with message:
    //"The value must be valid, the test returned: " + $result.
    Expect::that(null)->_("must be valid, the test returned: {testResult}", 
            array("testResult" => $result));
    //The value for the placeholder 'testResult' will be available within 
    //the exception and could be used by loggers.
}

```


### Code Example

``` php
<?php

use Nunzion\Expect;

class Square
{
    private $a;
    private $b;

    public function __construct($length)
    {
        //throws \InvalidArgumentException,
        //because "length" is a parameter of Square::__construct
        Expect::that($length)->isGreaterThan(0);

        $this->a = $length;
        $this->b = $length;
    }

    public function getArea()
    {
        //throws Nunzion\InvariantException,
        //because "b" is a member of Square
        Expect::that($this->b)->equals($this->a); 

        return $this->a * $this->b;
    }

    public function setData($data)
    {
        //Expects data is array and has a key 'length'
        //which is int and greater than 0
        Expect::that($data)->itsArrayElement("length")->isGreaterThan(0);

        $this->a = $data["length"];
        $this->b = $this->a;
    }
}

new Square(-1);

```

### Preconditions, Invariants and Normal Conditions

PHP Expect distinguishes between preconditions, invariants and normal conditions.

Preconditions validate arguments - invalid arguments can be caused by bugs inside the callers code or by bad user input.
If a condition fails and the value to check was a parameter (the expression inside `that()`), PHP Expect automatically throws an `\InvalidArgumentException`.

Invariants are conditions which ensure that the state of an object is always valid.
PHP Expect identifies such conditions if the value to check is a member of `$this` and throws a `Nunzion\InvariantViolationException` if the condition fails.
Usually bugs cause violated invariants, so these exceptions should be logged.

Normal conditions are all conditions which PHP Expect does not recognize.

### isNullOr and isUndefinedOr

You can prepend each is* method call with `isNullOr` and `isUndefinedOr`. The test will then succeed if the value to test is either `null` or `undefined`.

### Extending Nunzion\Expect

You can extend Nunzion\Expect to customize the exceptions to throw. This can be done by overwriting the `get*ExceptionConstructor` methods:

``` php
<?php

namespace MyCompany;

class Expect extends Nunzion\Expect
{
    protected function getPreconditionViolationExceptionConstructor(
                $message, $arguments)
    {
        print_r($arguments); //arguments contains additional information
        //Return the exception construct information you want to throw instead.
        //Delaying the construction of the exception will prevent its stack trace 
        //to contain too much PHP Expect methods.
        return array(
              //the method which will be called to create the exception.
              array(new \ReflectionClass(
                "\MyNamespace\MyInvariantViolationException"), "newInstance"), 
              //the arguments which will be passed to the method.
              array($message)
          );
    }

    protected function getInvariantViolationExceptionConstructor(
                $message, $arguments)
    {
        print_r($arguments);
        return parent::getInvariantViolationExceptionConstructor(
                $message, $arguments);
    }

    protected function getConditionViolationExceptionConstructor(
                $message, $arguments)
    {
        print_r($arguments);
        return parent::getConditionViolationExceptionConstructor(
                $message, $arguments);
    }
}

```

Author
------
Henning Dieterichs - henning.dieterichs@hediet.de

License
-------
PHP Expect is licensed under the MIT License.