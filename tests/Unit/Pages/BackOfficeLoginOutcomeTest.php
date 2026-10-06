<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v9\BackOffice\Login\Page as LoginPage;

final class FakeLoginOutcomeEvaluation
{
    public function __construct(private mixed $value)
    {
    }

    public function getReturnValue(?int $timeout = null): mixed
    {
        return $this->value;
    }
}

final class FakeLoginOutcomeBrowserPage
{
    public function __construct(private FakeLoginOutcomePage $owner)
    {
    }

    public function evaluate(string $js): FakeLoginOutcomeEvaluation
    {
        $this->owner->polls++;
        $this->owner->evaluated[] = $js;

        return new FakeLoginOutcomeEvaluation($this->owner->settled());
    }
}

/**
 * Test double for a login whose next page is slow: the outcome (session open,
 * or error shown) only becomes visible after $settlesOnPoll polls. A cold
 * dashboard on a loaded CI runner took longer than the old fixed 10 s + 5 s.
 */
final class FakeLoginOutcomePage extends LoginPage
{
    public int $polls = 0;
    public int $settlesOnPoll = 3;
    public bool $neverSettles = false;
    public array $evaluated = [];
    public array $log = [];

    /** Plafond reçu par chaque waitForLoginOutcome(). */
    public array $outcomeTimeouts = [];

    public function waitForLoginOutcome(int $timeout = 60000, int $interval = 200): bool
    {
        $this->outcomeTimeouts[] = $timeout;

        return parent::waitForLoginOutcome($timeout, $interval);
    }

    public function settled(): bool
    {
        return !$this->neverSettles && $this->polls >= $this->settlesOnPoll;
    }

    public function getPage()
    {
        return new FakeLoginOutcomeBrowserPage($this);
    }

    public function setValue($selector, $value)
    {
        $this->log[] = 'set '.$selector;
    }

    public function click($selector, $nth = 1)
    {
        $this->log[] = 'click '.$selector;
    }

    public function waitForPageReload()
    {
        $this->log[] = 'reload';
    }
}

final class BackOfficeLoginOutcomeTest extends TestCase
{
    private function page(): FakeLoginOutcomePage
    {
        return new FakeLoginOutcomePage(
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
    }

    public function testASlowOutcomeIsWaitedFor(): void
    {
        $page = $this->page();
        $page->settlesOnPoll = 4;

        $this->assertTrue($page->waitForLoginOutcome(2000, 1));
        $this->assertSame(4, $page->polls);
    }

    public function testTheOutcomeIsTheLogoutLinkOrAFilledErrorAlert(): void
    {
        $page = $this->page();
        $page->settlesOnPoll = 1;

        $page->waitForLoginOutcome(2000, 1);

        $js = $page->evaluated[0];
        $this->assertStringContainsString('#header_logout', $js);
        $this->assertStringContainsString('.alert-danger', $js);
        // An empty alert container (1.7 / 8 ship one) is not an outcome.
        $this->assertStringContainsString('textContent', $js);
    }

    public function testAnOutcomeThatNeverComesReturnsFalseRatherThanThrowing(): void
    {
        $page = $this->page();
        $page->neverSettles = true;

        $this->assertFalse($page->waitForLoginOutcome(150, 10));
        $this->assertGreaterThan(1, $page->polls);
    }

    public function testLoginWaitsForTheOutcomeAfterTheReload(): void
    {
        $page = $this->page();
        $page->settlesOnPoll = 2;

        $page->login();

        $this->assertSame(['set #email', 'set #passwd', 'click #submit_login', 'reload'], $page->log);
        $this->assertSame(2, $page->polls, 'login() must poll until the outcome is visible');
    }

    public function testLoginWithoutNavigationWaitDoesNotWait(): void
    {
        $page = $this->page();

        $page->login(waitForNavigation: false);

        $this->assertSame(['set #email', 'set #passwd', 'click #submit_login'], $page->log);
        $this->assertSame(0, $page->polls);
    }

    public function testLoginKeepsTheRunCeilingByDefault(): void
    {
        $page = $this->page();
        $page->settlesOnPoll = 1;

        $page->login();

        $this->assertSame([60000], $page->outcomeTimeouts);
        $this->assertTrue($page->loginOutcomeSeen);
    }

    public function testLoginUsesTheCeilingSetOnThePage(): void
    {
        $page = $this->page();
        $page->loginOutcomeTimeout = 150;
        $page->settlesOnPoll = 1;

        $page->login();

        $this->assertSame([150], $page->outcomeTimeouts);
    }

    public function testAnOutcomeNotSeenWithinTheCeilingIsRecorded(): void
    {
        $page = $this->page();
        $page->loginOutcomeTimeout = 150;
        $page->neverSettles = true;

        $page->login();

        $this->assertFalse($page->loginOutcomeSeen);
    }
}
