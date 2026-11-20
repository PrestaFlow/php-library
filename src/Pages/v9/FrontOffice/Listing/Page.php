<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Listing;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            // The heading, not the header block. `#js-product-list-header` is a
            // container: on Classic it also holds the category description, and
            // on hummingbird the subcategory nav as well, so reading it returned
            // a whitespace blob ("Home Clothes Accessories Art") that satisfied
            // any non-emptiness check while naming nothing. The `h1` inside it
            // is the category name on 1.7.8, 8.2 and 9.2, Classic and
            // hummingbird alike — which is why Category already overrode this
            // selector with exactly this value.
            'pageTitle' => '#js-product-list-header h1',
            'productArticle' => '#js-product-list .products div:nth-child(${index}) article',
            // The whole path down to the anchor, on purpose: appending the link
            // part in PHP would put it out of reach of the theme layer, and the
            // miniature title is not a shared class across themes (Classic has
            // `.product-title a`, hummingbird `a.product-miniature__title`).
            'productArticleLink' => '#js-product-list .products div:nth-child(${index}) article .product-title a',
            // Wishlist
            'productAddToWishlist' => '#js-product-list .products div:nth-child(${index}) article button.wishlist-button-add',
            'wishlistModal' => '.wishlist-add-to .wishlist-modal.show',
            'wishlistModalListItem' => '.wishlist-add-to .wishlist-modal.show ul.wishlist-list li.wishlist-list-item:nth-child(1)',
            'wishlistToast' => '.wishlist-toast .wishlist-toast-text',
        ];
    }

    public function defineMessages()
    {
        return [
            'addedToWishlist' => $this->translate('Product added'),
        ];
    }

    public function getListingTitle()
    {
        return $this->getTitle();
    }

    /** Number of product miniatures in the listing. */
    public function countProducts(): int
    {
        return count($this->readProductMiniatures('#js-product-list'));
    }

    /** Name shown on the nth miniature (1-based), null when there is none. */
    public function getProductName(int $index = 1): ?string
    {
        return $this->readProductMiniatures('#js-product-list')[$index - 1]['name'] ?? null;
    }

    /** Product page URL of the nth miniature (1-based), null when there is none. */
    public function getProductUrl(int $index = 1): ?string
    {
        return $this->readProductMiniatures('#js-product-list')[$index - 1]['url'] ?? null;
    }

    /**
     * Open a category found on the shop (main menu, else sitemap), for
     * callers that must not assume a category id. False when none was found.
     */
    public function goToDiscoveredCategory(): bool
    {
        $url = $this->discoverListingUrls(allProducts: false)[0] ?? null;
        if ($url === null) {
            return false;
        }

        $this->goToUrlInPlace($url);
        $this->waitForPageLoaded();

        return true;
    }

    /**
     * Click the nth product miniature and wait for the product page.
     *
     * Returns whether the click found its target: navigateTo() answers false on
     * a selector that matched nothing, and swallowing that answer here is how a
     * missing link turned into a failure three steps later, on the product page.
     */
    public function goToProduct(int $index = 1)
    {
        $clicked = $this->navigateTo($this->selector('productArticleLink', ['index' => $index]));

        // waitForNavigation() waits on a navigation started by navigate(); this
        // one is started by a click, so it needs waitForPageReload().
        $this->waitForPageReload();

        return $clicked;
    }

    /** Whether the wishlist module (blockwishlist) shows its buttons on this listing. */
    public function hasWishlistButtons(): bool
    {
        try {
            return $this->getPage()->evaluate(
                '!!document.querySelector(".wishlist-button-add, .wishlist-button, [class*=\\"wishlist\\"]")'
            )->getReturnValue() === true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function addToWishList($index)
    {
        if (!$this->isAddedToWishlist($index)) {
            // Click on the heart
            $this->click($this->selector('productAddToWishlist', ['index' => $index]));
            // Wait for the modal
            $this->elementIsVisible($this->selector('wishlistModal'));
            // Click on the first wishlist
            $this->click($this->selector('wishlistModalListItem'));
            // Wait for the toast
            $this->elementIsVisible($this->selector('wishlistToast'));

            return $this->getTextContent($this->selector('wishlistToast'));
        }

        return $this->message('addedToWishlist');
    }

    public function isAddedToWishlist($index)
    {
        return 'favorite' === $this->getTextContent($this->selector('productAddToWishlist', ['index' => $index]));
    }
}
