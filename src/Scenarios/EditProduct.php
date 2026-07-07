<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class EditProduct extends Scenario
{
    public $params = [
        'productName' => 'PF Edit Test',
        'initialPrice' => 9.99,
        'newPrice' => 19.99,
        'quantity' => 10,
    ];

    public function steps($testSuite)
    {
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Products');

        extract($testSuite->pages);

        $testSuite
        ->it('log in to the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('create a product to edit', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->createProduct(
                $this->getParam('productName'),
                (float) $this->getParam('initialPrice'),
                (int) $this->getParam('quantity')
            );
        })
        ->it('open the product from the list and change its price', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($this->getParam('productName'));
            $backOfficeProductsPage->openProduct(1);

            $backOfficeProductsPage->updatePrice((float) $this->getParam('newPrice'));

            Expect::that($backOfficeProductsPage->getFormPrice())->contains((string) $this->getParam('newPrice'));
        })
        ->it('delete the product from the BackOffice', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($this->getParam('productName'));
            $backOfficeProductsPage->deleteProduct(1);
        });

        return $testSuite;
    }
}
