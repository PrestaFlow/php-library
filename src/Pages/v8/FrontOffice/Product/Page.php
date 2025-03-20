<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\Product;

use PrestaFlow\Library\Pages\v8\FrontOffice\Page as BasePage;

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
            'addedToCart' => 'Produit ajouté au panier avec succès',
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
