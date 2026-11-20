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
        // null => the first category of the main menu (else the sitemap's).
        'categoryId' => null,
        'productIndex' => 1,
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
            $categoryId = $this->getParam('categoryId');
            if ($categoryId !== null && $categoryId !== '') {
                $frontOfficeCategoryPage->goToPage('category', (int) $categoryId);
            } else {
                Expect::that($frontOfficeCategoryPage->goToDiscoveredCategory())->equals(true);
            }

            Expect::that($frontOfficeCategoryPage->getListingTitle())->isNotEmpty();
            Expect::that($frontOfficeCategoryPage->countProducts() > 0)->equals(true);
        })
        ->it('go to product page', function () use ($frontOfficeCategoryPage, $frontOfficeProductPage) {
            $index = (int) $this->getParam('productIndex');

            // Read before the click: the product page must be the one the
            // miniature advertised. Miniature titles may be truncated ("..."),
            // and the product heading may be upper-cased by the theme's CSS.
            $name = (string) $frontOfficeCategoryPage->getProductName($index);
            $name = mb_strtolower(trim(rtrim(trim($name), '.…')));

            Expect::that($name)->isNotEmpty();

            $frontOfficeCategoryPage->goToProduct($index);

            Expect::that(mb_strtolower((string) $frontOfficeProductPage->getTitle()))->contains($name);
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
