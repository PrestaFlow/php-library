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
        // A page with no selectors of its own: everything it resolves comes
        // from the area base, which is exactly what the theme _common block
        // has to reach.
        $this->importPage('FrontOffice\Stores');

        extract($this->pages);

        $this
        ->describe('Front office smoke')
        ->it('the home page renders', function () use ($frontOfficeHomePage) {
            $frontOfficeHomePage->goToPage('home');

            Expect::that($frontOfficeHomePage->isDisplayed())->equals(true);
        })
        // notEquals('') was satisfied by anything at all, including the home
        // page and the whitespace blob the listing header used to return on
        // hummingbird. Cross-checking the heading against the document title
        // is locale-agnostic and comes from an independent server-side render
        // (meta title vs. rendered h1), so it fails both when the heading
        // selector drifts back to the container and when we never left home.
        ->it('reach the product listing from the home page', function () use ($frontOfficeHomePage, $frontOfficeListingPage) {
            $frontOfficeHomePage->goToAllProducts();

            $title = $frontOfficeListingPage->getListingTitle();

            Expect::that($title)->isNotEmpty();
            Expect::that($frontOfficeListingPage->getMetaTitle())->contains($title);
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

            $title = $frontOfficeCategoryPage->getListingTitle();

            Expect::that($title)->isNotEmpty();
            Expect::that($frontOfficeCategoryPage->getMetaTitle())->contains($title);
        })
        ->it('open a product from the category listing', function () use ($frontOfficeCategoryPage, $frontOfficeProductPage) {
            $frontOfficeCategoryPage->goToProduct(1);

            Expect::that($frontOfficeProductPage->getPrice() > 0)->equals(true);
        })
        /*
         * The header chrome is declared once on FrontOfficePage and inherited
         * by all 31 front-office pages, so nothing page-specific ever asserted
         * it. Measured on a live 9.2 shop, both selectors miss on hummingbird
         * on every page -- a whole theme's worth of markup that no suite
         * touched. Asserting them here puts the area-wide selectors under the
         * same matrix as everything else, on a page that declares none of its
         * own.
         */
        ->it('the shared header chrome resolves on this theme', function () use ($frontOfficeStoresPage) {
            $frontOfficeStoresPage->goToPage('stores');

            Expect::that($frontOfficeStoresPage->elementIsVisible($frontOfficeStoresPage->getSelector('desktopLogo'), 5000))->equals(true);
            Expect::that($frontOfficeStoresPage->elementIsVisible($frontOfficeStoresPage->getSelector('userInfoLink'), 5000))->equals(true);
        })
        /*
         * accountLink only exists once a session is open, so every anonymous
         * probe reported it missing on BOTH themes -- the one shape that looks
         * like "no divergence" and hides one. Measured against a logged-in
         * session it misses on hummingbird and matches on Classic, which is why
         * it needs the scenario below rather than another anonymous step.
         *
         * Registration, not EnsureTestAccount: the latter logs in with the
         * FO_EMAIL / FO_PASSWD defaults, which match PrestaShop's demo customer
         * (pub@prestashop.com / 123456789) and therefore only work on a shop
         * that carries demo data. Its register-if-missing fallback cannot stand
         * in, because 9.2 rejects that same password -- the shop answers "The
         * minimum score must be: Strong" -- so on a shop without the fixture,
         * such as a duplicated second shop, both branches fail. Registration
         * creates its own account with a unique address and a policy-compliant
         * password, so it needs nothing from the shop's fixtures.
         */
        ->scenario(\PrestaFlow\Library\Scenarios\Registration::class)
        ->it('the logged-in header chrome resolves on this theme', function () use ($frontOfficeStoresPage) {
            $frontOfficeStoresPage->goToPage('stores');

            Expect::that($frontOfficeStoresPage->elementIsVisible($frontOfficeStoresPage->getSelector('accountLink'), 5000))->equals(true);
        });
    }
}
