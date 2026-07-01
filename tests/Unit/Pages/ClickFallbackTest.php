<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\BackOfficePage;

final class ClickFallbackTest extends TestCase
{
    public function testGoToSubMenuNavigatesToResolvedHref(): void
    {
        // Fake chrome-php Page: evaluate() returns a resolved href, navigate()
        // records the URL it was asked to open. Lets goToSubMenu run without a
        // real browser and proves it navigates to the sub-link's href.
        $fakePage = new class {
            public array $navigated = [];
            public function waitUntilContainsElement($selector, $timeout = 3000) {}
            public function evaluate($js)
            {
                return new class {
                    public function getReturnValue()
                    {
                        return 'https://shop.test/admin-dev/sell/catalog/products/';
                    }
                };
            }
            public function navigate($url)
            {
                $this->navigated[] = $url;
                return new class {
                    public function waitForNavigation() {}
                };
            }
        };

        $page = new class($fakePage) extends BackOfficePage {
            private $fakePage;
            public function __construct($fakePage) { $this->fakePage = $fakePage; }
            public function getPage() { return $this->fakePage; }
        };

        $page->goToSubMenu('#subtab-AdminCatalog', '#subtab-AdminProducts');

        $this->assertContains(
            'https://shop.test/admin-dev/sell/catalog/products/',
            $fakePage->navigated,
            'goToSubMenu should navigate to the resolved sub-link href'
        );
    }
}
