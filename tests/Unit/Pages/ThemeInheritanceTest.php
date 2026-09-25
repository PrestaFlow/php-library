<?php

/*
 * Theme overrides used to be keyed on the CONCRETE page class alone, so a page
 * that extends a SIBLING page (Category extends Listing, PricesDrop extends
 * Listing, ...) silently ignored every override its parent declared. The
 * fixtures below reproduce that shape with real classes -- the resolution walks
 * class_parents(), so a fake getPageName() would prove nothing.
 *
 * They live under a v99 namespace on purpose: the resolver only recognises
 * classes under Pages\v{N}\ or Pages\Common\, and v99 is the one version that
 * cannot collide with a page the library actually ships.
 */

namespace PrestaFlow\Library\Pages\v99\FrontOffice {

    use PrestaFlow\Library\Pages\CommonPage;

    /**
     * The area base. Strips to the two-segment chain "FrontOffice\Page", which
     * is trap 1: its walk lands on the FrontOffice node, a map of PAGE NAMES.
     */
    class Page extends CommonPage
    {
        public array $baseSelectors = [];

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

        public function getPageName(): string
        {
            return str_replace('PrestaFlow\\Library\\Pages\\v99\\', '', static::class);
        }
    }
}

namespace PrestaFlow\Library\Pages\v99\FrontOffice\Listing {

    class Page extends \PrestaFlow\Library\Pages\v99\FrontOffice\Page
    {
    }
}

namespace PrestaFlow\Library\Pages\v99\FrontOffice\Category {

    /** The defect in one line: a page whose parent is a sibling page. */
    class Page extends \PrestaFlow\Library\Pages\v99\FrontOffice\Listing\Page
    {
    }
}

namespace PrestaFlow\Library\Pages\v99\FrontOffice\Product {

    class Page extends \PrestaFlow\Library\Pages\v99\FrontOffice\Page
    {
    }
}

namespace PrestaFlow\Tests\Unit\Pages {

    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Library\Pages\v99\FrontOffice\Category\Page as CategoryPage;
    use PrestaFlow\Library\Pages\v99\FrontOffice\Listing\Page as ListingPage;
    use PrestaFlow\Library\Pages\v99\FrontOffice\Product\Page as ProductPage;

    final class ThemeInheritanceTest extends TestCase
    {
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

        private function writeTheme(string $theme, array $catalog): string
        {
            if ($this->tmpThemes === '') {
                $this->tmpThemes = sys_get_temp_dir() . '/pf-theme-inherit-' . bin2hex(random_bytes(6));
                mkdir($this->tmpThemes, 0777, true);
            }

            file_put_contents($this->tmpThemes . '/' . $theme . '.json', json_encode($catalog));

            return $this->tmpThemes;
        }

        /** THE defect: an override on Listing must reach Category. */
        public function testAnOverrideOnTheParentPageReachesTheChildPage(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    'Listing' => ['productArticleLink' => '.product-miniature__title'],
                ],
            ]);

            $page = new CategoryPage(
                ['THEME' => 'hummingbird'],
                ['productArticleLink' => '.product-title a']
            );
            $page->themeDirs = [$dir];

            $this->assertSame(
                '.product-miniature__title',
                $page->getSelectors()['productArticleLink'],
                'an override declared on Listing must apply to Category, which extends it'
            );
        }

        /** The parent block must not beat the child's own block. */
        public function testTheConcretePageStillWinsOverItsParent(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    'Listing' => ['pageHeading' => '.from-listing'],
                    'Category' => ['pageHeading' => '.from-category'],
                ],
            ]);

            $page = new CategoryPage(['THEME' => 'hummingbird'], ['pageHeading' => '.base']);
            $page->themeDirs = [$dir];

            $this->assertSame('.from-category', $page->getSelectors()['pageHeading']);
        }

        /** Keys only the parent declares survive alongside the child's own. */
        public function testParentAndChildBlocksAreMergedNotReplaced(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    'Listing' => ['productArticle' => '.from-listing', 'sortBy' => '.sort'],
                    'Category' => ['productArticle' => '.from-category'],
                ],
            ]);

            $page = new CategoryPage(
                ['THEME' => 'hummingbird'],
                ['productArticle' => '.base-article', 'sortBy' => '.base-sort']
            );
            $page->themeDirs = [$dir];

            $selectors = $page->getSelectors();

            $this->assertSame('.from-category', $selectors['productArticle']);
            $this->assertSame('.sort', $selectors['sortBy']);
        }

        /** Inheritance must not leak sideways: Product does not extend Listing. */
        public function testASiblingPageDoesNotInheritAnotherPagesOverrides(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    'Listing' => ['productArticle' => '.from-listing'],
                ],
            ]);

            $page = new ProductPage(['THEME' => 'hummingbird'], ['productArticle' => '.base-article']);
            $page->themeDirs = [$dir];

            $this->assertSame('.base-article', $page->getSelectors()['productArticle']);
        }

        /** The parent page itself keeps resolving its own block. */
        public function testTheParentPageStillResolvesItsOwnBlock(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    'Listing' => ['productArticle' => '.from-listing'],
                ],
            ]);

            $page = new ListingPage(['THEME' => 'hummingbird'], ['productArticle' => '.base-article']);
            $page->themeDirs = [$dir];

            $this->assertSame('.from-listing', $page->getSelectors()['productArticle']);
        }

        /**
         * Trap 1: the area base strips to "FrontOffice\Page", whose walk lands on
         * the FrontOffice node -- a map of PAGE NAMES. Those names must never be
         * merged in as if they were selector keys.
         */
        public function testPageNamesFromTheAreaNodeDoNotLeakIntoSelectors(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    'Product' => ['addToCartButton' => '.product__add-to-cart-button'],
                    'Listing' => ['productArticle' => '.from-listing'],
                    'Category' => ['productArticle' => '.from-category'],
                ],
            ]);

            $page = new CategoryPage(['THEME' => 'hummingbird'], ['base' => '.base']);
            $page->themeDirs = [$dir];

            $selectors = $page->getSelectors();

            $this->assertArrayNotHasKey('Product', $selectors);
            $this->assertArrayNotHasKey('Listing', $selectors);
            $this->assertArrayNotHasKey('Category', $selectors);
            $this->assertSame(
                [],
                array_filter($selectors, fn ($value) => !is_string($value)),
                'no selector value may be an array'
            );
        }

        /**
         * Trap 2, on its own: even when a walk DOES land on a node holding nested
         * blocks, only flat string values are taken.
         */
        public function testNestedArraysAreNeverMergedAsSelectors(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    'Category' => [
                        'productArticle' => '.from-category',
                        'Nested' => ['productArticle' => '.too-deep'],
                    ],
                ],
            ]);

            $page = new CategoryPage(['THEME' => 'hummingbird'], []);
            $page->themeDirs = [$dir];

            $selectors = $page->getSelectors();

            $this->assertSame('.from-category', $selectors['productArticle']);
            $this->assertArrayNotHasKey('Nested', $selectors);
        }

        /*
         * A selector declared on the AREA base (FrontOfficePage: desktopLogo,
         * userInfoLink, ...) is inherited by every page in that area, but the
         * catalog had nowhere to express it: the chain "FrontOffice\\Page"
         * strips to a single usable segment and is dropped by trap 1, so the
         * only way to theme such a selector was to repeat it in all 31 page
         * blocks. Measured on a real 9.2 shop, desktopLogo and userInfoLink
         * miss on hummingbird on every one of 18 front-office pages, which is
         * exactly the shape that duplication would have to cover.
         *
         * "_common" is the reserved block for that. It cannot collide with a
         * page name: page segments come from class namespaces and never start
         * with an underscore.
         */
        public function testACommonBlockAppliesToAPageWithNoBlockOfItsOwn(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    '_common' => ['desktopLogo' => '.header-bottom__logo'],
                ],
            ]);

            $page = new ProductPage(['THEME' => 'hummingbird'], ['desktopLogo' => '#_desktop_logo']);
            $page->themeDirs = [$dir];

            $this->assertSame('.header-bottom__logo', $page->getSelectors()['desktopLogo']);
        }

        /** The common block is the least specific tier: any page block beats it. */
        public function testAPageBlockBeatsTheCommonBlock(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    '_common' => ['desktopLogo' => '.from-common'],
                    'Product' => ['desktopLogo' => '.from-product'],
                ],
            ]);

            $page = new ProductPage(['THEME' => 'hummingbird'], ['desktopLogo' => '#_desktop_logo']);
            $page->themeDirs = [$dir];

            $this->assertSame('.from-product', $page->getSelectors()['desktopLogo']);
        }

        /** ... and a parent PAGE block beats it too, not just the concrete one. */
        public function testAParentPageBlockBeatsTheCommonBlock(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    '_common' => ['productArticle' => '.from-common'],
                    'Listing' => ['productArticle' => '.from-listing'],
                ],
            ]);

            $page = new CategoryPage(['THEME' => 'hummingbird'], ['productArticle' => '.base']);
            $page->themeDirs = [$dir];

            $this->assertSame('.from-listing', $page->getSelectors()['productArticle']);
        }

        /** Keys the page block does not mention still come through. */
        public function testCommonAndPageBlocksAreMergedNotReplaced(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    '_common' => ['desktopLogo' => '.logo', 'userInfoLink' => '.user'],
                    'Product' => ['desktopLogo' => '.product-logo'],
                ],
            ]);

            $page = new ProductPage(
                ['THEME' => 'hummingbird'],
                ['desktopLogo' => '#_desktop_logo', 'userInfoLink' => '#_desktop_user_info']
            );
            $page->themeDirs = [$dir];

            $selectors = $page->getSelectors();

            $this->assertSame('.product-logo', $selectors['desktopLogo']);
            $this->assertSame('.user', $selectors['userInfoLink']);
        }

        /**
         * A common block belongs to its area. Without this the reserved key
         * would become a global, and a BackOffice override would start
         * rewriting FrontOffice selectors.
         */
        public function testACommonBlockDoesNotLeakAcrossAreas(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'BackOffice' => [
                    '_common' => ['desktopLogo' => '.back-office-logo'],
                ],
            ]);

            $page = new ProductPage(['THEME' => 'hummingbird'], ['desktopLogo' => '#_desktop_logo']);
            $page->themeDirs = [$dir];

            $this->assertSame('#_desktop_logo', $page->getSelectors()['desktopLogo']);
        }

        /** Trap 2 still applies inside the reserved block. */
        public function testNestedArraysInTheCommonBlockAreNotMergedAsSelectors(): void
        {
            $dir = $this->writeTheme('hummingbird', [
                'FrontOffice' => [
                    '_common' => [
                        'desktopLogo' => '.logo',
                        'Nested' => ['desktopLogo' => '.too-deep'],
                    ],
                ],
            ]);

            $page = new ProductPage(['THEME' => 'hummingbird'], []);
            $page->themeDirs = [$dir];

            $selectors = $page->getSelectors();

            $this->assertSame('.logo', $selectors['desktopLogo']);
            $this->assertArrayNotHasKey('Nested', $selectors);
        }
    }
}
