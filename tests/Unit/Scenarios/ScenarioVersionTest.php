<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Scenarios\Scenario;
use PrestaFlow\Library\Tests\TestsSuite;

final class ScenarioVersionTest extends TestCase
{
    private function suite(string $version, array $globals = []): TestsSuite
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {};
        $suite->onVersion($version);
        $suite->setGlobals(['LOCALE' => 'en', ...$globals]);

        return $suite;
    }

    public function test_the_scenario_takes_the_suite_versions(): void
    {
        $scenario = new Scenario($this->suite('1.7.8.11'));

        $this->assertSame('1.7', $scenario->getMajorVersion());
        $this->assertSame('1.7.8.11', $scenario->getPatchVersion());
    }

    public function test_a_patch_version_global_is_parsed_not_stored_as_a_string(): void
    {
        $scenario = new Scenario($this->suite('8.1.0', ['PATCH_VERSION' => '9.2.0']));

        $this->assertSame(
            ['patchVersion' => '9.2.0', 'minorVersion' => '9.2', 'majorVersion' => '9'],
            $scenario->getVersions()
        );
    }

    public function test_changing_the_scenario_version_leaves_the_suite_alone(): void
    {
        $suite = $this->suite('8.1.0');
        $scenario = new Scenario($suite);

        $scenario->setMajorVersion('9');

        $this->assertSame('8', $suite->getMajorVersion());
    }
}
