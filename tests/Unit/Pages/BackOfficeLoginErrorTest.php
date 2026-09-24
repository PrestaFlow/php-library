<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v9\BackOffice\Login\Page as LoginPage;

final class FakeLoginErrorEvaluation
{
    public function __construct(private mixed $value)
    {
    }

    public function getReturnValue(?int $timeout = null): mixed
    {
        return $this->value;
    }
}

final class FakeLoginErrorBrowserPage
{
    public function __construct(private FakeLoginErrorPage $owner)
    {
    }

    public function evaluate(string $js): FakeLoginErrorEvaluation
    {
        $this->owner->polls++;
        $this->owner->evaluated[] = $js;

        return new FakeLoginErrorEvaluation($this->owner->containerIsFilled());
    }
}

/**
 * Test double for the 1.7 / 8.2 admin login, which is AJAX: the error
 * container ships EMPTY in the initial HTML
 *
 *     <div id="error" class="hide alert alert-danger"></div>
 *
 * and is filled only when the XHR lands. $fillsOnPoll is how many polls that
 * takes — 1 means "already there when we looked", which is what 9.2 does.
 */
final class FakeLoginErrorPage extends LoginPage
{
    public const MESSAGE = 'The employee does not exist, or the password provided is incorrect.';

    public int $polls = 0;
    public int $fillsOnPoll = 3;
    public bool $neverFills = false;
    public array $evaluated = [];
    public array $textReads = [];

    public function containerIsFilled(): bool
    {
        return !$this->neverFills && $this->polls >= $this->fillsOnPoll;
    }

    public function getPage()
    {
        return new FakeLoginErrorBrowserPage($this);
    }

    public function getTextContent($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
    {
        $this->textReads[] = $selector;

        // The node is present from the first millisecond either way; what
        // changes over time is whether it has any text in it.
        return $this->containerIsFilled() ? self::MESSAGE : '';
    }
}

final class BackOfficeLoginErrorTest extends TestCase
{
    private function page(): FakeLoginErrorPage
    {
        return new FakeLoginErrorPage(
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

    /**
     * The defect: the container is in the DOM before the XHR answers, so a
     * presence wait returns "" roughly half the time on 1.7 and 8.2.
     */
    public function testAnErrorThatArrivesLateIsStillRead(): void
    {
        $page = $this->page();
        $page->fillsOnPoll = 3;

        $this->assertSame(FakeLoginErrorPage::MESSAGE, $page->getLoginError(2000, 1));
        $this->assertGreaterThanOrEqual(3, $page->polls, 'the getter must have polled until the container filled');
    }

    public function testAnErrorThatIsAlreadyThereIsReadWithoutExtraPolling(): void
    {
        // 9.2 renders .alert-danger only on the error response, so it is
        // already filled when we first look. No regression in that case.
        $page = $this->page();
        $page->fillsOnPoll = 1;

        $this->assertSame(FakeLoginErrorPage::MESSAGE, $page->getLoginError(2000, 1));
        $this->assertSame(1, $page->polls);
    }

    public function testTheWaitIsOnContentNotOnMerePresence(): void
    {
        $page = $this->page();
        $page->fillsOnPoll = 2;

        $page->getLoginError(2000, 1);

        $this->assertNotEmpty($page->evaluated);
        $js = $page->evaluated[0];
        $this->assertStringContainsString('.alert-danger', $js);
        $this->assertStringContainsString('textContent', $js, 'waiting on presence alone is the defect');
        $this->assertStringContainsString('!==', $js);
    }

    /**
     * A getter that throws on timeout would report the wait, not the finding.
     * The caller asserts on the message and fails with its own wording.
     */
    public function testAnErrorThatNeverArrivesReturnsEmptyRatherThanThrowing(): void
    {
        $page = $this->page();
        $page->neverFills = true;

        $this->assertSame('', $page->getLoginError(150, 10));
        $this->assertGreaterThan(1, $page->polls);
    }

    public function testTheTextIsReadThroughTheSameSelectorThatWasWaitedOn(): void
    {
        $page = $this->page();
        $page->fillsOnPoll = 1;

        $page->getLoginError(2000, 1);

        $this->assertSame([$page->getSelector('alertDangerTextBlock')], $page->textReads);
    }
}
