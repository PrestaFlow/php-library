<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\Product;

use PrestaFlow\Library\Pages\v8\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'productTitle' => 'h1',
        ];
    }

    public function defineMessages()
    {
        return [
        ];
    }

    public function getTitle()
    {
        return $this->getTextContent($this->selector('productTitle'));
    }

    public function getProductTitle()
    {
        return $this->getTitle();
    }
}
