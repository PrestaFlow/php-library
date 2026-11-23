<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * A product with combinations follows the buyer's choice: as a guest, open a
 * product with combinations, choose another value of one of its attributes,
 * check that the combination changed, add it to the cart, check that the
 * cart line holds that very combination, then remove the line.
 *
 * The product is discovered (the first product of the shop with combinations
 * that can be added to the cart); the suite is skipped with a reason when the
 * first products of the shop have none. Combinations are compared by id
 * (id_product_attribute), and their values as the shop names them: no
 * catalogue, attribute or language is assumed. The price may or may not
 * change with the combination: it is only checked to stay a price.
 *
 * The guest cart is emptied of the line at the end; when a step fails before,
 * the guest cart stays as it is (a guest cart, not tied to any customer).
 */
class ProductCombinations extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => the first product of the shop with combinations that can be
        // added to the cart. Set a product path or URL to pin one product.
        'productUrl' => null,
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');

        extract($testSuite->pages);

        $testSuite
        ->it('open a product with combinations', function () use ($frontOfficeProductPage) {
            // Skips the suite when no product of the shop qualifies.
            $frontOfficeProductPage->goToProductWithCombinations($this->getParam('productUrl'));

            $this->store('productId', $frontOfficeProductPage->getProductId());
            $this->store('initialCombination', $frontOfficeProductPage->getCombinationId());

            Expect::that($frontOfficeProductPage->hasCombinations())->equals(true);
            Expect::that($this->retrieve('productId'))->isGreaterThan(0);
            Expect::that($this->retrieve('initialCombination'))->isGreaterThan(0);
        })
        ->it('choose another combination: its id changes', function () use ($frontOfficeProductPage) {
            Expect::that($frontOfficeProductPage->selectOtherCombination())->equals(true);

            $this->store('combination', $frontOfficeProductPage->getCombinationId());
            $this->store('combinationValues', $frontOfficeProductPage->getCombinationValues());

            Expect::that($this->retrieve('combination'))->notEquals($this->retrieve('initialCombination'));
            Expect::that($this->retrieve('combinationValues'))->isNotEmpty();
            Expect::that($frontOfficeProductPage->isAddableToCart())->equals(true);
            // The price may differ between combinations or not: it only has to stay one.
            Expect::that($frontOfficeProductPage->getPrice())->isGreaterThan(0);
        })
        ->it('add it to the cart: the cart line holds the chosen combination', function () use ($frontOfficeProductPage, $frontOfficeCartPage) {
            $frontOfficeProductPage->addToCart(1);
            $frontOfficeCartPage->goToCart();

            $this->store('line', $frontOfficeCartPage->findLineOfCombination($this->retrieve('productId'), $this->retrieve('combination')));

            Expect::that($this->retrieve('line'))->isGreaterThan(0);
            Expect::that($frontOfficeCartPage->getLineCombinationValues($this->retrieve('line')))->equals($this->retrieve('combinationValues'));
            Expect::that($frontOfficeCartPage->getLineQuantity($this->retrieve('line')))->equals(1);
        })
        ->it('remove the line: the combination leaves the cart', function () use ($frontOfficeCartPage) {
            $frontOfficeCartPage->removeLine($this->retrieve('line'));

            Expect::that($frontOfficeCartPage->findLineOfCombination($this->retrieve('productId'), $this->retrieve('combination')))->equals(0);
        });

        return $testSuite;
    }
}
