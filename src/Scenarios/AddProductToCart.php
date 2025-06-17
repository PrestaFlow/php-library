<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class AddProductToCart extends Scenario
{
    public $params = [
        'categoryId' => 3,
        'categoryTitle' => 'Vêtements',
        'productIndex' => 1,
        'productTitle' => 'T-shirt imprimé colibri',
        'cartQuantity' => 1,
    ];

    public function steps($testSuite)
    {
        $testSuite->importPage('FrontOffice\Category');
        $testSuite->importPage('FrontOffice\Product');

        extract($testSuite->pages);

        $testSuite
        ->it('go to category page', function () use ($frontOfficeCategoryPage) {
            $frontOfficeCategoryPage->goToPage('category', (int) $this->getParam('categoryId'));

            Expect::that($frontOfficeCategoryPage->getListingTitle())->contains($this->getParam('categoryTitle'));
        })
        ->it('go to product page', function () use ($frontOfficeCategoryPage, $frontOfficeProductPage) {
            $frontOfficeCategoryPage->goToProduct((int) $this->getParam('productIndex'));

            Expect::that($frontOfficeProductPage->getTitle())->contains($this->getParam('productTitle'));
        })
        ->it('add to cart', function () use ($frontOfficeProductPage) {
            $textResult = $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));

            Expect::that($textResult)->contains($frontOfficeProductPage->message('addedToCart'));
        });

        return $testSuite;
    }
}
