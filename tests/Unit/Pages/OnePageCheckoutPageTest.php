<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class OnePageCheckoutPageTest extends TestCase
{
    private const PAGE_CLASS = 'PrestaFlow\\Library\\Pages\\v9\\FrontOffice\\OnePageCheckout\\Page';

    public function testPageExistsAndExtendsFrontOfficeBasePage(): void
    {
        $this->assertTrue(class_exists(self::PAGE_CLASS));
        $this->assertTrue(is_subclass_of(self::PAGE_CLASS, 'PrestaFlow\\Library\\Pages\\Common\\FrontOffice\\Page'));
    }

    public function testPageTargetsTheOrderController(): void
    {
        $defaults = (new \ReflectionClass(self::PAGE_CLASS))->getDefaultProperties();
        $this->assertSame('order', $defaults['url']);
        $this->assertSame('Checkout', $defaults['pageTitle']);
    }

    public function testPageExposesTheCheckoutActions(): void
    {
        foreach ([
            'isOnePageCheckoutActive',
            'continueAsGuest',
            'selectAddress',
            'fillNewAddress',
            'selectFirstCarrier',
            'selectFirstPayment',
            'acceptTerms',
            'placeOrder',
        ] as $method) {
            $this->assertTrue(method_exists(self::PAGE_CLASS, $method), $method);
        }
    }
}
