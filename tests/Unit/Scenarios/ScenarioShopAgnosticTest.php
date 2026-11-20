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
        $this->assertNull($params['productIndex']);
        // Still accepted, and checked, when a suite passes them.
        $this->assertNull($params['categoryTitle']);
        $this->assertNull($params['productTitle']);
    }

    public function test_no_payment_reason_names_the_modules_looked_for(): void
    {
        $reason = Scenario::pfNoPaymentMethodReason(['ps_wirepayment', 'ps_checkpayment']);

        $this->assertStringContainsString('No offline payment method', $reason);
        $this->assertStringContainsString('ps_wirepayment, ps_checkpayment', $reason);
        $this->assertStringContainsString('paymentModules', $reason);
    }

    public function test_carrier_id_param(): void
    {
        $this->assertNull(Scenario::pfCarrierIdParam(null));
        $this->assertNull(Scenario::pfCarrierIdParam(''));
        $this->assertNull(Scenario::pfCarrierIdParam('abc'));
        $this->assertSame(3, Scenario::pfCarrierIdParam(3));
        $this->assertSame(7, Scenario::pfCarrierIdParam('7'));
    }

    public function test_unique_email_and_suffix_differ_on_every_call(): void
    {
        $emails = array_map(fn () => Scenario::pfUniqueEmail('pf-guest'), range(1, 20));

        $this->assertCount(20, array_unique($emails));
        foreach ($emails as $email) {
            $this->assertMatchesRegularExpression('/^pf-guest-\d{8}-\d{6}-[0-9a-f]{6}@example\.com$/', $email);
            $this->assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
        }

        $this->assertNotSame(Scenario::pfUniqueSuffix(), Scenario::pfUniqueSuffix());
    }

    public function test_data_created_on_the_shop_is_unique_by_default(): void
    {
        foreach (['GuestCheckout', 'OnePageCheckoutGuest'] as $name) {
            $this->assertNull($this->defaults($name)['guestEmail'], $name);
        }
        foreach (['CreateProductAndVerify', 'EditProduct'] as $name) {
            $this->assertNull($this->defaults($name)['productName'], $name);
        }
    }

    public function test_manage_order_picks_the_status_by_id(): void
    {
        $params = $this->defaults('ManageOrder');

        // PS_OS_PAYMENT on a fresh install, in every language.
        $this->assertSame(2, $params['orderStatusId']);
        $this->assertNull($params['orderStatus']);
    }

    public function test_shop_id_comes_from_the_project_unless_given(): void
    {
        foreach (['CheckoutOrder', 'GuestCheckout', 'OnePageCheckoutGuest', 'OnePageCheckoutOrder', 'SwitchTheme'] as $name) {
            $this->assertNull($this->defaults($name)['shopId'], $name);
        }

        $this->assertSame(1, Scenario::pfShopIdFromGlobals([]));
        $this->assertSame(1, Scenario::pfShopIdFromGlobals(['SHOP_ID' => 0]));
        $this->assertSame(1, Scenario::pfShopIdFromGlobals(['SHOP_ID' => 'x']));
        $this->assertSame(2, Scenario::pfShopIdFromGlobals(['SHOP_ID' => '2']));
    }
}
