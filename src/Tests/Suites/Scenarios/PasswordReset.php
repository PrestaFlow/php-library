<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class PasswordReset extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Password reset: ask for a reset link for the project\'s customer account (the password is not changed)')
        ->scenario(\PrestaFlow\Library\Scenarios\PasswordReset::class);
    }
}
