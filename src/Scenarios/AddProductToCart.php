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
            $categoryId = $this->getParam('categoryId');
            if ($categoryId !== null && $categoryId !== '') {
                $frontOfficeCategoryPage->goToPage('category', (int) $categoryId);
            } elseif (!$frontOfficeCategoryPage->goToDiscoveredCategory()) {
                $this->skipWithReason('No category of this shop lists products: set the categoryId param.', skipRemaining: true);
            }

            $title = $frontOfficeCategoryPage->getListingTitle();

            Expect::that($title)->isNotEmpty();
            if (is_string($this->getParam('categoryTitle')) && $this->getParam('categoryTitle') !== '') {
                Expect::that($title)->contains($this->getParam('categoryTitle'));
            }
            Expect::that($frontOfficeCategoryPage->countProducts() > 0)->equals(true);
        })
        ->it('go to product page', function () use ($frontOfficeCategoryPage, $frontOfficeProductPage) {
            $index = $this->getParam('productIndex');
            if ($index !== null && $index !== '') {
                // Read before the click: the product page must be the one the
                // miniature advertised.
                $name = (string) $frontOfficeCategoryPage->getProductName((int) $index);
                $frontOfficeCategoryPage->goToProduct((int) $index);
            } else {
                $found = $frontOfficeCategoryPage->openPurchasableProduct();
                if ($found === null) {
                    $this->skipWithReason(Scenario::NO_PURCHASABLE_PRODUCT, skipRemaining: true);
                }
                $name = $found['name'];
            }

            // Miniature titles may be truncated ("..."), and the product
            // heading may be upper-cased by the theme's CSS.
            $name = mb_strtolower(trim(rtrim(trim($name), '.…')));
            $title = mb_strtolower((string) $frontOfficeProductPage->getTitle());

            Expect::that($name)->isNotEmpty();
            Expect::that($title)->contains($name);
            if (is_string($this->getParam('productTitle')) && $this->getParam('productTitle') !== '') {
                Expect::that($title)->contains(mb_strtolower($this->getParam('productTitle')));
            }
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
