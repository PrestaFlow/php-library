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
            'pageHeading', 'newProductButton', 'createProductButton', 'filterNameInput', 'searchButton',
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

    public function testExposesCanonicalFrontOfficeUrl(): void
    {
        $page = $this->make();
        $this->assertSame('#product_footer_actions_preview', $page->selectors['productPreviewLink']);
        $this->assertTrue(method_exists($page, 'getCreatedProductUrl'));
    }

    public function testDeleteProductWaitsForConfirmModal(): void
    {
        $body = $this->methodBody('deleteProduct');
        $this->assertStringContainsString('waitUntilContainsElement', $body);
        $this->assertStringContainsString('deleteConfirmButton', $body);
    }

    public function testUsesRealPs9FormSelectors(): void
    {
        $selectors = $this->make()->selectors;
        $this->assertSame('#create_product_create', $selectors['createProductButton']);
        $this->assertSame('#product_pricing_retail_price_price_tax_excluded', $selectors['formPriceInput']);
        $this->assertSame('#product_stock_quantities_delta_quantity_delta', $selectors['formQuantityInput']);
    }

    // Reads a method's source so the tests below can assert *structure and
    // ordering* invariants (e.g. enable-before-save) that are live-validated but
    // cannot be exercised in composer test-unit (no headless Chrome). These are
    // structural checks, not behavioural coverage.
    private function methodBody(string $method): string
    {
        $ref = new \ReflectionMethod($this->make(), $method);
        $lines = file($ref->getFileName());
        return implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        ));
    }

    public function testGoToNewProductClicksAddThenCreate(): void
    {
        $body = $this->methodBody('goToNewProduct');
        $this->assertStringContainsString('newProductButton', $body);
        $this->assertStringContainsString('createProductButton', $body);
        $this->assertLessThan(
            strpos($body, 'createProductButton'),
            strpos($body, 'newProductButton'),
            'goToNewProduct must click the Add link before the create-draft button'
        );
    }

    public function testCreateProductActivatesBeforeSave(): void
    {
        $body = $this->methodBody('createProduct');
        $this->assertLessThan(
            strpos($body, 'formSaveButton'),
            strpos($body, 'enableProduct'),
            'createProduct must enable the product before saving so it persists'
        );
    }

    public function testHasEditActions(): void
    {
        $page = $this->make();
        $this->assertArrayHasKey('listRowLink', $page->selectors);
        foreach (['openProduct', 'updatePrice', 'getFormPrice'] as $method) {
            $this->assertTrue(method_exists($page, $method), $method);
        }
    }
}
