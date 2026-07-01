<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class CreateProductAndVerify extends Scenario
{
    public $params = [
        'productName' => 'PrestaFlow Scenario Product',
        'productPrice' => 9.99,
        'productQuantity' => 10,
    ];

    public function steps($testSuite)
    {
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Products');
        $testSuite->importPage('FrontOffice\Product');

        extract($testSuite->pages);

        $testSuite
        ->it('log in to the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('create and enable a product', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->createProduct(
                $this->getParam('productName'),
                (float) $this->getParam('productPrice'),
                (int) $this->getParam('productQuantity')
            );
            $backOfficeProductsPage->enableProduct();

            $this->store('productId', $backOfficeProductsPage->getCreatedProductId());
        })
        ->it('verify the product on the FrontOffice', function () use ($frontOfficeProductPage) {
            $frontOfficeProductPage->goToProduct((int) $this->retrieve('productId'));

            Expect::that($frontOfficeProductPage->getTitle())->contains($this->getParam('productName'));
        })
        ->it('delete the product from the BackOffice', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($this->getParam('productName'));
            $backOfficeProductsPage->deleteProduct(1);
        });

        return $testSuite;
    }
}
