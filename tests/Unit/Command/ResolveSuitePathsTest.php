<?php

namespace PrestaFlow\Tests\Unit\Command;

use Error;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Command\ExecuteSuite;

final class ResolveSuitePathsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/prestaflow-resolve-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir . '/Suites/Nested', 0777, true);

        file_put_contents($this->tmpDir . '/Suites/First.php', '<?php');
        file_put_contents($this->tmpDir . '/Suites/Nested/Second.php', '<?php');
        // Not a suite: must never be returned.
        file_put_contents($this->tmpDir . '/Suites/README.md', 'nope');
    }

    protected function tearDown(): void
    {
        foreach (['/Suites/Nested/Second.php', '/Suites/First.php', '/Suites/README.md'] as $file) {
            @unlink($this->tmpDir . $file);
        }
        @rmdir($this->tmpDir . '/Suites/Nested');
        @rmdir($this->tmpDir . '/Suites');
        @rmdir($this->tmpDir);
    }

    /**
     * The capitalised variant is a FALLBACK, not an unconditional transform.
     * Kept pure so it can be asserted on a case-insensitive filesystem, where
     * is_dir('src') and is_dir('Src') both answer true and a filesystem-level
     * test would pass for the wrong reason.
     */
    public function testCandidatePathsOffersTheArgumentBeforeItsCapitalisedVariant(): void
    {
        $command = new ExecuteSuite();

        $this->assertSame(
            ['src/Tests/Suites', 'Src/Tests/Suites'],
            $command->candidatePaths('src/Tests/Suites')
        );
    }

    public function testCandidatePathsKeepsTheHistoricalDefaultWorking(): void
    {
        $command = new ExecuteSuite();

        $this->assertSame(['tests', 'Tests'], $command->candidatePaths('tests'));
    }

    public function testCandidatePathsDoesNotDuplicateAnAlreadyCapitalisedPath(): void
    {
        $command = new ExecuteSuite();

        $this->assertSame(['Tests'], $command->candidatePaths('Tests'));
    }

    public function testResolveReturnsEveryPhpFileOfADirectoryRecursively(): void
    {
        $command = new ExecuteSuite();

        $resolved = $command->resolveSuitePaths($this->tmpDir . '/Suites');
        sort($resolved);

        $this->assertSame([
            $this->tmpDir . '/Suites/First.php',
            $this->tmpDir . '/Suites/Nested/Second.php',
        ], $resolved);
    }

    public function testResolveAcceptsASingleSuiteFile(): void
    {
        $command = new ExecuteSuite();

        $this->assertSame(
            [$this->tmpDir . '/Suites/First.php'],
            $command->resolveSuitePaths($this->tmpDir . '/Suites/First.php')
        );
    }

    public function testResolveRejectsAFileThatIsNotASuite(): void
    {
        $command = new ExecuteSuite();

        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/not a PHP suite file/');

        $command->resolveSuitePaths($this->tmpDir . '/Suites/README.md');
    }

    public function testResolveReportsAMissingPathDistinctly(): void
    {
        $command = new ExecuteSuite();

        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/doesn\'t seem to exist/');

        $command->resolveSuitePaths($this->tmpDir . '/Suites/Missing');
    }
}
