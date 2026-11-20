<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * Health probe of the front office: home, a category, a product, the cart and
 * the order page, as a guest. On each page: an HTTP status below 400, no
 * JavaScript error, no request the server answered with a 5xx, no
 * maintenance page and a document title.
 *
 * Everything is found on the shop (category, product). Nothing is ordered:
 * the walk stops on the order page without submitting any step.
 */
class FrontOfficeHealth extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $testSuite->importPage('FrontOffice\Home');
        $testSuite->importPage('FrontOffice\Category');
        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');

        extract($testSuite->pages);

        $testSuite
        ->it('the home page is healthy', function () use ($frontOfficeHomePage) {
            $frontOfficeHomePage->goToPage('home');

            Expect::that($frontOfficeHomePage->getDocumentStatus())->isBetween(200, 399);
            Expect::that($frontOfficeHomePage->isInMaintenance())->equals(false);
            Expect::that($frontOfficeHomePage->jsErrors())->isEmpty();
            Expect::that($frontOfficeHomePage->failedServerRequests())->isEmpty();
            Expect::that($frontOfficeHomePage->getMetaTitle())->isNotEmpty();
        })
        ->it('a category page is healthy', function () use ($frontOfficeCategoryPage) {
            // Skips the suite when no category of the shop lists products.
            $frontOfficeCategoryPage->goToDiscoveredCategory();

            Expect::that($frontOfficeCategoryPage->getDocumentStatus())->isBetween(200, 399);
            Expect::that($frontOfficeCategoryPage->isInMaintenance())->equals(false);
            Expect::that($frontOfficeCategoryPage->jsErrors())->isEmpty();
            Expect::that($frontOfficeCategoryPage->failedServerRequests())->isEmpty();
            Expect::that($frontOfficeCategoryPage->getMetaTitle())->isNotEmpty();
        })
        ->it('a product page is healthy', function () use ($frontOfficeCategoryPage, $frontOfficeProductPage) {
            // The first product of the category that can be added to the cart;
            // skips the suite when there is none.
            $frontOfficeCategoryPage->openPurchasableProduct();

            Expect::that($frontOfficeProductPage->getDocumentStatus())->isBetween(200, 399);
            Expect::that($frontOfficeProductPage->isInMaintenance())->equals(false);
            Expect::that($frontOfficeProductPage->jsErrors())->isEmpty();
            Expect::that($frontOfficeProductPage->failedServerRequests())->isEmpty();
            Expect::that($frontOfficeProductPage->getMetaTitle())->isNotEmpty();
        })
        ->it('the cart page is healthy', function () use ($frontOfficeProductPage, $frontOfficeCartPage) {
            $frontOfficeProductPage->addToCart(1);
            $frontOfficeCartPage->goToCart();

            Expect::that($frontOfficeCartPage->hasItems())->equals(true);
            Expect::that($frontOfficeCartPage->getDocumentStatus())->isBetween(200, 399);
            Expect::that($frontOfficeCartPage->isInMaintenance())->equals(false);
            Expect::that($frontOfficeCartPage->jsErrors())->isEmpty();
            Expect::that($frontOfficeCartPage->failedServerRequests())->isEmpty();
            Expect::that($frontOfficeCartPage->getMetaTitle())->isNotEmpty();
        })
        ->it('the order page is healthy', function () use ($frontOfficeCartPage) {
            // Opens the order page only: no step of the checkout is submitted,
            // whichever checkout (four steps or one page) the shop runs.
            $frontOfficeCartPage->proceedToCheckout();

            Expect::that($frontOfficeCartPage->isOrderPage())->equals(true);
            Expect::that($frontOfficeCartPage->getDocumentStatus())->isBetween(200, 399);
            Expect::that($frontOfficeCartPage->isInMaintenance())->equals(false);
            Expect::that($frontOfficeCartPage->jsErrors())->isEmpty();
            Expect::that($frontOfficeCartPage->failedServerRequests())->isEmpty();
            Expect::that($frontOfficeCartPage->getMetaTitle())->isNotEmpty();
        });

        return $testSuite;
    }
}
