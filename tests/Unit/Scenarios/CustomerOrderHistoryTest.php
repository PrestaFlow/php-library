<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class CustomerOrderHistoryTest extends TestCase
{
    public function test_defaults(): void
    {
        $params = (new \ReflectionClass(\PrestaFlow\Library\Scenarios\CustomerOrderHistory::class))->getDefaultProperties()['params'];

        $this->assertNull($params['customerEmail']);
        $this->assertNull($params['productUrl']);
        $this->assertFalse($params['useLastOrder']);
        // PS_OS_PAYMENT on every install: the status that issues the invoice.
        $this->assertSame(2, $params['orderStatusId']);
    }

    public function test_the_history_page_resolves_through_the_shop(): void
    {
        $page = (new \ReflectionClass(\PrestaFlow\Library\Pages\v9\FrontOffice\OrderHistory\Page::class))->getDefaultProperties();

        $this->assertSame('order-history', $page['url']);
        $this->assertSame('history', $page['shopPage']);
    }
}
