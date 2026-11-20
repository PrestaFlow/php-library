<?php

namespace PrestaFlow\Tests\Browser\OrderHistoryFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\OrderHistory\Page as OrderHistoryPage;

    /** The OrderHistory page on a chrome-php page handed in by the test, without a suite. */
    class OrderHistory extends OrderHistoryPage
    {
        public static $chromePage = null;

        public function __construct()
        {
            $this->selectors = $this->defineSelectors();
        }

        public function getPage()
        {
            return self::$chromePage;
        }
    }
}

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Exceptions\SkipTestException;
    use PrestaFlow\Tests\Browser\OrderHistoryFixtures\OrderHistory;

    /** "Order history" as Classic (1.7 to 9, table and mobile list) and hummingbird render it. */
    final class CustomerOrderHistoryBrowserTest extends TestCase
    {
        private static $browser = null;

        public static function tearDownAfterClass(): void
        {
            self::$browser?->close();
            self::$browser = null;
        }

        /** The invoice: a data: URL carrying id_order, like the real pdf-invoice link does. */
        private static function invoiceUrl(): string
        {
            return 'data:application/pdf;id_order=12;base64,' . base64_encode((string) file_get_contents(__DIR__ . '/../Unit/Support/fixtures/facture.pdf'));
        }

        private static function classic(bool $withInvoice): string
        {
            $invoice = $withInvoice ? '<a href="' . self::invoiceUrl() . '"><i>pdf</i></a>' : '-';

            return '<table class="table"><thead><tr><th>Order reference</th><th>Status</th></tr></thead><tbody>'
                . '<tr><th scope="row">KHWLILZLL</th><td>11/20/2026</td><td>€29.00</td><td>Bank wire</td><td>Payment accepted</td>'
                . '<td class="text-sm-center">' . $invoice . '</td><td class="order-actions">'
                . '<a href="https://shop.test/en/order-detail?id_order=12" data-link-action="view-order-details">Details</a>'
                . '<a href="https://shop.test/en/order?submitReorder=&id_order=12">Reorder</a></td></tr>'
                . '<tr><th scope="row">XKBKNABJK</th><td>11/18/2026</td><td>€9.00</td><td>Check</td><td>Awaiting check payment</td><td>-</td>'
                . '<td class="order-actions"><a href="https://shop.test/en/order-detail?id_order=9" data-link-action="view-order-details">Details</a></td></tr>'
                . '</tbody></table>';
        }

        private static function hummingbird(bool $withInvoice): string
        {
            $invoice = $withInvoice ? '<a href="' . self::invoiceUrl() . '" aria-label="View invoice">pdf</a>' : '';

            return '<div class="order-history"><div class="order-history__inner">'
                . '<div class="order-history__header"><span class="order-history__cell">Order reference</span></div>'
                . '<div class="order-history__row"><span class="order-history__cell order-history__cell--reference">KHWLILZLL</span>'
                . '<span class="order-history__cell order-history__cell--invoice">' . $invoice . '</span>'
                . '<span class="order-history__cell order-history__cell--actions">'
                . '<a class="btn" href="https://shop.test/en/order-detail?id_order=12" data-link-action="view-order-details">i</a>'
                . '<a class="btn" href="https://shop.test/en/order?submitReorder=&id_order=12">r</a></span></div>'
                . '<div class="order-history__row"><span class="order-history__cell order-history__cell--reference">XKBKNABJK</span>'
                . '<span class="order-history__cell order-history__cell--actions">'
                . '<a class="btn" href="https://shop.test/en/order-detail?id_order=9" data-link-action="view-order-details">i</a></span></div>'
                . '</div></div>';
        }

        private function history(string $markup): OrderHistory
        {
            if (self::$browser === null) {
                try {
                    self::$browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true]);
                } catch (\Throwable $e) {
                    if (getenv('CI') === false || getenv('CI') === '') {
                        $this->markTestSkipped('Chrome indisponible : ' . $e->getMessage());
                    }
                    $this->fail('Chrome indisponible en CI : ' . $e->getMessage());
                }
            }

            $page = self::$browser->createPage();
            $page->navigate('data:text/html,' . rawurlencode('<meta charset="utf-8"><body>' . $markup . '</body>'))->waitForNavigation();
            OrderHistory::$chromePage = $page;

            return new OrderHistory();
        }

        public static function themes(): array
        {
            return ['classic' => ['classic'], 'hummingbird' => ['hummingbird']];
        }

        /** @dataProvider themes */
        public function test_orders_are_found_by_reference(string $theme): void
        {
            $history = $this->history(self::$theme(false));

            $this->assertSame(['KHWLILZLL', 'XKBKNABJK'], $history->getOrderReferences());
            $this->assertTrue($history->hasOrder('XKBKNABJK'));
            $this->assertFalse($history->hasOrder('ZZZZZZZZZ'));
            $this->assertSame('KHWLILZLL', $history->orderReferenceOrLatest(null));
            $this->assertSame('XKBKNABJK', $history->orderReferenceOrLatest('XKBKNABJK'));
        }

        /** @dataProvider themes */
        public function test_the_invoice_downloads_as_a_pdf(string $theme): void
        {
            $history = $this->history(self::$theme(true));

            $pdf = $history->downloadInvoice('KHWLILZLL');

            $this->assertGreaterThan(0, $pdf->pageCount());
            $this->assertTrue($pdf->contains('Facture'));
        }

        /** @dataProvider themes */
        public function test_no_invoice_skips_the_step_only(string $theme): void
        {
            $history = $this->history(self::$theme(false));

            try {
                $history->downloadInvoice('KHWLILZLL');
                $this->fail('An order without invoice must skip');
            } catch (SkipTestException $e) {
                $this->assertSame('No invoice for this order: invoices are disabled on this shop (PS_INVOICE), or the order status does not generate one.', $e->getMessage());
                $this->assertFalse($e->skipRemaining);
            }
        }

        public function test_an_empty_history_skips_the_suite(): void
        {
            $history = $this->history('<div class="alert alert-info">You have not placed any orders.</div>');

            try {
                $history->orderReferenceOrLatest(null);
                $this->fail('An empty history must skip');
            } catch (SkipTestException $e) {
                $this->assertSame('This customer has no order yet: let the scenario place one (useLastOrder off).', $e->getMessage());
                $this->assertTrue($e->skipRemaining);
            }
        }

        public function test_reusing_the_last_order_skips_the_order_steps(): void
        {
            $history = $this->history('<p>-</p>');

            $history->skipWhenReusingLastOrder(false);
            $history->skipWhenReusingLastOrder(null);

            try {
                $history->skipWhenReusingLastOrder('1');
                $this->fail('useLastOrder must skip');
            } catch (SkipTestException $e) {
                $this->assertSame("Reusing the customer's last order (useLastOrder param): no new order is placed.", $e->getMessage());
                $this->assertFalse($e->skipRemaining);
            }
        }

        public function test_the_detail_page_is_recognised(): void
        {
            $history = $this->history('<div id="order-infos"><strong>Order Reference KHWLILZLL</strong></div>');

            OrderHistory::$chromePage->evaluate('document.body.id = "order-detail"');

            $this->assertTrue($history->isOrderDetailFor('KHWLILZLL'));
        }
    }
}
