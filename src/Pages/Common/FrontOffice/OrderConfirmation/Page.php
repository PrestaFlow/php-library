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
        return trim($this->getTextContent($this->getSelector('orderReference')));
    }
}
