<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * The catalogue search finds a product by a word of its name.
 *
 * Discovers a product that can be ordered, takes the longest word of its
 * name (4 letters or more, any script), then searches for it twice: through
 * the shop's search page (prestashop.urls.pages.search with s=) and through
 * the header search field of the ps_searchbar module. Each time, the search
 * must answer at least one product, the original one among them. Products
 * are compared by id (data-id-product), never by their wording.
 *
 * A shop whose header has no search field skips that step only. Nothing is
 * created on the shop.
 */
class ProductSearch extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => the first product of the shop that can be added to the cart
        // as it is. Set a product path or URL to pin one product.
        'productUrl' => null,
        // null => the longest word (4 letters or more) of the product's name.
        'searchWord' => null,
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Search');

        extract($testSuite->pages);

        $testSuite
        ->it('find a product to search for', function () use ($frontOfficeProductPage, $frontOfficeSearchPage) {
            // Skips the suite when no product of the shop can be added to the cart.
            $frontOfficeProductPage->goToPurchasableProduct($this->getParam('productUrl'));

            $this->store('productId', $frontOfficeProductPage->getProductId());
            // Skips the suite when the name has no word of 4 letters or more.
            $this->store('searchWord', $frontOfficeSearchPage->pickSearchWord($frontOfficeProductPage->getProductName(), $this->getParam('searchWord')));

            Expect::that($this->retrieve('productId'))->isGreaterThan(0);
            Expect::that($this->retrieve('searchWord'))->isNotEmpty();
        })
        ->it('the search page finds the product', function () use ($frontOfficeSearchPage) {
            $frontOfficeSearchPage->searchFor($this->retrieve('searchWord'));

            Expect::that($frontOfficeSearchPage->isSearchPage())->equals(true);
            Expect::that($frontOfficeSearchPage->countResults())->isGreaterThan(0);
            Expect::that($frontOfficeSearchPage->hasResultProduct($this->retrieve('productId')))->equals(true);
        })
        ->it('the header search field finds the product', function () use ($frontOfficeSearchPage) {
            $frontOfficeSearchPage->goToPage('index');

            // Skips this step only when the header has no search field.
            $frontOfficeSearchPage->requireHeaderSearch();
            $frontOfficeSearchPage->searchFromHeader($this->retrieve('searchWord'));

            Expect::that($frontOfficeSearchPage->isSearchPage())->equals(true);
            Expect::that($frontOfficeSearchPage->countResults())->isGreaterThan(0);
            Expect::that($frontOfficeSearchPage->hasResultProduct($this->retrieve('productId')))->equals(true);
        });

        return $testSuite;
    }
}
