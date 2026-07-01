<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;

final class BackOfficeProductsTest extends TestCase
{
    private function make(): object
    {
        $class = 'PrestaFlow\\Library\\Pages\\v9\\BackOffice\\Products\\Page';
        $globals = [
            'PS_VERSION' => '9.0.0',
            'LOCALE' => 'en',
            'PREFIX_LOCALE' => false,
            'BO' => ['URL' => 'http://localhost/admin/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'DEBUG' => false,
            'VERBOSE' => false,
        ];
        return new $class(locale: 'en', patchVersion: '9.0.0', globals: $globals, customs: []);
    }

    public function testDeclaresAllSelectorKeys(): void
    {
        $selectors = $this->make()->selectors;
        foreach ([
            'pageHeading', 'newProductButton', 'filterNameInput', 'searchButton',
            'resetButton', 'listRow', 'listRowName', 'resultCount', 'rowActionsToggle',
            'rowDeleteLink', 'deleteConfirmButton', 'formNameInput', 'formPriceInput',
            'formQuantityInput', 'formSaveButton', 'successAlert',
        ] as $key) {
            $this->assertArrayHasKey($key, $selectors, $key);
        }
    }

    public function testHasAllActionMethods(): void
    {
        $page = $this->make();
        foreach ([
            'filterByName', 'resetFilter', 'getListCount', 'getProductNameInList',
            'goToNewProduct', 'createProduct', 'deleteProduct', 'getSuccessMessage',
        ] as $method) {
            $this->assertTrue(method_exists($page, $method), $method);
        }
    }

    public function testHasScenarioSupport(): void
    {
        $page = $this->make();
        $this->assertArrayHasKey('productOnlineToggle', $page->selectors);
        $this->assertTrue(method_exists($page, 'enableProduct'));
        $this->assertTrue(method_exists($page, 'getCreatedProductId'));
    }
}
