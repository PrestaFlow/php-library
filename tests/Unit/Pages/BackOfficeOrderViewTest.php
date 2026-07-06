<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class BackOfficeOrderViewTest extends TestCase
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
        $class = 'PrestaFlow\\Library\\Pages\\v9\\BackOffice\\' . $name . '\\Page';
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $this->globals, customs: []);
    }

    public function testOrderViewSelectorsAndMethods(): void
    {
        $page = $this->make('OrderView');
        foreach (['statusSelect', 'updateStatusButton', 'historyRows', 'internalNoteTextarea', 'internalNoteSaveButton', 'trackingNumberInput', 'trackingSaveButton'] as $key) {
            $this->assertArrayHasKey($key, $page->selectors, $key);
        }
        foreach (['getCurrentStatus', 'updateStatus', 'hasStatusInHistory', 'setInternalNote', 'getInternalNote', 'addTracking', 'getTracking'] as $method) {
            $this->assertTrue(method_exists($page, $method), $method);
        }
    }

    public function testOrdersHasOpenOrder(): void
    {
        $page = $this->make('Orders');
        $this->assertArrayHasKey('listRowLink', $page->selectors);
        $this->assertTrue(method_exists($page, 'openOrder'));
    }
}
