<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class EditProduct extends Scenario
{
    public $params = [
        // null => 'PF Edit Test <unique suffix>': a fixed name would match the
        // products earlier runs left behind when filtering the list.
        'productName' => null,
        'initialPrice' => 9.99,
        'newPrice' => 19.99,
        'quantity' => 10,
    ];

    public function steps($testSuite)
    {
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Products');

        $this->params['productName'] = ($this->params['productName'] ?? null) ?: 'PF Edit Test ' . self::uniqueSuffix();

        extract($testSuite->pages);

        $testSuite
        ->it('log in to the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);
        })
        ->it('create a product to edit', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->createProduct(
                $this->getParam('productName'),
                (float) $this->getParam('initialPrice'),
                (int) $this->getParam('quantity')
            );

            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($this->getParam('productName'));

            Expect::that($backOfficeProductsPage->getProductNameInList(1))->contains($this->getParam('productName'));
        })
        ->it('open the product from the list and change its price', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($this->getParam('productName'));
            $backOfficeProductsPage->openProduct(1);

            $backOfficeProductsPage->updatePrice((float) $this->getParam('newPrice'));

            // Numeric: the field may read "19.990000" or "19,99".
            Expect::that($backOfficeProductsPage->getFormPriceValue())->equals(round((float) $this->getParam('newPrice'), 2));
        })
        ->it('delete the product from the BackOffice', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($this->getParam('productName'));
            $backOfficeProductsPage->deleteProduct(1);

            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($this->getParam('productName'));

            Expect::that($backOfficeProductsPage->getListCount())->equals(0);
        });

        return $testSuite;
    }
}
