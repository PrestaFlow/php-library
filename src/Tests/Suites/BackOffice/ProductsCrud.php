<?php

namespace PrestaFlow\Library\Tests\Suites\BackOffice;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

class ProductsCrud extends TestsSuite
{
    public function init()
    {
        $this->importPage('BackOffice\Login');
        $this->importPage('BackOffice\Products');

        extract($this->pages);

        $productName = 'PrestaFlow Test Product';

        $this
        ->describe('Create, find and delete a product from the BackOffice')
        ->it('should log in to the BO', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();
        })
        ->it('should create a product', function () use ($backOfficeProductsPage, $productName) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->createProduct($productName, 9.99, 10);

            Expect::that($backOfficeProductsPage->getSuccessMessage())->isNotEmpty();
        })
        ->it('should find the product in the list', function () use ($backOfficeProductsPage, $productName) {
            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->filterByName($productName);

            Expect::that($backOfficeProductsPage->getProductNameInList(1))->contains($productName);
        })
        ->it('should delete the product', function () use ($backOfficeProductsPage) {
            $backOfficeProductsPage->deleteProduct(1);

            Expect::that($backOfficeProductsPage->getSuccessMessage())->isNotEmpty();
        });
    }
}
