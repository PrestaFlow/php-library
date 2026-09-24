<?php

namespace PrestaFlow\Tests\Unit\Pages;

use Exception;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v9\BackOffice\Login\Page as LoginPage;

/**
 * Test double: answers getTextContent() from a map keyed by selector, so a
 * "page" here is just the set of nodes a given PrestaShop version actually
 * renders. No browser, no shop.
 */
final class FakeVersionPage extends LoginPage
{
    public array $nodes = [];
    public array $asked = [];

    public function getTextContent($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
    {
        $this->asked[] = $selector;

        // getTextContent() answers false when the node is not there at all.
        return $this->nodes[$selector] ?? false;
    }
}

final class BackOfficeShopVersionTest extends TestCase
{
    private function fakeGlobals(): array
    {
        return [
            'PS_VERSION' => '9.2.0',
            'LOCALE' => 'en',
            'PREFIX_LOCALE' => false,
            'BO' => ['URL' => 'http://localhost/admin-dev/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
            'DEBUG' => false,
            'VERBOSE' => false,
        ];
    }

    private function page(array $nodes): FakeVersionPage
    {
        $page = new FakeVersionPage(
            locale: 'en',
            patchVersion: '9.2.0',
            globals: $this->fakeGlobals(),
            customs: []
        );
        $page->nodes = $nodes;

        return $page;
    }

    public function testTheVersionIsReadFromTheHeaderNodeThatActuallyHoldsOne(): void
    {
        // #shop_version is the one node present on an authenticated back office
        // of 1.7.8, 8.2 and 9.2 alike (verified live on all three).
        $page = $this->page(['#shop_version' => '9.2.0']);

        $this->assertSame('9.2.0', $page->getPrestashopVersion());
        $this->assertContains('#shop_version', $page->asked);
    }

    public function testEverySupportedVersionStringSurvivesUnchanged(): void
    {
        foreach (['1.7.8.11', '8.2.8', '9.2.0', '9.0.0-rc.1.test'] as $version) {
            $this->assertSame($version, $this->page(['#shop_version' => $version])->getPrestashopVersion(), $version);
        }
    }

    /**
     * The defect itself. A login page states no version on 8 or 9, and the
     * block the getter used to read held the login form's required-field
     * marker ("* PrestaShop" on 9) or the shop name ("PrestaShop" on 8 and
     * 1.7) — non-empty strings that satisfied isNotEmpty() while saying
     * nothing about any version.
     */
    public function testALoginPageThatStatesNoVersionAnswersNullRatherThanALabel(): void
    {
        $nineTwoLoginPage = $this->page([
            '#login_form h4' => "*\n        PrestaShop",
        ]);
        $eightTwoLoginPage = $this->page([
            '#login_form h4' => 'PrestaShop',
            '#shop_name' => 'PrestaShop',
        ]);

        $this->assertNull($nineTwoLoginPage->getPrestashopVersion());
        $this->assertNull($eightTwoLoginPage->getPrestashopVersion());
    }

    public function testAnEmptyOrAbsentNodeAnswersNull(): void
    {
        $this->assertNull($this->page([])->getPrestashopVersion());
        $this->assertNull($this->page(['#shop_version' => ''])->getPrestashopVersion());
        $this->assertNull($this->page(['#shop_version' => '   '])->getPrestashopVersion());
    }

    /**
     * Structural guard, not a format check: if a label ever drifts into
     * #shop_version the getter must go back to saying nothing rather than
     * repeating it. A label is not an answer.
     */
    public function testTextThatIsNotVersionShapedAnswersNull(): void
    {
        foreach (['PrestaShop', '* PrestaShop', 'Dashboard', '9'] as $label) {
            $this->assertNull($this->page(['#shop_version' => $label])->getPrestashopVersion(), $label);
        }
    }

    public function testTheMisleadingLoginSelectorIsGone(): void
    {
        $page = $this->page([]);

        $this->assertSame('#shop_version', $page->getSelector('shopVersionBlock'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('psVersionBlock');
        $page->getSelector('psVersionBlock');
    }

    public function testTheGetterIsAvailableOnEveryBackOfficePageNotJustLogin(): void
    {
        // #shop_version is a header node, so the dashboard — and every other
        // authenticated page — answers it too.
        $dashboard = new \PrestaFlow\Library\Pages\v9\BackOffice\Dashboard\Page(
            locale: 'en',
            patchVersion: '9.2.0',
            globals: $this->fakeGlobals(),
            customs: []
        );

        $this->assertSame('#shop_version', $dashboard->getSelector('shopVersionBlock'));
        $this->assertTrue(method_exists($dashboard, 'getPrestashopVersion'));
    }
}
