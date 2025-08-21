<?php

namespace PrestaFlow\Library\Pages\v7\FrontOffice\Product;

use PrestaFlow\Library\Pages\v7\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'quantityWantedInput' => '#quantity_wanted',
            'currentProductPrice' => '.product-price',
            'addToCartButton' => '.add-to-cart',
            'modalTitle' => '#myModalLabel',
        ];
    }

    public function defineMessages()
    {
        return [
            'addedToCart' => $this->translate('Product successfully added to your shopping cart'),
        ];
    }

    public function getPrice()
    {
        return $this->getTextContent($this->getSelector('currentProductPrice'));
    }

    public function addToCart(int $quantity = 1)
    {
        $this->setValue($this->getSelector('quantityWantedInput'), $quantity);

        $this->click($this->getSelector('addToCartButton'));

        return ltrim($this->getTextContent($this->getSelector('modalTitle')));
    }
}
