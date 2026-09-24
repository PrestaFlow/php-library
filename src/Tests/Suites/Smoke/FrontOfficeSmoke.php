<?php

namespace PrestaFlow\Library\Tests\Suites\Smoke;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * The smallest suite worth running against every supported PrestaShop.
 *
 * Deliberately not a checkout. The point is to fail EARLY and SPECIFICALLY on a
 * version whose markup differs, so the failure names the page. A tunnel reports
 * the same "stuck at step 2" for a dozen unrelated causes.
 *
 * Nothing here is pinned to a fixture: no product URL, no category id. The
 * suite walks whatever catalogue the shop has, so it is as valid on 1.7.8.11 as
 * on 9.2.0 — and when it fails on one of them, that failure is the finding.
 */
class FrontOfficeSmoke extends TestsSuite
{
    public function init()
    {
        $this->importPage('FrontOffice\Home');
        $this->importPage('FrontOffice\Listing');
        $this->importPage('FrontOffice\Product');

        extract($this->pages);

        $this
        ->describe('Front office smoke')
        ->it('the home page renders', function () use ($frontOfficeHomePage) {
            $frontOfficeHomePage->goToPage('home');

            Expect::that($frontOfficeHomePage->isDisplayed())->equals(true);
        })
        ->it('reach the product listing from the home page', function () use ($frontOfficeHomePage, $frontOfficeListingPage) {
            // Reads the "all products" link out of the home page and follows it,
            // so a theme that renames that link fails here and names Home.
            $frontOfficeHomePage->goToAllProducts();

            Expect::that($frontOfficeListingPage->getListingTitle())->notEquals('');
        })
        ->it('open a product and read its price', function () use ($frontOfficeListingPage, $frontOfficeProductPage) {
            $frontOfficeListingPage->goToProduct(1);

            // A price above zero proves three things at once: the listing linked
            // to a real product, the product page matched its price element, and
            // parsePrice() understood the rendered format.
            Expect::that($frontOfficeProductPage->getPrice() > 0)->equals(true);
        });
    }
}
