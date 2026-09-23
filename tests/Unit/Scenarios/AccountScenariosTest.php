<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class AccountScenariosTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function scenarioProvider(): array
    {
        return [
            'registration' => ['Registration', __DIR__ . '/../../../src/Scenarios/Registration.php'],
            'ensure-test-account' => ['EnsureTestAccount', __DIR__ . '/../../../src/Scenarios/EnsureTestAccount.php'],
        ];
    }

    /**
     * @dataProvider scenarioProvider
     */
    public function testScenarioAndSuiteExist(string $name): void
    {
        $scenario = 'PrestaFlow\\Library\\Scenarios\\' . $name;
        $suite = 'PrestaFlow\\Library\\Tests\\Suites\\Scenarios\\' . $name;

        $this->assertTrue(class_exists($scenario), $scenario);
        $this->assertTrue(is_subclass_of($scenario, 'PrestaFlow\\Library\\Scenarios\\Scenario'));
        $this->assertTrue(class_exists($suite), $suite);
        $this->assertTrue(is_subclass_of($suite, 'PrestaFlow\\Library\\Tests\\TestsSuite'));
    }

    /**
     * Same guard as the One Page Checkout scenarios: a step that only clicks
     * reports success even when nothing happened, because click() returns false
     * on a missing selector instead of raising.
     *
     * @dataProvider scenarioProvider
     */
    public function testEveryStepAssertsSomething(string $name, string $path): void
    {
        // Split on a real step call — `->it('` followed by its title — not on
        // the bare substring, which also occurs in prose comments.
        $chunks = preg_split("/->it\\(\\s*'/", file_get_contents($path));
        array_shift($chunks);

        $this->assertNotEmpty($chunks, $name . ' declares no step at all');

        foreach ($chunks as $chunk) {
            preg_match('/^([^\']+)\'/', $chunk, $matches);

            $this->assertStringContainsString(
                'Expect::that',
                $chunk,
                sprintf('step "%s" asserts nothing, so it cannot fail', $matches[1] ?? '(untitled)')
            );
        }
    }

    public function testRegistrationKeepsItsEmailUniquePerRun(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\Registration');
        $params = $ref->getDefaultProperties()['params'] ?? [];

        // A hardcoded default would make every run after the first fail on a
        // duplicate e-mail.
        $this->assertArrayHasKey('email', $params);
        $this->assertNull($params['email'], 'the default e-mail must stay null so a unique one is generated');
    }

    public function testEnsureTestAccountHardcodesNoPassword(): void
    {
        $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\EnsureTestAccount');
        $params = $ref->getDefaultProperties()['params'] ?? [];

        foreach (['email', 'password'] as $key) {
            $this->assertArrayHasKey($key, $params, $key);
            $this->assertNull($params[$key], $key . ' must come from the FO_* globals, not from the source');
        }
    }
}
