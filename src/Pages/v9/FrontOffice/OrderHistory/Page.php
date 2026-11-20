<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\OrderHistory;

use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as BasePage;
use PrestaFlow\Library\Support\Pdf;

/**
 * "Order history" of the customer account (history controller), its order
 * detail page and the invoice PDF.
 *
 * Rows are found through the core's own markers rather than a table layout:
 * the details link (data-link-action="view-order-details") in Classic 1.7 to
 * 9 (table, and its mobile list) as in hummingbird (.order-history__row). The
 * invoice link is the row's other link to the order (id_order), the reorder
 * one aside (submitReorder).
 */
class Page extends BasePage
{
    public string $pageTitle = 'Order history';
    public string $url = 'order-history';
    public $shopPage = 'history';

    /** Skip reason of latestOrderReference() / orderReferenceOrLatest() on an empty history. */
    public const NO_ORDER = 'This customer has no order yet: let the scenario place one (useLastOrder off).';

    /** Skip reason of downloadInvoice(). */
    public const NO_INVOICE = 'No invoice for this order: invoices are disabled on this shop (PS_INVOICE),'
        . ' or the order status does not generate one.';

    /** Skip reason of skipWhenReusingLastOrder(). */
    public const REUSING_LAST_ORDER = "Reusing the customer's last order (useLastOrder param): no new order is placed.";

    public function defineSelectors()
    {
        return [
            'detailsLink' => 'a[data-link-action="view-order-details"]',
            // The element holding one order, whichever layout renders it.
            'orderRow' => 'tr, .order-history__row, .order',
        ];
    }

    /**
     * Skip this test (only) when $useLastOrder (a param, as a suite passes it)
     * asks to reuse the customer's last order instead of placing one.
     */
    public function skipWhenReusingLastOrder(mixed $useLastOrder): void
    {
        if (filter_var($useLastOrder, FILTER_VALIDATE_BOOLEAN)) {
            throw new SkipTestException(self::REUSING_LAST_ORDER);
        }
    }

    /** References listed on the history page, newest first. */
    public function getOrderReferences(): array
    {
        $refs = $this->pfRowsEval('return rows.map(function(r){return r.ref;});');

        return is_array($refs) ? array_values(array_unique(array_filter($refs, 'is_string'))) : [];
    }

    /** $reference when given (null or '' otherwise), else the newest order of the history; skips when there is none. */
    public function orderReferenceOrLatest(mixed $reference): string
    {
        if (is_string($reference) && $reference !== '') {
            return $reference;
        }

        $references = $this->getOrderReferences();
        if ($references === []) {
            throw new SkipTestException(self::NO_ORDER, skipRemaining: true);
        }

        return $references[0];
    }

    /** Whether the history lists the order $reference. */
    public function hasOrder(string $reference): bool
    {
        return $this->pfOrderLinks($reference)['details'] !== null;
    }

    /** Open the detail page of the order $reference. */
    public function openOrderDetail(string $reference): void
    {
        $url = $this->pfOrderLinks($reference)['details'];
        if ($url !== null) {
            $this->goToUrlInPlace($url);
        }
    }

    /** Whether the page open now is the detail page of the order $reference. */
    public function isOrderDetailFor(string $reference): bool
    {
        return $this->waitForJsCondition(sprintf(
            '!!document.body && document.body.id === "order-detail" && document.body.textContent.indexOf(%s) !== -1',
            json_encode($reference)
        ), 5000);
    }

    /**
     * Download the invoice of the order $reference with the customer's
     * session, as a Pdf. Skips this test (only) when the history offers no
     * invoice for the order (NO_INVOICE).
     */
    public function downloadInvoice(string $reference): Pdf
    {
        $url = $this->pfOrderLinks($reference)['invoice'];
        if ($url === null) {
            throw new SkipTestException(self::NO_INVOICE);
        }

        $base64 = $this->getPage()->evaluate(sprintf(
            'fetch(%s,{credentials:"include"}).then(function(r){return r.arrayBuffer();}).then(function(b){'
            . 'var s="",a=new Uint8Array(b);for(var i=0;i<a.length;i+=8192){s+=String.fromCharCode.apply(null,a.subarray(i,i+8192));}'
            . 'return btoa(s);})',
            json_encode($url)
        ))->getReturnValue();

        return Pdf::fromString((string) base64_decode((string) $base64));
    }

    /** ['details' => ?url, 'invoice' => ?url] of the order $reference. */
    private function pfOrderLinks(string $reference): array
    {
        $found = $this->pfRowsEval(sprintf(
            'var want=%s;for(var i=0;i<rows.length;i++){if(rows[i].ref===want||rows[i].text.indexOf(want)!==-1)'
            . '{return {details:rows[i].details,invoice:rows[i].invoice};}}return null;',
            json_encode($reference)
        ));

        return [
            'details' => is_array($found) && is_string($found['details'] ?? null) ? $found['details'] : null,
            'invoice' => is_array($found) && is_string($found['invoice'] ?? null) && $found['invoice'] !== '' ? $found['invoice'] : null,
        ];
    }

    /** Evaluate $body with `rows` = [{ref, text, details, invoice}] of the orders listed. */
    private function pfRowsEval(string $body): mixed
    {
        try {
            $json = $this->getPage()->evaluate(sprintf(
                '(function(){var rows=[];[].slice.call(document.querySelectorAll(%s)).forEach(function(a){'
                . 'var row=a.closest(%s);if(!row)return;var text=row.textContent.replace(/\\s+/g," ").trim();'
                . 'var cell=[].slice.call(row.querySelectorAll("*")).find(function(e){return /^[A-Z]{9}$/.test(e.textContent.trim());});'
                . 'var m=cell?[cell.textContent.trim()]:null;var inv=null;'
                . '[].slice.call(row.querySelectorAll("a[href]")).forEach(function(l){'
                . 'if(!inv&&l!==a&&!l.hasAttribute("data-link-action")&&l.href.indexOf("id_order")!==-1&&l.href.indexOf("submitReorder")===-1){inv=l.href;}});'
                . 'rows.push({ref:m?m[0]:null,text:text,details:a.href,invoice:inv});});'
                . 'return JSON.stringify((function(rows){%s})(rows));})()',
                json_encode($this->getSelector('detailsLink')),
                json_encode($this->getSelector('orderRow')),
                $body
            ))->getReturnValue();
        } catch (\Throwable) {
            return null;
        }

        return is_string($json) ? json_decode($json, true) : null;
    }
}
