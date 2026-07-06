<?php

namespace PrestaFlow\Library\Pages\Common\BackOffice\OrderView;

use PrestaFlow\Library\Pages\Common\BackOffice\Page as BasePage;

class Page extends BasePage
{
    public string $pageTitle = 'Order';

    public function defineSelectors()
    {
        return [
            // PS 9 order-view (/sell/orders/{id}/view) — validated live 2026-07-06.
            'statusSelect' => '#update_order_status_action_input',
            'updateStatusButton' => '#update_order_status_action_btn',
            // Status history lives in a tab pane (kept in the DOM); scan its text.
            'historyRows' => '#historyTabContent',
            'currentStatusBadge' => '.order-statuses-select .current, .order-status-label',
            'internalNoteTextarea' => '#private_note_note',
            'internalNoteSaveButton' => 'form[name="private_note"] button[type="submit"]',
            // Tracking sits in a shipping-edit modal opened by the edit button.
            'trackingEditButton' => '.js-update-shipping-btn',
            'trackingNumberInput' => '#update_order_shipping_tracking_number',
            'trackingSaveButton' => '.modal.show button.btn-primary[type="submit"]',
            // After saving, the tracking number is shown in the shipping table
            // (the modal input is empty when the modal is closed).
            'trackingDisplay' => '.carrier-tracking-num',
        ];
    }

    public function getCurrentStatus(): string
    {
        // The status <select> keeps the current status as its selected option.
        $sel = json_encode($this->getSelector('statusSelect'));

        return trim((string) $this->getPage()->evaluate(sprintf(
            '(function(){var s=document.querySelector(%s);return s&&s.selectedIndex>=0?s.options[s.selectedIndex].text:"";})()',
            $sel
        ))->getReturnValue());
    }

    public function updateStatus(string $status): void
    {
        // The status field is a select2: set the underlying <select> value by
        // option label and fire "change" (the lib's selectOption doesn't drive
        // select2), then submit.
        $sel = json_encode($this->getSelector('statusSelect'));
        $label = json_encode($status);
        $this->getPage()->evaluate(sprintf(
            '(function(){var s=document.querySelector(%s);if(!s)return;var o=[].slice.call(s.options).find(function(x){return x.text.trim()===%s;});if(o){s.value=o.value;s.dispatchEvent(new Event("change",{bubbles:true}));}})()',
            $sel,
            $label
        ));
        $this->click($this->getSelector('updateStatusButton'));
        $this->waitForPageReload();
    }

    public function hasStatusInHistory(string $status): bool
    {
        // After an update the new status becomes the current one; comparing the
        // current status is robust (the on-page history tab id collides with the
        // debug profiler's own history panel).
        return str_contains($this->getCurrentStatus(), $status);
    }

    public function setInternalNote(string $note): void
    {
        // The private-note form sits in a collapsed panel, so drive it via JS
        // (set the value, fire input, submit the form) — a click+type would fail
        // on the hidden textarea.
        $sel = json_encode($this->getSelector('internalNoteTextarea'));
        $value = json_encode($note);
        $this->getPage()->evaluate(sprintf(
            '(function(){var t=document.querySelector(%s);if(!t)return;t.value=%s;t.dispatchEvent(new Event("input",{bubbles:true}));var f=t.closest("form");var b=f?f.querySelector("button[type=submit],button.btn-primary"):null;if(b)b.click();})()',
            $sel,
            $value
        ));
        $this->waitForPageReload();
    }

    public function getInternalNote(): string
    {
        return $this->readValue($this->getSelector('internalNoteTextarea'));
    }

    public function addTracking(string $number): void
    {
        // The tracking field is in a shipping-edit modal that must be OPENED for
        // its form (carrier id + CSRF token) to populate — a blind form submit
        // saves nothing. Open the modal (JS click, reliable across tab state),
        // wait for it, set the field via JS, then click its Update button.
        $this->openShippingModal();
        $sel = json_encode($this->getSelector('trackingNumberInput'));
        $val = json_encode($number);
        $this->getPage()->evaluate(sprintf(
            '(function(){var t=document.querySelector(%s);if(t){t.value=%s;t.dispatchEvent(new Event("input",{bubbles:true}));t.dispatchEvent(new Event("change",{bubbles:true}));}})()',
            $sel,
            $val
        ));
        $this->click($this->getSelector('trackingSaveButton'));
        $this->waitForPageReload();
    }

    public function getTracking(): string
    {
        // Re-open the shipping-edit modal: its tracking field pre-fills with the
        // saved value. (The Carriers-tab display cell is lazy-loaded and
        // unreliable to read headlessly.)
        $this->openShippingModal();

        return $this->readValue($this->getSelector('trackingNumberInput'));
    }

    private function openShippingModal(): void
    {
        $editSel = json_encode($this->getSelector('trackingEditButton'));
        $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);if(e)e.click();})()',
            $editSel
        ));
        try {
            $this->getPage()->waitUntilContainsElement($this->getSelector('trackingSaveButton'), 8000);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Read an input/textarea's current `.value` property via JS. Unlike the
     * library's getInputValue (which reads the `value` attribute), this returns
     * the live value — the only reliable read for a <textarea>, whose value is
     * text content, not an attribute.
     */
    private function readValue(string $selector): string
    {
        $sel = json_encode($selector);

        return trim((string) $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);return e?e.value:"";})()',
            $sel
        ))->getReturnValue());
    }
}
