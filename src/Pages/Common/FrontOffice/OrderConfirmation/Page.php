<?php

namespace PrestaFlow\Library\Pages\Common\FrontOffice\OrderConfirmation;

use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Order confirmation';

    public function defineSelectors()
    {
        return [
            // Best-effort PS 9 confirmation page — corrected live.
            'confirmationBlock' => '#content-hook_order_confirmation',
            'orderReference' => '#order-reference-value',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->isVisible($this->getSelector('confirmationBlock'));
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
