<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\Customer;

use PrestaFlow\Library\Pages\v9\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'emailInput' => 'input[name="customer[email]"]',
            'saveButton' => 'form[name="customer"] #save-button',
        ];
    }
}
