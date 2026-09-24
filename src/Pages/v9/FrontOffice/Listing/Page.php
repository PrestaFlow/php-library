<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Listing;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'pageTitle' => '#js-product-list-header',
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
