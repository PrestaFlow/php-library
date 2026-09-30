<?php

namespace PrestaFlow\Tests\Browser;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Visual\PageSnapshot;
use PrestaFlow\Library\Visual\SnapshotException;
use PrestaFlow\Library\Visual\SnapshotResult;

/** Script de carte (PageScripts::ELEMENT_MAP) dans un vrai Chrome. */
final class PageSnapshotBrowserTest extends TestCase
{
    private static ?SnapshotResult $result = null;

    private function snapshot(): SnapshotResult
    {
        if (self::$result === null) {
            try {
                self::$result = (new PageSnapshot())->take('file://'.__DIR__.'/fixtures/snapshot.html', 'desktop', 30000);
            } catch (SnapshotException $e) {
                // en local sans Chrome : ignoré ; en CI : le test doit échouer, jamais passer en silence
                if (getenv('CI') === false || getenv('CI') === '') {
                    $this->markTestSkipped('Chrome indisponible : '.$e->getMessage());
                }
                $this->fail('Chrome indisponible en CI : '.$e->getMessage());
            }
        }

        return self::$result;
    }

    /** @return array<string, mixed>|null premier élément de la carte dont le sélecteur vaut $selector */
    private function bySelector(string $selector): ?array
    {
        foreach ($this->snapshot()->elements as $el) {
            if ($el['selector'] === $selector) {
                return $el;
            }
        }

        return null;
    }

    private function byText(string $tag, string $id = ''): array
    {
        return array_values(array_filter($this->snapshot()->elements, fn ($e) => $e['tag'] === $tag && ($id === '' || $e['id'] === $id)));
    }

    public function test_capture_is_a_full_page_jpeg(): void
    {
        $r = $this->snapshot();
        $this->assertStringStartsWith("\xFF\xD8\xFF", $r->image);
        $this->assertSame('image/jpeg', $r->mime);
        $this->assertSame(1920, $r->width);
        $this->assertGreaterThan(3000, $r->height);
    }

    public function test_stable_id_wins(): void
    {
        $this->assertNotNull($this->bySelector('#header'));
        $this->assertNotNull($this->bySelector('#footer'));
    }

    public function test_generated_id_falls_back_to_classes(): void
    {
        $banner = $this->byText('div', 'item-48213')[0];
        $this->assertStringNotContainsString('48213', $banner['selector']);
        $this->assertMatchesRegularExpression('/^div\.(bloc|promo-banner|bloc\.promo-banner|promo-banner\.bloc)$/', $banner['selector']);
    }

    public function test_state_and_library_classes_are_ignored(): void
    {
        foreach ($this->snapshot()->elements as $el) {
            $this->assertDoesNotMatchRegularExpression('/\.(active|owl-item|hidden|d-none|sr-only|invisible)(?![\w-])/', $el['selector'], $el['selector']);
        }
        $marked = array_values(array_filter($this->snapshot()->elements, fn ($e) => in_array('hidden', $e['classes'], true)));
        $this->assertCount(1, $marked, 'un .hidden visible doit rester dans la carte');
    }

    public function test_descendant_of_a_unique_ancestor_beats_the_nth_of_type_chain(): void
    {
        $card = $this->bySelector('#main div.card');
        $this->assertNotNull($card, json_encode(array_column($this->snapshot()->elements, 'selector')));
        $this->assertSame(1, $card['matches']);
    }

    public function test_ancestor_nth_of_type_fallback_is_unique(): void
    {
        $paragraphs = $this->byText('p');
        $second = array_values(array_filter($paragraphs, fn ($p) => str_contains($p['selector'], 'nth-of-type(2)')));
        $this->assertNotEmpty($second, json_encode(array_column($paragraphs, 'selector')));
        foreach ($this->snapshot()->elements as $el) {
            $this->assertSame(1, $el['matches'], $el['selector']);
        }
    }

    public function test_invisible_and_tiny_elements_are_excluded_svg_children_too(): void
    {
        $selectors = array_column($this->snapshot()->elements, 'selector');
        $classes = array_merge(...array_column($this->snapshot()->elements, 'classes'));
        foreach (['hidden-none', 'hidden-vis', 'hidden-op', 'tiny'] as $c) {
            $this->assertNotContains($c, $classes, $c);
        }
        $this->assertContains('#logo', $selectors);
        $this->assertEmpty($this->byText('circle'));
    }

    public function test_boxes_are_in_document_coordinates_and_parents_resolve(): void
    {
        $footer = $this->bySelector('#footer');
        $this->assertGreaterThan(3000, $footer['box'][1]);
        $elements = $this->snapshot()->elements;
        foreach ($elements as $k => $el) {
            $this->assertSame($k, $el['i']);
            $this->assertTrue($el['p'] === -1 || ($el['p'] >= 0 && $el['p'] < $k), 'parent avant enfant');
        }
        $copy = $this->bySelector('#footer > p:nth-of-type(1)') ?? $this->bySelector('p.copy');
        $this->assertNotNull($copy);
        $this->assertSame('footer', $elements[$copy['p']]['tag']);
    }
}
