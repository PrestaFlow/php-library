<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\BackOfficePage;
use PrestaFlow\Library\Pages\CommonPage;

final class BackOfficeGoToMenuTest extends TestCase
{
    private const CURRENT = 'http://shop.test/admin-dev/index.php?controller=AdminDashboard&token=t';

    /**
     * The page versions live in a static (Traits\Version) that getPageName()
     * fills on first use and never re-derives. Before PHP 8.3 a class
     * re-using that trait (FrontOfficePage, via Translations) shares the
     * parent's storage, so whatever a BackOfficePage caches here is what the
     * next front-office page in the process sees. Leave it as found.
     */
    private array $versions = [];

    protected function setUp(): void
    {
        $this->versions = CommonPage::$versions;
    }

    protected function tearDown(): void
    {
        CommonPage::$versions = $this->versions;
    }

    /** Page factice : $links associe un sélecteur à son href (ou null si absent). */
    private function page(array $links): BackOfficePage
    {
        $globals = ['PS_VERSION' => '9.0.0', 'BO' => ['URL' => 'http://shop.test/admin-dev/']];

        return new class ('en', '9.0.0', $globals, $links, self::CURRENT) extends BackOfficePage {
            public array $navigated = [];

            public function __construct($locale, $version, $globals, private array $links, private string $current)
            {
                parent::__construct($locale, $version, $globals);
            }

            public function getPage()
            {
                $outer = $this;

                return new class ($outer) {
                    public function __construct(private $outer)
                    {
                    }

                    public function evaluate(string $js)
                    {
                        $value = null;
                        if (str_contains($js, 'location.href')) {
                            $value = $this->outer->current();
                        } else {
                            foreach ($this->outer->links() as $selector => $href) {
                                if (str_contains($js, json_encode($selector))) {
                                    $value = $href;
                                }
                            }
                        }

                        return new class ($value) {
                            public function __construct(private $value)
                            {
                            }

                            public function getReturnValue($timeout = null)
                            {
                                return $this->value;
                            }
                        };
                    }

                    public function navigate(string $url)
                    {
                        $this->outer->navigated[] = $url;

                        return new class {
                            public function waitForNavigation($event = null): void
                            {
                            }
                        };
                    }
                };
            }

            public function current(): string
            {
                return $this->current;
            }

            public function links(): array
            {
                return $this->links;
            }
        };
    }

    public function test_first_present_entry_wins(): void
    {
        $page = $this->page(['#subtab-AdminDashboard' => null, '#tab-AdminDashboard' => 'http://shop.test/admin-dev/index.php?controller=AdminDashboard&token=abc']);

        $href = $page->goToMenu('#subtab-AdminDashboard, #tab-AdminDashboard');

        $this->assertSame('http://shop.test/admin-dev/index.php?controller=AdminDashboard&token=abc', $href);
        $this->assertSame([$href], $page->navigated);
    }

    public function test_parent_anchor_is_not_a_link(): void
    {
        $page = $this->page(['#subtab-AdminCatalog' => self::CURRENT . '#collapse-2']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('#subtab-AdminCatalog');
        $page->goToMenu('#subtab-AdminCatalog');
    }

    public function test_missing_entry_fails_with_the_selectors(): void
    {
        $page = $this->page([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('#subtab-AdminOrders, #nope');
        $page->goToMenu('#subtab-AdminOrders, #nope');
    }
}
