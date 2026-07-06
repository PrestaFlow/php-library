<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

class ManageOrder extends Scenario
{
    public $params = [
        'locale' => 'fr',
        // Status label as shown in the BackOffice dropdown — the admin employee's
        // language is English on this shop (only the FrontOffice is French).
        'orderStatus' => 'Payment accepted',
        'internalNote' => 'PrestaFlow internal note',
        'trackingNumber' => 'PF-TRACK-0001',
        // Optional: order reference to manage; defaults to the one stored by a
        // preceding CheckoutOrder scenario.
        'orderReference' => null,
    ];

    public function steps($testSuite)
    {
        $testSuite->params['locale'] = $this->params['locale'] ?? 'fr';

        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\Orders');
        $testSuite->importPage('BackOffice\OrderView');

        extract($testSuite->pages);

        $testSuite
        ->it('open the order in the BackOffice', function () use ($backOfficeLoginPage, $backOfficeOrdersPage) {
            $reference = $this->retrieve('orderReference') ?? $this->getParam('orderReference');

            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            $backOfficeOrdersPage->goTo();
            $backOfficeOrdersPage->filterByReference($reference);
            $backOfficeOrdersPage->openOrder(1);
        })
        ->it('change the order status', function () use ($backOfficeOrderViewPage) {
            $backOfficeOrderViewPage->updateStatus($this->getParam('orderStatus'));

            Expect::that($backOfficeOrderViewPage->hasStatusInHistory($this->getParam('orderStatus')))->equals(true);
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
