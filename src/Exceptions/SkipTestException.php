<?php

namespace PrestaFlow\Library\Exceptions;

/**
 * Thrown from inside a running test to report it as skipped, with a reason,
 * instead of failed: the shop lacks what the test needs (no offline payment
 * method, no wishlist module...) and that is not a defect of the shop.
 *
 * With $skipRemaining, every later test of the suite is skipped too, with the
 * same reason: the steps after a checkout that could not be paid for have
 * nothing left to check.
 */
class SkipTestException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $reason, public readonly bool $skipRemaining = false)
    {
        parent::__construct($reason);
    }
}
