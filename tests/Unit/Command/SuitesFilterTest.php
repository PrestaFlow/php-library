<?php

namespace PrestaFlow\Tests\Unit\Command;

use Error;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Command\ExecuteSuite;
use PrestaFlow\Library\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * PRESTAFLOW_SUITES (set by the GitHub Action's `suites` input) and --suites
 * restrict a run to sub-folders of the suites path.
 */
final class SuitesFilterTest extends TestCase
{
    private string $tmpDir;
    private string $root;
    private string $previousCwd;
    private array|string|false $previousEnv;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/prestaflow-suites-' . bin2hex(random_bytes(6));
        $this->root = $this->tmpDir . '/Suites';

        foreach (['BackOffice', 'FrontOffice/Checkout', 'Empty'] as $dir) {
            mkdir($this->root . '/' . $dir, 0777, true);
        }
        foreach (['Top.php', 'BackOffice/Login.php', 'FrontOffice/Home.php', 'FrontOffice/Checkout/Guest.php'] as $file) {
            file_put_contents($this->root . '/' . $file, "<?php\nnamespace Fixture;\n");
        }

        $this->previousCwd = getcwd();
        chdir($this->tmpDir);

        $this->previousEnv = [
            'env' => $_ENV['PRESTAFLOW_SUITES'] ?? null,
            'process' => getenv('PRESTAFLOW_SUITES'),
        ];
        unset($_ENV['PRESTAFLOW_SUITES']);
        putenv('PRESTAFLOW_SUITES');
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        exec('rm -rf ' . escapeshellarg($this->tmpDir));

        if ($this->previousEnv['env'] === null) {
            unset($_ENV['PRESTAFLOW_SUITES']);
        } else {
            $_ENV['PRESTAFLOW_SUITES'] = $this->previousEnv['env'];
        }
        putenv($this->previousEnv['process'] === false ? 'PRESTAFLOW_SUITES' : 'PRESTAFLOW_SUITES=' . $this->previousEnv['process']);
    }

    public function testParseTrimsAndDropsEmptyNamesAndDuplicates(): void
    {
        $this->assertSame(
            ['BackOffice', 'FrontOffice/Checkout'],
            (new ExecuteSuite())->parseSuitesFilter(' BackOffice, ,FrontOffice/Checkout/ ,BackOffice,')
        );
    }

    public function testParseOfNothingIsNoFilter(): void
    {
        $this->assertSame([], (new ExecuteSuite())->parseSuitesFilter(null));
        $this->assertSame([], (new ExecuteSuite())->parseSuitesFilter(' , '));
    }

    public function testParseRejectsParentSegments(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/\.\./');

        (new ExecuteSuite())->parseSuitesFilter('BackOffice/../../etc');
    }

    public function testParseRejectsAbsolutePaths(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/absolute/');

        (new ExecuteSuite())->parseSuitesFilter('/etc');
    }

    public function testNoFilterKeepsTheWholeTree(): void
    {
        $this->assertSame(
            $this->sorted((new ExecuteSuite())->resolveSuitePaths($this->root)),
            $this->sorted((new ExecuteSuite())->resolveSuitePaths($this->root, []))
        );
    }

    public function testFilterKeepsOnlyTheNamedSubFoldersRecursively(): void
    {
        $resolved = (new ExecuteSuite())->resolveSuitePaths($this->root, ['FrontOffice']);

        $this->assertSame([
            $this->root . '/FrontOffice/Checkout/Guest.php',
            $this->root . '/FrontOffice/Home.php',
        ], $this->sorted($resolved));
    }

    public function testFilterAcceptsNestedPathsAndReturnsTheUnionWithoutDuplicates(): void
    {
        $resolved = (new ExecuteSuite())->resolveSuitePaths($this->root, ['FrontOffice/Checkout', 'BackOffice', 'FrontOffice']);

        $this->assertSame([
            $this->root . '/BackOffice/Login.php',
            $this->root . '/FrontOffice/Checkout/Guest.php',
            $this->root . '/FrontOffice/Home.php',
        ], $this->sorted($resolved));
        $this->assertSame(count($resolved), count(array_unique($resolved)));
    }

    public function testUnknownNamesFailListingMissingAndAvailableFolders(): void
    {
        try {
            (new ExecuteSuite())->resolveSuitePaths($this->root, ['BackOffice', 'Nope', 'Front']);
            $this->fail('An unknown sub-folder must fail the run');
        } catch (Error $e) {
            $this->assertStringContainsString('Nope', $e->getMessage());
            $this->assertStringContainsString('Front', $e->getMessage());
            $this->assertStringContainsString('BackOffice, Empty, FrontOffice', $e->getMessage());
        }
    }

    public function testFilterOnASingleSuiteFileIsRefused(): void
    {
        $this->expectException(Error::class);

        (new ExecuteSuite())->resolveSuitePaths($this->root . '/Top.php', ['BackOffice']);
    }

    public function testEnvVariableFiltersTheRunAndFailsOnAnUnknownFolder(): void
    {
        putenv('PRESTAFLOW_SUITES=Nope');

        $tester = $this->tester();
        $exitCode = $tester->run(['command' => 'run', 'folder' => $this->root], ['capture_stderr_separately' => true]);

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('Nope', $tester->getErrorOutput());
    }

    public function testCliOptionWinsOverTheEnvVariable(): void
    {
        putenv('PRESTAFLOW_SUITES=Nope');

        $tester = $this->tester();
        $tester->run(
            ['command' => 'run', 'folder' => $this->root, '--suites' => 'BackOffice'],
            ['capture_stderr_separately' => true]
        );

        // The unknown name from the environment was never looked at.
        $this->assertStringNotContainsString('Nope', $tester->getErrorOutput());
    }

    public function testAFilteredRunThatSelectsNoSuiteFails(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->run(
            ['command' => 'run', 'folder' => $this->root, '--suites' => 'Empty'],
            ['capture_stderr_separately' => true]
        );

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Suites filter [Empty] selected no suite', $tester->getErrorOutput());
    }

    public function testAnUnfilteredEmptyFolderStillSucceeds(): void
    {
        $this->assertSame(0, $this->tester()->run(
            ['command' => 'run', 'folder' => $this->root . '/Empty'],
            ['capture_stderr_separately' => true]
        ));
    }

    public function testCliOptionAloneFailsOnAnUnknownFolder(): void
    {
        $exitCode = $this->tester()->run(
            ['command' => 'run', 'folder' => $this->root, '--suites' => 'Missing'],
            ['capture_stderr_separately' => true]
        );

        $this->assertNotSame(0, $exitCode);
    }

    private function tester(): ApplicationTester
    {
        $application = new Application();
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }

    private function sorted(array $paths): array
    {
        sort($paths);

        return $paths;
    }
}
