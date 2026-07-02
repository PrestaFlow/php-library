<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class FrontOfficeCheckoutTest extends TestCase
{
    private array $globals = [
        'PS_VERSION' => '9.0.0',
        'LOCALE' => 'en',
        'PREFIX_LOCALE' => false,
        'BO' => ['URL' => 'http://localhost/admin/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
        'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
        'DEBUG' => false,
        'VERBOSE' => false,
    ];

    private function make(string $name): object
    {
        $class = 'PrestaFlow\\Library\\Pages\\v9\\FrontOffice\\' . $name . '\\Page';
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $this->globals, customs: []);
    }

    public function testCartHasCheckoutAction(): void
    {
        $page = $this->make('Cart');
        $this->assertArrayHasKey('checkoutButton', $page->selectors);
        $this->assertTrue(method_exists($page, 'proceedToCheckout'));
    }

    public function testCheckoutHasTunnelActions(): void
    {
        $page = $this->make('Checkout');
        foreach (['addressesContinueButton', 'shippingOption', 'shippingContinueButton', 'paymentOption', 'termsCheckbox', 'placeOrderButton'] as $key) {
            $this->assertArrayHasKey($key, $page->selectors, $key);
        }
        foreach (['confirmAddresses', 'chooseShipping', 'choosePaymentAndConfirm'] as $method) {
            $this->assertTrue(method_exists($page, $method), $method);
        }
    }

    public function testOrderConfirmationReadsReference(): void
    {
        $page = $this->make('OrderConfirmation');
        $this->assertArrayHasKey('confirmationBlock', $page->selectors);
        $this->assertArrayHasKey('orderReference', $page->selectors);
        $this->assertTrue(method_exists($page, 'isConfirmed'));
        $this->assertTrue(method_exists($page, 'getOrderReference'));
    }
}
