<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class AddProductToCart extends Scenario
{
    public function steps($testSuite)
    {
        $testSuite->importPage('FrontOffice\Category');
        $testSuite->importPage('FrontOffice\Product');

        extract($testSuite->pages);

        return $testSuite
        ->it('go to category page', function () use ($frontOfficeCategoryPage) {
            $frontOfficeCategoryPage->goToPage('category', 3);

            $categoryTitle = 'Vêtements';
            Expect::that($frontOfficeCategoryPage->getListingTitle())->contains($categoryTitle);
        })
        ->it('go to product page', function () use ($frontOfficeCategoryPage, $frontOfficeProductPage) {
            $frontOfficeCategoryPage->goToProduct(1);

            $productTitle = 'T-shirt imprimé colibri';
            Expect::that($frontOfficeProductPage->getTitle())->contains($productTitle);
        })
        ->it('add to cart', function () use ($frontOfficeProductPage) {
            $textResult = $frontOfficeProductPage->addToCart(2);

            Expect::that($textResult)->contains($frontOfficeProductPage->message('addedToCart'));
        });
    }
}
