<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Product;

use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $url = '{index}-product.html';

    public function defineSelectors()
    {
        $selectors = parent::defineSelectors();

        $pageSelectors = [
            'quantityWantedInput' => '#quantity_wanted',
            'currentProductPrice' => '.product-price',
            'addToCartButton' => '.add-to-cart',
            'modalTitle' => '#myModalLabel',
        ];

        return [...$selectors, ...$pageSelectors];
    }

    public function defineMessages()
    {
        return [
            'addedToCart' => $this->translate('Product successfully added to your shopping cart'),
        ];
    }

    public function goToProduct(int $productId = 0)
    {
        $this->goToPage('product', $productId);

        $this->waitForPageLoaded();
    }

    /**
     * Navigate to a product by its canonical FrontOffice path (e.g.
     * "1-1-hummingbird-printed-t-shirt.html"). PrestaShop friendly URLs cannot
     * be rebuilt from the id alone, so scenarios pass the known path.
     */
    public function goToProductPath(string $path)
    {
        if (preg_match('#^https?://#i', $path)) {
            $this->goToUrl($path);

            return;
        }

        $base = rtrim($this->getGlobal('FO_URL'), '/') . '/';
        $this->goToUrl($base . ltrim($path, '/'));
    }

    /**
     * Open $productUrl (a path or an absolute URL) when given, otherwise the
     * first product of the shop that can be added to the cart (findPurchasable()).
     * When nothing was given and nothing qualifies, skips the test and the
     * rest of the suite (NO_PURCHASABLE_PRODUCT). Returns true otherwise.
     */
    public function goToPurchasableProduct(?string $productUrl = null): bool
    {
        if ($productUrl !== null && $productUrl !== '') {
            $this->goToProductPath($productUrl);

            return true;
        }

        // findPurchasable() leaves the browser on the product it found.
        // Navigating there again would hang: its URL often carries the
        // combination as a fragment (#/1-size-s), and a same-document
        // navigation never fires the load event waited for.
        if ($this->findPurchasable() === null) {
            throw new SkipTestException(self::NO_PURCHASABLE_PRODUCT, skipRemaining: true);
        }

        return true;
    }

    public function getPrice()
    {
        $price = $this->getTextContent($this->getSelector('currentProductPrice'));

        if (!is_string($price)) {
            return $price;
        }

        return $this->parsePrice($price);
    }

    /**
     * Whether this product page lets a buyer add the product as it is: an
     * add-to-cart button, enabled (PrestaShop disables it when the product is
     * out of stock, not available for order, or waits for a required
     * customization), and no required customization field.
     */
    public function isAddableToCart(): bool
    {
        return $this->pfIsAddableToCartHere($this->getSelector('addToCartButton'));
    }

    /** Skip reason of requireOutOfStockRefused(). */
    public const OUT_OF_STOCK_ORDERABLE = 'This shop accepts orders for out-of-stock products'
        . ' (PS_ORDER_OUT_OF_STOCK, or stock management is disabled): an out-of-stock product stays orderable.';

    /**
     * The product as the page embeds it for the theme's JS (data-product on
     * #product-details in Classic, .js-product-details in hummingbird;
     * PrestaShop 1.7 to 9, any theme built on the core templates): quantity, availability, allow_oosp... [] when absent.
     */
    public function getProductData(): array
    {
        try {
            $json = $this->getPage()->evaluate(
                '(function(){var e=document.querySelector("#product-details[data-product]")'
                . '||document.querySelector(".js-product-details[data-product]")||document.querySelector("[data-product]");'
                . 'return e?e.getAttribute("data-product"):null;})()'
            )->getReturnValue();
        } catch (\Throwable) {
            return [];
        }

        $data = is_string($json) ? json_decode($json, true) : null;

        return is_array($data) ? $data : [];
    }

    /**
     * Whether the shop lets this product be ordered without stock: the
     * product's own setting, else PS_ORDER_OUT_OF_STOCK, or no stock
     * management at all (allow_oosp in the embedded product data).
     */
    public function acceptsOutOfStockOrders(): bool
    {
        return !empty($this->getProductData()['allow_oosp']);
    }

    /**
     * Skip this test (only) when the shop accepts orders for out-of-stock
     * products: there, a product without stock rightly stays orderable.
     */
    public function requireOutOfStockRefused(): void
    {
        if ($this->acceptsOutOfStockOrders()) {
            throw new SkipTestException(self::OUT_OF_STOCK_ORDERABLE);
        }
    }

    /**
     * URL of the first product of the shop a buyer can add to the cart as it
     * is, so scenarios do not depend on the demo catalogue. Browses the
     * listings discoverListingUrls() finds (or $listingUrls), opening at most
     * $maxProducts product pages. Null when none qualifies. Leaves the browser
     * on the product found.
     */
    public function findPurchasable(int $maxProducts = 10, array $listingUrls = []): ?string
    {
        if ($listingUrls === []) {
            $listingUrls = $this->discoverListingUrls();
        }

        $seen = [];
        foreach ($listingUrls as $listingUrl) {
            try {
                $this->goToUrlInPlace($listingUrl);
            } catch (\Throwable) {
                continue;
            }

            $found = $this->pfOpenFirstPurchasable($maxProducts, $seen, $this->getSelector('addToCartButton'));
            if ($found !== null) {
                return $found['url'];
            }
            if (count($seen) >= $maxProducts) {
                return null;
            }
        }

        return null;
    }

    public function addToCart(int $quantity = 1)
    {
        $this->setValue($this->getSelector('quantityWantedInput'), $quantity);

        $this->click($this->getSelector('addToCartButton'));

        return ltrim($this->getTextContent($this->getSelector('modalTitle')));
    }
}
