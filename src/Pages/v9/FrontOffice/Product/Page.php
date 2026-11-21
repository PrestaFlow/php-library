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
            // Attribute inputs of a product with combinations: a select, radio
            // buttons or color swatches, each named group[<id_attribute_group>]
            // and carrying data-product-attribute (core templates of Classic,
            // 1.7 to 9, and hummingbird; the core JS listens to them).
            'variantInput' => '.js-product-variants [data-product-attribute], .product-variants [data-product-attribute]',
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

    /** Skip reason of goToProductWithCombinations(). */
    public const NO_PRODUCT_WITH_COMBINATIONS = 'No product with combinations that can be added to the cart'
        . ' was found among the first products of this shop.';

    /**
     * Whether the product page open now offers a choice of combination: at
     * least one attribute (size, color...) with two values or more.
     */
    public function hasCombinations(): bool
    {
        foreach ($this->pfAttributeGroups() as $group) {
            if (count($group['values']) >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * URL of the first product of the shop that has combinations and can be
     * added to the cart as it is (same browsing as findPurchasable()). Null
     * when none qualifies within $maxProducts product pages. Leaves the
     * browser on the product found.
     */
    public function findProductWithCombinations(int $maxProducts = 20, array $listingUrls = []): ?string
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

            $found = $this->pfOpenFirstPurchasable(
                $maxProducts,
                $seen,
                $this->getSelector('addToCartButton'),
                fn (): bool => $this->hasCombinations()
            );
            if ($found !== null) {
                return $found['url'];
            }
            if (count($seen) >= $maxProducts) {
                return null;
            }
        }

        return null;
    }

    /**
     * Open $productUrl (a path or an absolute URL) when given, otherwise the
     * first product of the shop with combinations that can be added to the
     * cart (findProductWithCombinations()). When nothing was given and
     * nothing qualifies, skips the test and the rest of the suite
     * (NO_PRODUCT_WITH_COMBINATIONS). Returns true otherwise.
     */
    public function goToProductWithCombinations(?string $productUrl = null): bool
    {
        if ($productUrl !== null && $productUrl !== '') {
            $this->goToProductPath($productUrl);

            return true;
        }

        // Like goToPurchasableProduct(): the browser already is on the product.
        if ($this->findProductWithCombinations() === null) {
            throw new SkipTestException(self::NO_PRODUCT_WITH_COMBINATIONS, skipRemaining: true);
        }

        return true;
    }

    /** Id of the product open now (data-product), 0 when unknown. */
    public function getProductId(): int
    {
        return (int) ($this->getProductData()['id_product'] ?? 0);
    }

    /**
     * Id of the combination selected now (id_product_attribute of the
     * embedded product data, which the theme refreshes on every attribute
     * change), 0 when the product has none or it is unknown.
     */
    public function getCombinationId(): int
    {
        return (int) ($this->getProductData()['id_product_attribute'] ?? 0);
    }

    /**
     * Values of the combination selected now, as the shop names them in its
     * own language, from the embedded product data: sorted and joined with
     * " / " (e.g. "Black / M"), to compare with
     * Cart::getLineCombinationValues(). "" when none.
     */
    public function getCombinationValues(): string
    {
        $values = [];
        foreach ((array) ($this->getProductData()['attributes'] ?? []) as $attribute) {
            if (is_array($attribute) && isset($attribute['name']) && is_string($attribute['name'])) {
                $values[] = trim($attribute['name']);
            }
        }
        sort($values);

        return implode(' / ', $values);
    }

    /**
     * Switch to another combination that can be added to the cart: tries the
     * other values of each attribute in turn (select, radio or color input)
     * and waits for the theme's AJAX refresh of the combination. Stops on the
     * first one whose id differs from the one selected before and whose
     * add-to-cart button is enabled (in stock or orderable). False when none
     * qualifies.
     */
    public function selectOtherCombination(int $timeout = 10000): bool
    {
        $initial = $this->getCombinationId();

        foreach ($this->pfAttributeGroups() as $name => $group) {
            foreach ($group['values'] as $value) {
                if ($value === $group['selected']) {
                    continue;
                }

                $current = $this->getCombinationId();
                $this->pfChooseAttributeValue($name, $value);

                // The new combination lands with the refreshed product data;
                // a combination that does not exist may leave it unchanged.
                if (!$this->waitForJsCondition(sprintf(
                    '(function(){var e=document.querySelector("#product-details[data-product]")'
                    . '||document.querySelector(".js-product-details[data-product]");'
                    . 'if(!e)return false;var d=JSON.parse(e.getAttribute("data-product"));'
                    . 'return parseInt(d.id_product_attribute,10)!==%d;})()',
                    $current
                ), $timeout)) {
                    continue;
                }
                $this->waitForStable(3000);

                $combination = $this->getCombinationId();
                if ($combination > 0 && $combination !== $initial && $this->isAddableToCart()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Attribute groups of the product open now, by input name
     * (group[<id>]): ['values' => [value ids], 'selected' => value id].
     */
    private function pfAttributeGroups(): array
    {
        $script = sprintf(
            '(function(sel){var g={};[].slice.call(document.querySelectorAll(sel)).forEach(function(e){'
            . 'var n=e.getAttribute("name");if(!n)return;g[n]=g[n]||{values:[],selected:null};'
            . 'if(e.tagName==="SELECT"){[].slice.call(e.options).forEach(function(o){if(!o.disabled)g[n].values.push(o.value);});'
            . 'g[n].selected=e.value;}'
            . 'else if(e.type==="radio"){if(!e.disabled)g[n].values.push(e.value);if(e.checked)g[n].selected=e.value;}});'
            . 'return JSON.stringify(g);})(%s)',
            json_encode($this->getSelector('variantInput'))
        );

        try {
            $groups = json_decode((string) $this->getPage()->evaluate($script)->getReturnValue(), true);
        } catch (\Throwable) {
            return [];
        }

        return is_array($groups) ? $groups : [];
    }

    /**
     * Choose $value for the attribute input named $name the way a buyer
     * would: a change event on the select, a click on the radio (its label
     * often hides it, hence the click from the page).
     */
    private function pfChooseAttributeValue(string $name, string $value): void
    {
        $this->getPage()->evaluate(sprintf(
            '(function(sel,name,value){var inputs=[].slice.call(document.querySelectorAll(sel)).filter(function(e){'
            . 'return e.getAttribute("name")===name;});'
            . 'inputs.forEach(function(e){'
            . 'if(e.tagName==="SELECT"){e.value=value;e.dispatchEvent(new Event("change",{bubbles:true}));}'
            . 'else if(e.value===value){e.click();}});})(%s,%s,%s)',
            json_encode($this->getSelector('variantInput')),
            json_encode($name),
            json_encode($value)
        ));
    }

    public function addToCart(int $quantity = 1)
    {
        $this->setValue($this->getSelector('quantityWantedInput'), $quantity);

        $this->click($this->getSelector('addToCartButton'));

        // The confirmation modal opens once the add-to-cart request answered.
        // 3 s (getTextContent()'s default) was too short on a busy shop: the
        // caller then navigated to the cart while the request was still in
        // flight, and the cart came up empty (seen once in a CI-like run).
        $title = $this->getTextContent($this->getSelector('modalTitle'), 1, true, 10000);

        return ltrim(is_string($title) ? $title : '');
    }
}
