<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v9\BackOffice\Login\Page as LoginPage;
use RuntimeException;

final class FakeLogoutEvaluation
{
    public function __construct(private mixed $value)
    {
    }

    public function getReturnValue(?int $timeout = null): mixed
    {
        return $this->value;
    }
}

final class FakeLogoutNavigation
{
    public function waitForNavigation($event = null, $timeout = null): void
    {
    }
}

final class FakeLogoutBrowserPage
{
    public function __construct(private FakeLogoutPage $owner)
    {
    }

    public function evaluate(string $js): FakeLogoutEvaluation
    {
        $this->owner->evaluated[] = $js;

        return new FakeLogoutEvaluation($this->owner->logoutHref);
    }

    public function navigate(string $url): FakeLogoutNavigation
    {
        $this->owner->navigated[] = $url;

        return new FakeLogoutNavigation();
    }
}

/**
 * Test double: a back office whose #header_logout carries whatever href the
 * version under test would have built. goToPage() is recorded instead of
 * executed, so the pre-fix implementation is observable without a browser.
 */
final class FakeLogoutPage extends LoginPage
{
    public mixed $logoutHref = null;
    public array $navigated = [];
    public array $evaluated = [];
    public array $goToPageCalls = [];

    public function getPage()
    {
        return new FakeLogoutBrowserPage($this);
    }

    public function goToPage($page = null, $params = null)
    {
        $this->goToPageCalls[] = $page;
    }
}

final class BackOfficeLogoutTest extends TestCase
{
    /**
     * The hrefs the three supported versions actually put on #header_logout,
     * copied from live 1.7.8.11, 8.2.8 and 9.2.0 shops. The point of the table
     * is that none of them is {BO_URL}logout, and that all three are reachable
     * through the same selector.
     */
    public static function liveLogoutHrefs(): array
    {
        return [
            '1.7.8.11' => ['http://localhost:8017/admin-dev/index.php?controller=AdminLogin&logout=1&token=d219249b20d2ed74425beb3b17e8248d'],
            '8.2.8' => ['http://localhost:8082/admin-dev/index.php?controller=AdminLogin&logout=1&token=c4464c98fb374db8b412a5521652b62a'],
            '9.2.0' => ['http://localhost:8092/admin-dev/logout?_token=2f6cede35ff5.6-73AYBaswalfSQ-y4b9SXKAiK1ylwSWnLwz-AKzuA8'],
        ];
    }

    private function page(mixed $href): FakeLogoutPage
    {
        $page = new FakeLogoutPage(
            locale: 'en',
            patchVersion: '9.2.0',
            globals: [
                'PS_VERSION' => '9.2.0',
                'LOCALE' => 'en',
                'PREFIX_LOCALE' => false,
                'BO' => ['URL' => 'http://localhost/admin-dev/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
                'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
                'DEBUG' => false,
                'VERBOSE' => false,
            ],
            customs: []
        );
        $page->logoutHref = $href;

        return $page;
    }

    /**
     * @dataProvider liveLogoutHrefs
     */
    public function testLogoutFollowsTheLinkTheShopItselfBuilt(string $href): void
    {
        $page = $this->page($href);

        $page->logout();

        $this->assertSame([$href], $page->navigated);
    }

    /**
     * The defect: {BO_URL}logout is a Symfony route on 9 and nothing at all on
     * 1.7 and 8, whose legacy dispatcher served the dashboard instead and left
     * the employee logged in.
     */
    public function testLogoutNeverNavigatesToTheSyntheticLogoutRoute(): void
    {
        $page = $this->page('http://localhost:8082/admin-dev/index.php?controller=AdminLogin&logout=1&token=abc');

        $page->logout();

        $this->assertSame([], $page->goToPageCalls, 'logout() must not go through the {BO_URL}logout route');
        foreach ($page->navigated as $url) {
            $this->assertStringNotContainsString('admin-dev/logout', $url);
        }
    }

    public function testTheLinkIsLookedUpThroughTheSharedLogoutSelector(): void
    {
        $page = $this->page('http://localhost/admin-dev/logout?_token=x');

        $page->logout();

        $this->assertCount(1, $page->evaluated);
        $this->assertStringContainsString('#header_logout', $page->evaluated[0]);
        $this->assertStringContainsString('.href', $page->evaluated[0]);
    }

    /**
     * No logout link means no session. Answering quietly is what let a shipped
     * suite report a successful logout against a still-authenticated shop.
     */
    public function testLogoutWithoutASessionRaisesInsteadOfDoingNothingQuietly(): void
    {
        $page = $this->page(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('#header_logout');

        try {
            $page->logout();
        } finally {
            $this->assertSame([], $page->navigated);
        }
    }

    public function testAnEmptyHrefIsTreatedAsNoLinkAtAll(): void
    {
        $page = $this->page('');

        $this->expectException(RuntimeException::class);

        $page->logout();
    }

    /**
     * The v7 and v8 page objects stay stubs on purpose: the version difference
     * lives in the href the shop renders, not in PHP. If a later change moves
     * logout() into them, this is the test that should be revisited first.
     */
    public function testTheVersionedPageObjectsDoNotOverrideLogout(): void
    {
        foreach (['v7', 'v8'] as $version) {
            $class = 'PrestaFlow\\Library\\Pages\\' . $version . '\\BackOffice\\Login\\Page';
            $method = new \ReflectionMethod($class, 'logout');

            $this->assertSame(
                LoginPage::class,
                $method->getDeclaringClass()->getName(),
                $version . ' should inherit logout() rather than re-implement it'
            );
        }
    }
}
