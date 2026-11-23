<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Open a category, open one of its products, add it to the cart.
 *
 * Nothing here depends on the demo catalogue or the shop's language: the
 * category is found on the shop, and the product page is checked against the
 * name its miniature showed, not against a fixed label.
 */
class AddProductToCart extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => the first category of the main menu (else the sitemap's)
        // that lists products.
        'categoryId' => null,
        // Optional: checked against the listing title when given.
        'categoryTitle' => null,
        // null => the first product of the category that can be added to the
        // cart as it is; a position (1-based) opens that miniature instead.
        'productIndex' => null,
        // Optional: checked against the product page title when given.
        'productTitle' => null,
        'cartQuantity' => 1,
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $testSuite->importPage('FrontOffice\Category');
        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');

        extract($testSuite->pages);

        $testSuite
        ->it('go to category page', function () use ($frontOfficeCategoryPage) {
            // categoryId when given, else the first category of the shop that
            // lists products (skips the suite when there is none).
            $frontOfficeCategoryPage->goToCategory($this->getParam('categoryId'));

            Expect::that($frontOfficeCategoryPage->getListingTitle())->isNotEmpty();
            // Only checked when the suite passes a categoryTitle.
            $frontOfficeCategoryPage->expectTitleContains($this->getParam('categoryTitle'));
            Expect::that($frontOfficeCategoryPage->countProducts() > 0)->equals(true);
        })
        ->it('go to product page', function () use ($frontOfficeCategoryPage, $frontOfficeProductPage) {
            // The miniature at productIndex when given, else the first product
            // that can be added to the cart (skips the suite when there is
            // none); answers the name the miniature showed.
            $name = $frontOfficeCategoryPage->openProductToBuy($this->getParam('productIndex'));

            Expect::that($name)->isNotEmpty();
            // Case and a truncating "..." ignored: themes upper-case headings.
            $frontOfficeProductPage->expectTitleContains($name);
            // Only checked when the suite passes a productTitle.
            $frontOfficeProductPage->expectTitleContains($this->getParam('productTitle'));
        })
        ->it('add to cart', function () use ($frontOfficeProductPage, $frontOfficeCartPage) {
            $modalTitle = $frontOfficeProductPage->addToCart((int) $this->getParam('cartQuantity'));

            Expect::that($modalTitle)->isNotEmpty();

            $frontOfficeCartPage->goToCart();

            Expect::that($frontOfficeCartPage->hasItems())->equals(true);
        });

        return $testSuite;
    }
}
