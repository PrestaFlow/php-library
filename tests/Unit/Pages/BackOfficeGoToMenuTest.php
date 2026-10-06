<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\BackOfficePage;
use PrestaFlow\Library\Tests\TestsSuite;

final class BackOfficeGoToMenuTest extends TestCase
{
    private const CURRENT = 'http://shop.test/admin-dev/index.php?controller=AdminDashboard&token=t';

    /** Page factice : $links associe un sélecteur à son href (ou null si absent). */
    private function page(array $links): BackOfficePage
    {
        $globals = ['PS_VERSION' => '9.0.0', 'BO' => ['URL' => 'http://shop.test/admin-dev/']];

        return new class ('en', '9.0.0', $globals, $links, self::CURRENT) extends BackOfficePage {
            public array $navigated = [];

            /** Plafond reçu par chaque waitForNavigation() (null = défaut chrome-php). */
            public array $waits = [];

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

                        return new class ($this->outer) {
                            public function __construct(private $outer)
                            {
                            }

                            public function waitForNavigation($event = null, $timeout = null): void
                            {
                                $this->outer->waits[] = $timeout;
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

    public function test_menu_navigation_keeps_the_chrome_default_unless_capped(): void
    {
        $href = 'http://shop.test/admin-dev/index.php?controller=AdminOrders&token=abc';
        $page = $this->page(['#subtab-AdminOrders' => $href]);

        $page->goToMenu('#subtab-AdminOrders');
        $page->navigationTimeout = 15000;
        $page->goToMenu('#subtab-AdminOrders');

        $this->assertSame([null, 15000], $page->waits);
    }

    public function test_url_navigation_keeps_the_chrome_default_unless_capped(): void
    {
        // goToUrl() réapplique les en-têtes persistants : vides ici, aucun navigateur n'est lancé.
        $headers = TestsSuite::$extraHttpHeaders;
        TestsSuite::$extraHttpHeaders = [];
        try {
            $page = $this->page([]);

            $page->goToUrl('http://shop.test/admin-dev/logout');
            $page->navigationTimeout = 5000;
            $page->goToUrl('http://shop.test/admin-dev/logout');

            $this->assertSame([null, 5000], $page->waits);
            $this->assertSame(['http://shop.test/admin-dev/logout', 'http://shop.test/admin-dev/logout'], $page->navigated);
        } finally {
            TestsSuite::$extraHttpHeaders = $headers;
        }
    }
}
