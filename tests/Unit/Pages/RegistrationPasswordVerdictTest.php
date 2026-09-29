<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v9\FrontOffice\Registration\Page as V9Registration;

/**
 * Test double: replays scripted evaluate() results instead of driving a browser,
 * and records the JS it was handed.
 */
final class FakeRegistrationPage extends V9Registration
{
    /** @var array<int, mixed> */
    public array $results = [];
    /** @var array<int, string> */
    public array $receivedJs = [];

    public function getPage()
    {
        return new class ($this) {
            public function __construct(private FakeRegistrationPage $owner)
            {
            }

            public function evaluate(string $js)
            {
                $index = count($this->owner->receivedJs);
                $this->owner->receivedJs[] = $js;
                $value = $this->owner->results[$index] ?? false;

                return new class ($value) {
                    public function __construct(private mixed $value)
                    {
                    }

                    public function getReturnValue(?int $timeout = null): mixed
                    {
                        return $this->value;
                    }
                };
            }
        };
    }
}

/**
 * On hummingbird the password strength feedback appears asynchronously and
 * shifts the submit button by ~100px; register() waits for it so the mouse
 * click cannot land on a consent label instead. These pin what that wait looks
 * at, since a selector drifting away from the theme's markup would turn it into
 * a silent no-op and bring the flaky submit back.
 */
final class RegistrationPasswordVerdictTest extends TestCase
{
    private function page(): FakeRegistrationPage
    {
        return new FakeRegistrationPage('en', '9.2.0', ['FO' => ['URL' => 'http://shop.test/'], 'THEME' => 'hummingbird'], []);
    }

    public function testTheWaitLooksAtHummingbirdsPasswordPolicyMarkup(): void
    {
        $page = $this->page();
        $page->results = [true];

        $this->assertTrue($page->waitForPasswordVerdict(1000));

        $js = $page->receivedJs[0];
        $this->assertStringContainsString(json_encode('#field-password'), $js);
        $this->assertStringContainsString(json_encode('[data-ps-ref="password-field"]'), $js);
        $this->assertStringContainsString(json_encode('[data-ps-ref="password-feedback-container"]'), $js);
        $this->assertStringContainsString('d-none', $js);
    }

    public function testItPollsUntilTheVerdictIsRendered(): void
    {
        $page = $this->page();
        $page->results = [false, false, true];

        $this->assertTrue($page->waitForPasswordVerdict(5000));
        $this->assertCount(3, $page->receivedJs);
    }

    public function testATimeoutIsReportedNotThrown(): void
    {
        $page = $this->page();
        $page->results = [];

        $this->assertFalse($page->waitForPasswordVerdict(300));
    }
}
