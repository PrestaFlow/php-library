<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * The cart follows its quantities: as a guest, add a product, raise its
 * quantity, lower it, then remove it.
 *
 * Amounts are compared as numbers read from the page (parsePrice()), never as
 * formatted strings: no currency, separator or language is assumed. The
 * products subtotal is checked rather than the grand total, which may also
 * move with shipping.
 */
class CartQuantities extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => the first product of the shop that can be added to the cart
        // as it is. Set a product path or URL to pin one product.
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
        ->it('add a product to the cart', function () use ($frontOfficeProductPage, $frontOfficeCartPage) {
            // Skips the suite when no product of the shop can be added to the cart.
            $frontOfficeProductPage->goToPurchasableProduct($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart(1);
            $frontOfficeCartPage->goToCart();

            Expect::that($frontOfficeCartPage->countLines())->equals(1);
            Expect::that($frontOfficeCartPage->getLineQuantity(1))->equals(1);
            Expect::that($frontOfficeCartPage->getLineTotal(1))->isGreaterThan(0);
        })
        ->it('raise the quantity: the line and the cart follow', function () use ($frontOfficeCartPage) {
            $lineBefore = $frontOfficeCartPage->getLineTotal(1);
            $subtotalBefore = $frontOfficeCartPage->getProductsSubtotal();

            $frontOfficeCartPage->increaseLineQuantity(1);

            Expect::that($frontOfficeCartPage->getLineQuantity(1))->equals(2);
            Expect::that($frontOfficeCartPage->getLineTotal(1))->isGreaterThan($lineBefore);
            // The subtotal moved by exactly what the line moved.
            Expect::that(round($frontOfficeCartPage->getProductsSubtotal() - $subtotalBefore, 2))
                ->equals(round($frontOfficeCartPage->getLineTotal(1) - $lineBefore, 2));
        })
        ->it('lower the quantity: the line and the cart follow', function () use ($frontOfficeCartPage) {
            $lineBefore = $frontOfficeCartPage->getLineTotal(1);
            $subtotalBefore = $frontOfficeCartPage->getProductsSubtotal();

            $frontOfficeCartPage->decreaseLineQuantity(1);

            Expect::that($frontOfficeCartPage->getLineQuantity(1))->equals(1);
            Expect::that($frontOfficeCartPage->getLineTotal(1))->isLessThan($lineBefore);
            Expect::that(round($frontOfficeCartPage->getProductsSubtotal() - $subtotalBefore, 2))
                ->equals(round($frontOfficeCartPage->getLineTotal(1) - $lineBefore, 2));
        })
        ->it('remove the product: the cart is empty', function () use ($frontOfficeCartPage) {
            $frontOfficeCartPage->removeLine(1);

            Expect::that($frontOfficeCartPage->countLines())->equals(0);
        });

        return $testSuite;
    }
}
