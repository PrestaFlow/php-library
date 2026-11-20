<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class ManageOrder extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app). Friendly
        // URLs and their per-language overrides are read in that language.
        'locale' => null,
        // id_order_state to move the order to. 2 is PS_OS_PAYMENT ("Payment
        // accepted") on every install, whatever the employee's language.
        'orderStatusId' => 2,
        // Optional: a status label as the BackOffice list shows it, in the
        // employee's language. When set, it is used instead of orderStatusId.
        'orderStatus' => null,
        'internalNote' => 'PrestaFlow internal note',
        'trackingNumber' => 'PF-TRACK-0001',
        // Optional: order reference to manage; defaults to the one stored by a
        // preceding CheckoutOrder scenario.
        'orderReference' => null,
    ];

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Orders');
        $testSuite->importPage('BackOffice\OrderView');

        extract($testSuite->pages);

        $testSuite
        ->it('open the order in the BackOffice', function () use ($backOfficeLoginPage, $backOfficeOrdersPage, $backOfficeOrderViewPage) {
            $reference = $this->retrieve('orderReference') ?? $this->getParam('orderReference');

            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            $backOfficeOrdersPage->goTo();
            $backOfficeOrdersPage->filterByReference($reference);
            $backOfficeOrdersPage->openOrder(1);

            Expect::that($backOfficeOrderViewPage->getOrderReference())->contains((string) $reference);
        })
        ->it('change the order status', function () use ($backOfficeOrderViewPage) {
            // The label when the suite gives one, else the status id; answers
            // the label as the list shows it.
            $label = $backOfficeOrderViewPage->changeStatus($this->getParam('orderStatusId'), $this->getParam('orderStatus'));

            Expect::that($label)->isNotEmpty();
            Expect::that($backOfficeOrderViewPage->hasStatusInHistory($label))->equals(true);
        })
        ->it('add an internal note', function () use ($backOfficeOrderViewPage) {
            $backOfficeOrderViewPage->setInternalNote($this->getParam('internalNote'));

            Expect::that($backOfficeOrderViewPage->getInternalNote())->contains($this->getParam('internalNote'));
        })
        ->it('set a tracking number', function () use ($backOfficeOrderViewPage) {
            $backOfficeOrderViewPage->addTracking($this->getParam('trackingNumber'));

            Expect::that($backOfficeOrderViewPage->getTracking())->contains($this->getParam('trackingNumber'));
        });

        return $testSuite;
    }
}
