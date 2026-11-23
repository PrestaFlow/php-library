<?php

namespace PrestaFlow\Tests\Browser\SearchFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\Search\Page as SearchPage;

    /** The Search page on a chrome-php page handed in by the test, without a suite. */
    class Search extends SearchPage
    {
        public static $chromePage = null;

        public function __construct()
        {
            $this->selectors = ['pageTitle' => 'h1', ...$this->defineSelectors()];
        }

        public function getPage()
        {
            return self::$chromePage;
        }
    }
}

namespace PrestaFlow\Tests\Browser\SearchFixtures {

    use PrestaFlow\Library\Pages\v9\FrontOffice\Product\Page as ProductPage;

    class Product extends ProductPage
    {
        public function __construct()
        {
            $this->selectors = ['pageTitle' => 'h1', ...$this->defineSelectors()];
        }

        public function getPage()
        {
            return Search::$chromePage;
        }
    }
}

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Exceptions\SkipTestException;
    use PrestaFlow\Tests\Browser\SearchFixtures\Product;
    use PrestaFlow\Tests\Browser\SearchFixtures\Search;

    /**
     * The search results page, the ps_searchbar header field and what the
     * product page tells about its product, as Classic (1.7 to 9) and
     * hummingbird render them.
     */
    final class ProductSearchBrowserTest extends TestCase
    {
        private static $browser = null;

        public static function tearDownAfterClass(): void
        {
            self::$browser?->close();
            self::$browser = null;
        }

        /** Stands in for the search controller: records the word and turns the page into the results page. */
        private const SCRIPT = '<script>
            document.addEventListener("submit", function(e){
                e.preventDefault();
                window.__searched = e.target.querySelector("[name=s]").value;
                window.__controller = (e.target.querySelector("[name=controller]") || {}).value;
                document.body.id = "search";
            });
        </script>';

        private const CLASSIC_HEADER = '<div id="search_widget" class="search-widgets" data-search-controller-url="/search">'
            . '<form method="get" action="/search"><input type="hidden" name="controller" value="search">'
            . '<i class="material-icons search" aria-hidden="true">search</i>'
            . '<input type="text" name="s" value="" placeholder="Search our catalog" aria-label="Search">'
            . '<i class="material-icons clear" aria-hidden="true">clear</i></form></div>';

        private const HUMMINGBIRD_HEADER = '<div id="_desktop_ps_searchbar" class="order-2 ms-auto col-auto d-none d-md-flex align-items-center">'
            . '<div id="ps_searchbar" class="ps-searchbar js-search-widget" data-search-controller-url="/en/search">'
            . '<form class="ps-searchbar__form" method="get" action="/en/search" role="search">'
            . '<input type="hidden" name="controller" value="search">'
            . '<input class="js-search-input form-control ps-searchbar__input" type="text" name="s" value="" id="ps_searchbar_input" autocomplete="off">'
            . '</form></div></div>'
            . '<div class="ps-searchbar--mobile d-md-none d-flex col-auto"><div id="_mobile_ps_searchbar" class="ps-searchbar__container"></div></div>';

        private const CLASSIC_RESULTS = '<section id="products"><div id="js-product-list"><div class="products row">'
            . '<div class="js-product product"><article class="product-miniature js-product-miniature" data-id-product="11" data-id-product-attribute="0">'
            . '<h2 class="h3 product-title"><a href="/11-poster.html">Poster</a></h2></article></div>'
            . '<div class="js-product product"><article class="product-miniature js-product-miniature" data-id-product="1" data-id-product-attribute="1">'
            . '<h2 class="h3 product-title"><a href="/1-1-t-shirt.html">T-shirt</a></h2></article></div>'
            . '</div></div></section>';

        private const HUMMINGBIRD_RESULTS = '<section id="products"><div id="js-product-list"><div class="products row">'
            . '<div class="product-miniature card js-product-miniature" data-id-product="11" data-id-product-attribute="0">'
            . '<a href="/en/11-poster.html" class="product-miniature__title">Poster</a></div>'
            . '<div class="product-miniature card js-product-miniature" data-id-product="1" data-id-product-attribute="1">'
            . '<a href="/en/1-1-t-shirt.html" class="product-miniature__title">T-shirt</a></div>'
            . '</div></div></section>';

        private function open(string $markup, string $bodyId = 'index'): Search
        {
            if (self::$browser === null) {
                try {
                    self::$browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [1280, 800], 'noSandbox' => true]);
                } catch (\Throwable $e) {
                    if (getenv('CI') === false || getenv('CI') === '') {
                        $this->markTestSkipped('Chrome indisponible : ' . $e->getMessage());
                    }
                    $this->fail('Chrome indisponible en CI : ' . $e->getMessage());
                }
            }

            $page = self::$browser->createPage();
            $page->setViewport(1280, 800)->await();
            $page->navigate('data:text/html,' . rawurlencode(
                '<meta charset="utf-8"><style>.d-none{display:none}@media(min-width:768px){.d-md-flex{display:flex!important}.d-md-none{display:none!important}}</style>'
                . '<body id="' . $bodyId . '">' . $markup . self::SCRIPT . '</body>'
            ))->waitForNavigation();
            Search::$chromePage = $page;

            return new Search();
        }

        public static function themes(): array
        {
            return [
                'classic' => [self::CLASSIC_HEADER, self::CLASSIC_RESULTS],
                'hummingbird' => [self::HUMMINGBIRD_HEADER, self::HUMMINGBIRD_RESULTS],
            ];
        }

        /** @dataProvider themes */
        public function test_the_results_are_read_by_product_id(string $header, string $results): void
        {
            $search = $this->open($header . $results, 'search');

            $this->assertTrue($search->isSearchPage());
            $this->assertSame([11, 1], $search->getResultProductIds());
            $this->assertSame(2, $search->countResults());
            $this->assertTrue($search->hasResultProduct('1'));
            $this->assertTrue($search->hasResultProduct(11));
            $this->assertFalse($search->hasResultProduct(2));
            $this->assertFalse($search->hasResultProduct(null));
        }

        /** @dataProvider themes */
        public function test_the_header_field_submits_the_word(string $header, string $results): void
        {
            $search = $this->open($header);

            $this->assertFalse($search->isSearchPage());
            $search->requireHeaderSearch();
            $search->searchFromHeader('Hummingbird');

            $this->assertSame('Hummingbird', Search::$chromePage->evaluate('window.__searched')->getReturnValue());
            $this->assertSame('search', Search::$chromePage->evaluate('window.__controller')->getReturnValue());
            $this->assertTrue($search->isSearchPage());
        }

        public function test_no_result_is_no_product(): void
        {
            $search = $this->open('<section id="products"><div id="js-product-list"><section class="page-not-found"><h4>No products</h4></section></div></section>'
                . '<section class="featured-products"><article class="product-miniature js-product-miniature" data-id-product="1"></article></section>', 'search');

            $this->assertSame(0, $search->countResults());
            $this->assertFalse($search->hasResultProduct(1));
        }

        public function test_no_header_field_skips_this_step_only(): void
        {
            $search = $this->open('<header id="header"><div id="_desktop_logo"></div></header>');

            $this->assertFalse($search->hasHeaderSearch());
            try {
                $search->requireHeaderSearch();
                $this->fail('A header without the search field must skip');
            } catch (SkipTestException $e) {
                $this->assertSame('This shop has no search field in its header (the ps_searchbar module is missing or not shown).', $e->getMessage());
                $this->assertFalse($e->skipRemaining);
            }
        }

        public static function productPages(): array
        {
            return [
                'classic' => ['<section id="main"><h1 class="h1">Hummingbird printed t-shirt</h1>'
                    . '<div class="tab-pane" id="product-details" data-product="%s"></div>'
                    . '<form action="/cart" method="post" id="add-to-cart-or-refresh"><input type="hidden" name="id_product" value="7" id="product_page_product_id"></form></section>'],
                'hummingbird' => ['<section id="main"><h1 class="product__name h2 mb-1">  Hummingbird printed t-shirt  </h1>'
                    . '<div class="js-product-details" data-product="%s"></div>'
                    . '<form action="/cart" method="post" id="add-to-cart-or-refresh"><input type="hidden" name="id_product" value="7" id="product_page_product_id"></form></section>'],
            ];
        }

        /** @dataProvider productPages */
        public function test_the_product_page_gives_its_id_and_name(string $markup): void
        {
            $data = htmlspecialchars(json_encode(['id_product' => 7, 'name' => 'Hummingbird printed t-shirt']), ENT_QUOTES);
            $this->open(sprintf($markup, $data));
            $product = new Product();

            $this->assertSame(7, $product->getProductId());
            $this->assertSame('Hummingbird printed t-shirt', $product->getProductName());
        }

        /** @dataProvider productPages */
        public function test_without_product_data_the_form_and_heading_answer(string $markup): void
        {
            $this->open(str_replace(' data-product="%s"', '', $markup));
            $product = new Product();

            $this->assertSame(7, $product->getProductId());
            $this->assertSame('Hummingbird printed t-shirt', $product->getProductName());
        }
    }
}
