<?php

namespace PrestaFlow\Library\Tests\Suites\Scenarios;

use PrestaFlow\Library\Tests\TestsSuite;

class CartVoucher extends TestsSuite
{
    public function init()
    {
        $this
        ->describe('Cart voucher: a PrestaFlow promo code lowers the cart total, and removing it brings the total back')
        ->scenario(\PrestaFlow\Library\Scenarios\CartVoucher::class);
    }
}
