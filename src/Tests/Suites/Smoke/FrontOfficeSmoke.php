<?php

namespace PrestaFlow\Library\Tests\Suites\Smoke;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * The front-office walk every supported version has to survive.
 *
 * Steps are ordered so a failure names the page it happened on, and nothing
 * below a red step can be trusted: the cart steps depend on the add-to-cart
 * step having really added something.
 *
 * Two rules hold every step here together:
 *
 *  - EVERY step asserts. click() answers false on a missing selector instead of
 *    raising, so a step without an assertion reports success having done
 *    nothing at all.
 *  - NOTHING is hardcoded to one catalogue. No product id, no friendly URL, no
 *    theme-specific selector. Category 3 is the single exception: it is
 *    "Clothes" on the demo catalogue of 1.7, 8 and 9 alike.
 */
class FrontOfficeSmoke extends TestsSuite
{
    public function init()
    {
        $this->importPage('FrontOffice\Home');
        $this->importPage('FrontOffice\Listing');
        $this->importPage('FrontOffice\Product');
        $this->importPage('FrontOffice\Cart');
        $this->importPage('FrontOffice\Category');

        extract($this->pages);

        $this
        ->describe('Front office smoke')
        ->it('the home page renders', function () use ($frontOfficeHomePage) {
            $frontOfficeHomePage->goToPage('home');

            Expect::that($frontOfficeHomePage->isDisplayed())->equals(true);
        })
        ->it('reach the product listing from the home page', function () use ($frontOfficeHomePage, $frontOfficeListingPage) {
            $frontOfficeHomePage->goToAllProducts();

            Expect::that($frontOfficeListingPage->getListingTitle())->notEquals('');
        })
        ->it('open a product and read its price', function () use ($frontOfficeListingPage, $frontOfficeProductPage) {
            $frontOfficeListingPage->goToProduct(1);

            Expect::that($frontOfficeProductPage->getPrice() > 0)->equals(true);
        })
        // addToCart() answers the modal title it read after clicking. An empty
        // string (or the `false` getTextContent() returns on a timeout) means
        // either the button selector missed or no confirmation modal opened —
        // both are the same finding: the add-to-cart markup diverged here.
        ->it('add the product to the cart', function () use ($frontOfficeProductPage) {
            $modalTitle = $frontOfficeProductPage->addToCart(1);

            Expect::that($modalTitle)->isNotEmpty();
        })
        // Independent of the modal: proves the cart really holds a line rather
        // than the page merely having shown a confirmation.
        ->it('the cart page holds the added product', function () use ($frontOfficeCartPage) {
            $frontOfficeCartPage->goToCart();

            Expect::that($frontOfficeCartPage->hasItems())->equals(true);
        })
        // Category 3 is "Clothes" everywhere, so this reaches a listing by id
        // without a friendly URL. It also exercises the scalar-param
        // substitution in getPageURL() that f1ad516 fixed.
        ->it('reach a category listing by id', function () use ($frontOfficeCategoryPage) {
            $frontOfficeCategoryPage->goToPage('category', 3);

            Expect::that($frontOfficeCategoryPage->getListingTitle())->notEquals('');
        })
        ->it('open a product from the category listing', function () use ($frontOfficeCategoryPage, $frontOfficeProductPage) {
            $frontOfficeCategoryPage->goToProduct(1);

            Expect::that($frontOfficeProductPage->getPrice() > 0)->equals(true);
        });
    }
}
