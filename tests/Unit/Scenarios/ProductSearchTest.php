<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Pages\v9\FrontOffice\Search\Page as SearchPage;

final class ProductSearchTest extends TestCase
{
    public function test_defaults_are_discovered(): void
    {
        $params = (new \ReflectionClass(\PrestaFlow\Library\Scenarios\ProductSearch::class))->getDefaultProperties()['params'];

        foreach (['locale', 'productUrl', 'searchWord'] as $key) {
            $this->assertArrayHasKey($key, $params);
            $this->assertNull($params[$key], $key);
        }
    }

    public function test_the_search_page_resolves_through_the_shop(): void
    {
        $page = (new \ReflectionClass(SearchPage::class))->getDefaultProperties();

        $this->assertSame('search', $page['url']);
        $this->assertSame('search', $page['shopPage']);
    }

    public function test_the_search_page_exists_for_every_version(): void
    {
        $this->assertTrue(is_subclass_of(\PrestaFlow\Library\Pages\v8\FrontOffice\Search\Page::class, SearchPage::class));
        $this->assertTrue(is_subclass_of(\PrestaFlow\Library\Pages\v7\FrontOffice\Search\Page::class, SearchPage::class));
    }

    public static function names(): array
    {
        return [
            'longest word' => ['Hummingbird printed t-shirt', 'Hummingbird'],
            'first on a tie' => ['Mug Best Days', 'Best'],
            'punctuation and digits split words' => ['T-shirt 2000 XL', 'shirt'],
            'accents count as letters' => ['Café élégant', 'élégant'],
            'any script' => ['Чашка кофе', 'Чашка'],
            'apostrophes split words' => ["L'aventure commence", 'aventure'],
            'four letters is enough' => ['Mug Blue', 'Blue'],
            'no word of four letters' => ['Mug 3D XL', null],
            'empty' => ['', null],
        ];
    }

    /** @dataProvider names */
    public function test_the_longest_word_of_a_name(string $name, ?string $expected): void
    {
        $this->assertSame($expected, SearchPage::longestWord($name));
    }

    public function test_a_given_word_wins_over_the_name(): void
    {
        $page = (new \ReflectionClass(SearchPage::class))->newInstanceWithoutConstructor();

        $this->assertSame('poster', $page->pickSearchWord('Mug 3D', ' poster '));
        $this->assertSame('Hummingbird', $page->pickSearchWord('Hummingbird printed t-shirt', null));
        $this->assertSame('Hummingbird', $page->pickSearchWord('Hummingbird printed t-shirt', ''));
    }

    public function test_a_name_without_a_word_to_search_skips_the_suite(): void
    {
        $page = (new \ReflectionClass(SearchPage::class))->newInstanceWithoutConstructor();

        try {
            $page->pickSearchWord('Mug 3D', null);
            $this->fail('A name without a word of 4 letters must skip');
        } catch (SkipTestException $e) {
            $this->assertSame(SearchPage::NO_SEARCH_WORD, $e->getMessage());
            $this->assertSame('The product name has no word of at least 4 letters to search for: set the searchWord param.', $e->getMessage());
            $this->assertTrue($e->skipRemaining);
        }
    }
}
