<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * A product without stock cannot be added to the cart.
 *
 * Creates its own product in the back office (quantity 0, a name unique to
 * the run), opens it in the front office, checks that it cannot be added to
 * the cart, then deletes it. Nothing depends on a language: the check reads
 * the add-to-cart button, not an availability label.
 *
 * A shop that accepts orders for out-of-stock products (PS_ORDER_OUT_OF_STOCK,
 * or no stock management) rightly keeps the product orderable: the check is
 * then skipped with a reason, and the product is still deleted.
 *
 * After a failed step the library skips the steps that follow, the deletion
 * included. The product left behind is then deleted by the next run, which
 * starts by clearing products named like its own.
 */
class OutOfStockProduct extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => 'PrestaFlow out-of-stock <unique suffix>'.
        'productName' => null,
        'productPrice' => 9.99,
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $this->params['productName'] = ($this->params['productName'] ?? null) ?: 'PrestaFlow out-of-stock ' . self::pfUniqueSuffix();

        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Products');
        $testSuite->importPage('FrontOffice\Product');

        extract($testSuite->pages);

        $testSuite
        ->it('log in to the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);
        })
        ->it('create a product without stock', function () use ($backOfficeProductsPage) {
            // What an earlier run left behind when it stopped before its cleanup.
            $backOfficeProductsPage->deleteProductsNamed('PrestaFlow out-of-stock ');

            $backOfficeProductsPage->goTo();
            $backOfficeProductsPage->createProduct($this->getParam('productName'), (float) $this->getParam('productPrice'), 0);

            $this->store('productUrl', $backOfficeProductsPage->getCreatedProductUrl());

            Expect::that($this->retrieve('productUrl'))->isNotEmpty();
        })
        ->it('the product cannot be added to the cart', function () use ($frontOfficeProductPage) {
            $frontOfficeProductPage->goToProductPath($this->retrieve('productUrl'));

            // Skips this check when the shop accepts out-of-stock orders.
            $frontOfficeProductPage->requireOutOfStockRefused();

            Expect::that($frontOfficeProductPage->isAddableToCart())->equals(false);
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
