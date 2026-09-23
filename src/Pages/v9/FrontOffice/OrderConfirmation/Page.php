<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\OrderConfirmation;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Order confirmation';

    public function defineSelectors()
    {
        return [
            // Best-effort PS 9 confirmation page — corrected live.
            // #content-hook_order_confirmation and #order-reference-value only
            // exist in the classic theme. PS 9 ships hummingbird as the default
            // and it marks neither — so both selectors also list a
            // theme-agnostic fallback. body#order-confirmation comes from the
            // layout's <body id="{$page.page_name}">, which every theme honours.
            'confirmationBlock' => '#content-hook_order_confirmation, body#order-confirmation',
            'orderReference' => '#order-reference-value, .order-confirmation__details-list li:first-child',
        ];
    }

    /**
     * This is always called right after a navigation, and isVisible() defaults
     * to a 1 second budget — too tight when the checkout redirects
     * asynchronously. The One Page Checkout submits over fetch and only then
     * sets window.location.href, so waitForPageReload() can return before that
     * navigation has even started, leaving the confirmation page to load inside
     * the one second window. Hence an explicit, generous timeout.
     */
    public function isConfirmed(int $timeout = 10000): bool
    {
        return $this->isVisible($this->getSelector('confirmationBlock'), $timeout);
    }

    public function getOrderReference(): string
    {
        $text = trim($this->getTextContent($this->getSelector('orderReference')));

        // The confirmation renders a label like "Référence de la commande : XXXX"
        // (or "Order reference: XXXX"); keep only the reference code that follows.
        if (str_contains($text, ':')) {
            $text = trim(substr($text, strrpos($text, ':') + 1));
        }

        return $text;
    }
}
