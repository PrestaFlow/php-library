<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class ContactForm extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Contact form: send a PrestaFlow test message and find it in the BackOffice customer service')
        ->scenario(\PrestaFlow\Library\Scenarios\ContactForm::class);
    }
}
