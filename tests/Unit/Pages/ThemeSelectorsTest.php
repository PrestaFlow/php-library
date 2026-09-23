<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;

/**
 * Test double: bypasses the parent constructor so the selector merge can be
 * exercised without a browser, a locale catalog or a live shop.
 */
final class FakeThemePage extends CommonPage
{
    public array $baseSelectors = [];
    public array $themeDirs = [];
    public string $lastThemeWarning = '';

    public function __construct(array $globals = [], array $baseSelectors = [])
    {
        $this->globals = $globals;
        $this->baseSelectors = $baseSelectors;
        $this->customs = ['selectors' => []];
    }

    public function defineSelectors()
    {
        return $this->baseSelectors;
    }

    // getPageName() normally derives from the class namespace; pin it so the
    // JSON walk has a predictable path to follow.
    public function getPageName(): string
    {
        return 'FrontOffice\\Product\\Page';
    }
}

final class ThemeSelectorsTest extends TestCase
{
    public function testThemeDefaultsToClassicWhenNothingIsSet(): void
    {
        $page = new FakeThemePage([]);

        $this->assertSame('classic', $page->getTheme());
    }

    public function testThemeComesFromTheGlobals(): void
    {
        $page = new FakeThemePage(['THEME' => 'hummingbird']);

        $this->assertSame('hummingbird', $page->getTheme());
    }

    public function testSetThemeOverridesTheGlobals(): void
    {
        $page = new FakeThemePage(['THEME' => 'hummingbird']);
        $page->setTheme('panda');

        $this->assertSame('panda', $page->getTheme());
    }

    public function testClassicLeavesTheBaseMapUntouched(): void
    {
        $page = new FakeThemePage(['THEME' => 'classic'], ['addToCartButton' => '.add-to-cart']);

        $this->assertSame('.add-to-cart', $page->getSelectors()['addToCartButton']);
    }

    private string $tmpThemes = '';

    protected function tearDown(): void
    {
        if ($this->tmpThemes !== '' && is_dir($this->tmpThemes)) {
            foreach (glob($this->tmpThemes . '/*.json') as $file) {
                @unlink($file);
            }
            @rmdir($this->tmpThemes);
        }
    }

    private function writeThemeFile(string $theme, array $selectors): string
    {
        if ($this->tmpThemes === '') {
            $this->tmpThemes = sys_get_temp_dir() . '/pf-themes-' . bin2hex(random_bytes(6));
            mkdir($this->tmpThemes, 0777, true);
        }

        file_put_contents(
            $this->tmpThemes . '/' . $theme . '.json',
            json_encode(['FrontOffice' => ['Product' => $selectors]])
        );

        return $this->tmpThemes;
    }

    public function testThemeFileOverridesTheBaseMap(): void
    {
        $dir = $this->writeThemeFile('hummingbird', ['addToCartButton' => '.product__add-to-cart-button']);

        $page = new FakeThemePage(['THEME' => 'hummingbird'], ['addToCartButton' => '.add-to-cart']);
        $page->themeDirs = [$dir];

        $this->assertSame('.product__add-to-cart-button', $page->getSelectors()['addToCartButton']);
    }

    public function testKeysAbsentFromTheThemeFileKeepTheirBaseValue(): void
    {
        $dir = $this->writeThemeFile('hummingbird', ['addToCartButton' => '.product__add-to-cart-button']);

        $page = new FakeThemePage(
            ['THEME' => 'hummingbird'],
            ['addToCartButton' => '.add-to-cart', 'quantityWantedInput' => '#quantity_wanted']
        );
        $page->themeDirs = [$dir];

        $this->assertSame('#quantity_wanted', $page->getSelectors()['quantityWantedInput']);
    }

    public function testTheLastDirectoryWins(): void
    {
        $libraryDir = $this->writeThemeFile('hummingbird', ['addToCartButton' => '.from-library']);

        $projectDir = sys_get_temp_dir() . '/pf-themes-project-' . bin2hex(random_bytes(6));
        mkdir($projectDir, 0777, true);
        file_put_contents(
            $projectDir . '/hummingbird.json',
            json_encode(['FrontOffice' => ['Product' => ['addToCartButton' => '.from-project']]])
        );

        $page = new FakeThemePage(['THEME' => 'hummingbird'], ['addToCartButton' => '.add-to-cart']);
        $page->themeDirs = [$libraryDir, $projectDir];

        $this->assertSame('.from-project', $page->getSelectors()['addToCartButton']);

        @unlink($projectDir . '/hummingbird.json');
        @rmdir($projectDir);
    }

    public function testAMissingThemeFileWarnsAndKeepsTheBaseMap(): void
    {
        $page = new FakeThemePage(['THEME' => 'panda'], ['addToCartButton' => '.add-to-cart']);
        $page->themeDirs = [sys_get_temp_dir()];

        $selectors = $page->getSelectors();

        $this->assertSame('.add-to-cart', $selectors['addToCartButton']);
        $this->assertNotSame('', $page->lastThemeWarning, 'a named theme with no file must say so');
    }
}
