<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Home;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public function defineSelectors()
    {
        return [
            'homePageSection' => '#content.page-home',
            'maintenanceBlock' => '#content.page-maintenance',
            'desktopLogo' => '#_desktop_logo',
            'allProductsLink' => '#content section.featured-products:nth-of-type(1) .all-product-link',
        ];
    }

    /**
     * Whether the home page rendered its own content section.
     *
     * Distinct from "the request returned 200": a maintenance page, an error
     * page and a redirect to another shop all answer 200 too.
     */
    public function isDisplayed(): bool
    {
        return $this->elementIsVisible($this->getSelector('homePageSection'), 5000);
    }

    /**
     * Open the "all products" listing linked from the featured products block.
     * A shop without that block (module off, other theme) gets the first
     * listing discoverListingUrls() finds instead: a menu category, the
     * sitemap's, then the root category.
     */
    public function goToAllProducts()
    {
        $this->goToPage('home');

        $url = $this->getAllProductsUrl() ?? ($this->discoverListingUrls()[0] ?? null);
        if ($url === null) {
            return;
        }

        $this->getPage()->navigate($url)->waitForNavigation();
    }

    /** href of the featured products' "all products" link, null without one. */
    public function getAllProductsUrl(): ?string
    {
        try {
            $elem = $this->getPage()->dom()->querySelector($this->selector('allProductsLink'));
            $url = $elem?->getAttribute('href');
        } catch (\Throwable) {
            return null;
        }

        return is_string($url) && $url !== '' ? $url : null;
    }
}
