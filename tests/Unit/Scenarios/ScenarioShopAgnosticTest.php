<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\FrontOfficePage;
use PrestaFlow\Library\Scenarios\Scenario;

/**
 * The scenarios shipped with the library run on the user's shop: their
 * defaults must not name anything from the demo catalogue, and every value a
 * suite passes must still win.
 */
final class ScenarioShopAgnosticTest extends TestCase
{
    private const ORDER_SCENARIOS = ['CheckoutOrder', 'GuestCheckout', 'OnePageCheckoutGuest', 'OnePageCheckoutOrder'];

    private function defaults(string $scenario): array
    {
        return (new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\' . $scenario))->getDefaultProperties()['params'];
    }

    public function test_order_scenarios_discover_their_product_carrier_and_payment(): void
    {
        foreach (self::ORDER_SCENARIOS as $name) {
            $params = $this->defaults($name);
            $this->assertArrayHasKey('productUrl', $params, $name);
            $this->assertNull($params['productUrl'], $name);
            $this->assertArrayHasKey('carrierId', $params, $name);
            $this->assertNull($params['carrierId'], $name);
            $this->assertSame(FrontOfficePage::OFFLINE_PAYMENT_MODULES, $params['paymentModules'], $name);
        }
    }

    public function test_offline_payment_modules_never_redirect(): void
    {
        $this->assertSame(['ps_wirepayment', 'ps_checkpayment', 'ps_cashondelivery'], FrontOfficePage::OFFLINE_PAYMENT_MODULES);
    }

    public function test_add_product_to_cart_has_no_demo_label(): void
    {
        $params = $this->defaults('AddProductToCart');
        $this->assertNull($params['categoryId']);
        $this->assertArrayNotHasKey('categoryTitle', $params);
        $this->assertArrayNotHasKey('productTitle', $params);
    }

    public function test_no_payment_reason_names_the_modules_looked_for(): void
    {
        $reason = Scenario::noPaymentMethodReason(['ps_wirepayment', 'ps_checkpayment']);

        $this->assertStringContainsString('No offline payment method', $reason);
        $this->assertStringContainsString('ps_wirepayment, ps_checkpayment', $reason);
        $this->assertStringContainsString('paymentModules', $reason);
    }

    public function test_carrier_id_param(): void
    {
        $this->assertNull(Scenario::carrierIdParam(null));
        $this->assertNull(Scenario::carrierIdParam(''));
        $this->assertNull(Scenario::carrierIdParam('abc'));
        $this->assertSame(3, Scenario::carrierIdParam(3));
        $this->assertSame(7, Scenario::carrierIdParam('7'));
    }
}
