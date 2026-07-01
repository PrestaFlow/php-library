<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice\Product;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $url = '{index}-product.html';

    public function defineSelectors()
    {
        $selectors = parent::defineSelectors();

        $pageSelectors = [
            'quantityWantedInput' => '#quantity_wanted',
            'currentProductPrice' => '.product-price',
            'addToCartButton' => '.add-to-cart',
            'modalTitle' => '#myModalLabel',
        ];

        return [...$selectors, ...$pageSelectors];
    }

    public function defineMessages()
    {
        return [
            'addedToCart' => $this->translate('Product successfully added to your shopping cart'),
        ];
    }

    public function goToProduct(int $productId = 0)
    {
        $this->goToPage('product', $productId);

        $this->waitForNavigation();
    }

    public function getPrice()
    {
        $price = $this->getTextContent($this->getSelector('currentProductPrice'));

        if (is_string($price)) {
            $price = trim(str_replace(['$', '€', '£'], '', $price));
            $price = floatval(str_replace(',', '.', $price));
        }

        return $price;
    }

    public function addToCart(int $quantity = 1)
    {
        $this->setValue($this->getSelector('quantityWantedInput'), $quantity);

        $this->click($this->getSelector('addToCartButton'));

        return ltrim($this->getTextContent($this->getSelector('modalTitle')));
    }
}
