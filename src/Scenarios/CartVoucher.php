<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Expects\Expect;

/**
 * A promo code lowers the cart total, and removing it brings the total back.
 *
 * Creates its own cart rule in the back office (a name and a code unique to
 * the run, available once, no restriction), adds a product of the shop that
 * can be ordered to the cart as a guest, applies the code, checks that the
 * total went down, removes the code, checks that the total is back, then
 * deletes the rule.
 *
 * Amounts are compared as numbers read from the page (parsePrice()): no
 * currency, separator or language is assumed.
 *
 * After a failed step the library skips the steps that follow, the deletion
 * included. The rule left behind is then deleted by the next run, which
 * starts by clearing the rules named like its own.
 */
class CartVoucher extends Scenario
{
    public $params = [
        // null => the suite's locale (the project's language in the app).
        'locale' => null,
        // null => the first product of the shop that can be added to the cart
        // as it is. Set a product path or URL to pin one product.
        'productUrl' => null,
        // null => 'PrestaFlow voucher <unique suffix>'.
        'ruleName' => null,
        // null => 'PFTEST<UNIQUE SUFFIX>'.
        'code' => null,
        'reductionPercent' => 10,
        // A fixed amount off the order (default currency, tax included)
        // instead of the percentage; null keeps the percentage.
        'reductionAmount' => null,
    ];

    /** What every rule of this scenario is named after, for the cleanup. */
    public const RULE_NAME_PREFIX = 'PrestaFlow voucher ';

    public function steps($testSuite)
    {
        if (is_string($this->params['locale'] ?? null) && $this->params['locale'] !== '') {
            $testSuite->params['locale'] = $this->params['locale'];
        }

        $suffix = self::pfUniqueSuffix();
        $this->params['ruleName'] = ($this->params['ruleName'] ?? null) ?: self::RULE_NAME_PREFIX . $suffix;
        $this->params['code'] = ($this->params['code'] ?? null) ?: 'PFTEST' . strtoupper(str_replace('-', '', $suffix));

        $testSuite->importPage('FrontOffice\Product');
        $testSuite->importPage('FrontOffice\Cart');
        $testSuite->importPage('BackOffice\Login');
        $testSuite->importPage('BackOffice\CartRules');

        extract($testSuite->pages);

        $testSuite
        ->it('add a product to the cart', function () use ($frontOfficeProductPage, $frontOfficeCartPage) {
            // Skips the suite when no product of the shop can be added to the
            // cart: nothing has been created on the shop yet.
            $frontOfficeProductPage->goToPurchasableProduct($this->getParam('productUrl'));
            $frontOfficeProductPage->addToCart(1);
            $frontOfficeCartPage->goToCart();

            Expect::that($frontOfficeCartPage->countLines())->isGreaterThan(0);
        })
        ->it('log in to the BackOffice', function () use ($backOfficeLoginPage) {
            $backOfficeLoginPage->goToPage('index');
            $backOfficeLoginPage->login();

            Expect::that($backOfficeLoginPage->isLoggedIn())->equals(true);
        })
        ->it('create a cart rule with a code', function () use ($backOfficeCartRulesPage) {
            // What an earlier run left behind when it stopped before its cleanup.
            $backOfficeCartRulesPage->deleteCartRulesNamed('PrestaFlow voucher ');

            $backOfficeCartRulesPage->createCartRule(
                $this->getParam('ruleName'),
                $this->getParam('code'),
                (float) $this->getParam('reductionPercent'),
                $this->getParam('reductionAmount')
            );

            Expect::that($backOfficeCartRulesPage->getErrorMessage())->equals('');
            Expect::that($backOfficeCartRulesPage->countCartRulesNamed($this->getParam('ruleName')))->equals(1);
        })
        ->it('apply the code: the cart total goes down', function () use ($frontOfficeCartPage) {
            $frontOfficeCartPage->goToCart();
            // Skips this check when the cart page shows no promo code field.
            $frontOfficeCartPage->requireVoucherForm();

            $this->store('totalBefore', $frontOfficeCartPage->getCartTotal());
            $frontOfficeCartPage->applyVoucher($this->getParam('code'));

            Expect::that($frontOfficeCartPage->getVoucherError())->equals('');
            Expect::that($frontOfficeCartPage->countVouchers())->equals(1);
            Expect::that($frontOfficeCartPage->getCartTotal())->isLessThan($this->retrieve('totalBefore'));
        })
        ->it('remove the code: the cart total comes back', function () use ($frontOfficeCartPage) {
            $frontOfficeCartPage->requireVoucherForm();

            $frontOfficeCartPage->removeVoucher(1);

            Expect::that($frontOfficeCartPage->countVouchers())->equals(0);
            Expect::that($frontOfficeCartPage->getCartTotal())->equals($this->retrieve('totalBefore'));
        })
        ->it('delete the cart rule from the BackOffice', function () use ($backOfficeCartRulesPage) {
            $this->store('deleted', $backOfficeCartRulesPage->deleteCartRulesNamed($this->getParam('ruleName')));

            Expect::that($this->retrieve('deleted'))->equals(1);
            Expect::that($backOfficeCartRulesPage->countCartRulesNamed($this->getParam('ruleName')))->equals(0);
        });

        return $testSuite;
    }
}
