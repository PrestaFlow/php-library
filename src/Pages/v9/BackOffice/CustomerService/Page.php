<?php

namespace PrestaFlow\Library\Pages\v9\BackOffice\CustomerService;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

/**
 * Customers > Customer Service (AdminCustomerThreads), a legacy list in 1.7,
 * 8 and 9: the messages customers send through the contact form.
 */
class Page extends BasePage
{
    public string $pageTitle = 'Customer Service';
    public string $menuSelector = '#subtab-AdminCustomerThreads';
    public string $parentMenuSelector = '#subtab-AdminParentCustomerThreads';

    public function defineSelectors()
    {
        return [
            // HelperList of the customer_thread table, newest thread first.
            'threadRows' => '#table-customer_thread tbody tr',
        ];
    }

    public function goTo(): void
    {
        $this->goToSubMenu($this->parentMenuSelector, $this->menuSelector);
    }

    /**
     * Whether a thread from $email is listed on the first page of the list
     * (newest first, so a message just sent is there).
     */
    public function hasThreadFrom(string $email): bool
    {
        try {
            $this->getPage()->waitUntilContainsElement($this->getSelector('threadRows'), 5000);
        } catch (\Throwable) {
            return false;
        }

        return $this->getPage()->evaluate(sprintf(
            '(function(){var m=%s.toLowerCase();return [].slice.call(document.querySelectorAll(%s))'
            . '.some(function(r){return r.textContent.toLowerCase().indexOf(m)!==-1;});})()',
            json_encode($email),
            json_encode($this->getSelector('threadRows'))
        ))->getReturnValue() === true;
    }
}
