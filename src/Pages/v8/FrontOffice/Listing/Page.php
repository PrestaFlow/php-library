<?php

namespace PrestaFlow\Library\Pages\v8\FrontOffice\Listing;

use PrestaFlow\Library\Pages\v8\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'pageTitle' => '#js-product-list-header',
            'productArticle' => '#js-product-list .products div:nth-child(${index}) article',
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
            //'addedToWishlist' => 'Product added',
            'addedToWishlist' => 'Produit ajouté',
        ];
    }

    public function getListingTitle()
    {
        return $this->getTitle();
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
