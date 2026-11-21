<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v9\BackOffice\CartRules\Page as CartRulesPage;
use PrestaFlow\Library\Pages\v9\FrontOffice\Cart\Page as CartPage;
use PrestaFlow\Library\Scenarios\CartVoucher;

final class CartVoucherTest extends TestCase
{
    public function test_defaults_are_discovered_or_unique(): void
    {
        $params = (new \ReflectionClass(CartVoucher::class))->getDefaultProperties()['params'];

        foreach (['locale', 'productUrl', 'ruleName', 'code', 'reductionAmount'] as $key) {
            $this->assertArrayHasKey($key, $params);
            $this->assertNull($params[$key], $key);
        }
        $this->assertSame(10, $params['reductionPercent']);
    }

    public function test_the_cleanup_prefix_matches_the_rule_names(): void
    {
        $this->assertSame('PrestaFlow voucher ', CartVoucher::RULE_NAME_PREFIX);

        // The cleanup step passes the prefix as a literal (the app editor
        // reads steps as page calls): it must stay the constant's value.
        $source = (string) file_get_contents((new \ReflectionClass(CartVoucher::class))->getFileName());
        $this->assertStringContainsString("deleteCartRulesNamed('PrestaFlow voucher ')", $source);
    }

    public function test_the_cart_rules_page_exists_for_every_version(): void
    {
        foreach (['v7', 'v8'] as $version) {
            $class = "PrestaFlow\\Library\\Pages\\$version\\BackOffice\\CartRules\\Page";
            $this->assertTrue(class_exists($class), $class);
            $this->assertTrue(is_subclass_of($class, CartRulesPage::class), $class);
        }

        $page = (new \ReflectionClass(CartRulesPage::class))->getDefaultProperties();
        $this->assertSame('#subtab-AdminCartRules', $page['menuSelector']);
        $this->assertSame('#subtab-AdminParentCartRules', $page['parentMenuSelector']);
    }

    public function test_the_voucher_skip_reason_is_a_sentence(): void
    {
        $this->assertSame('This shop shows no promo code field on its cart page.', CartPage::NO_VOUCHER_FORM);
    }
}
