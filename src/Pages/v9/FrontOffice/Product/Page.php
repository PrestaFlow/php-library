<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Product;

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
     * False when nothing was given and nothing qualifies.
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
        return $this->findPurchasable() !== null;
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
     * Pull the number out of a rendered price.
     *
     * The element rarely holds digits alone: there is a currency symbol, and on
     * hummingbird a visually-hidden "Price:" label that getText() returns too,
     * so the text reads "Price: €14.28".
     *
     * The previous implementation stripped three currency symbols, swapped every
     * comma for a dot and called floatval(). That answered 0.0 on the
     * hummingbird markup, because floatval() stops at the first character it
     * cannot read — and it was already wrong above a thousand on every theme:
     * "1 234,56 €" came out as 1.0 and "$1,234.56" as 1.234.
     *
     * Rules: take the first run of digits and separators, drop whatever precedes
     * it, then decide which separator is decimal. When both appear, the last one
     * is decimal and the other groups thousands. When only one appears, it is
     * decimal unless exactly three digits follow it, which reads as grouping —
     * "1.234" is therefore 1234, the one genuinely ambiguous case.
     */
    public function parsePrice(string $text): float
    {
        if (!preg_match('/\d[\d.,\s\x{00A0}\x{202F}]*/u', $text, $matches)) {
            return 0.0;
        }

        $number = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $matches[0]);
        $number = rtrim($number, '.,');

        $lastDot = strrpos($number, '.');
        $lastComma = strrpos($number, ',');

        if ($lastDot !== false && $lastComma !== false) {
            $decimal = $lastDot > $lastComma ? '.' : ',';
        } elseif ($lastDot !== false || $lastComma !== false) {
            $position = $lastDot !== false ? $lastDot : $lastComma;
            $decimal = (strlen($number) - $position - 1) === 3
                ? ''
                : ($lastDot !== false ? '.' : ',');
        } else {
            $decimal = '';
        }

        if ($decimal === '') {
            return (float) str_replace(['.', ','], '', $number);
        }

        $grouping = $decimal === '.' ? ',' : '.';

        return (float) str_replace($decimal, '.', str_replace($grouping, '', $number));
    }

    /**
     * Whether this product page lets a buyer add the product as it is: an
     * add-to-cart button, enabled (PrestaShop disables it when the product is
     * out of stock, not available for order, or waits for a required
     * customization), and no required customization field.
     */
    public function isAddableToCart(): bool
    {
        $script = sprintf(
            '(function(sel){var b=document.querySelector(\'[data-button-action="add-to-cart"]\')||document.querySelector(sel);'
            . 'if(!b)return false;'
            . 'if(b.disabled||b.hasAttribute("disabled")||b.classList.contains("disabled"))return false;'
            . 'if(document.querySelector(".product-customization [required]"))return false;'
            . 'return true;})(%s)',
            json_encode($this->getSelector('addToCartButton'))
        );

        try {
            return $this->getPage()->evaluate($script)->getReturnValue() === true;
        } catch (\Throwable) {
            return false;
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

            foreach ($this->readProductMiniatures('#js-product-list') as $miniature) {
                $url = $miniature['url'] ?? null;
                if (!is_string($url) || isset($seen[$url])) {
                    continue;
                }
                if (count($seen) >= $maxProducts) {
                    return null;
                }
                $seen[$url] = true;

                try {
                    $this->goToUrlInPlace($url);
                } catch (\Throwable) {
                    continue;
                }

                if ($this->isAddableToCart()) {
                    return $url;
                }
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
