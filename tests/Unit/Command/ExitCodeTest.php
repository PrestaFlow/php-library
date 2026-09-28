<?php

namespace PrestaFlow\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * A run that blows up (Chrome that does not start, a suites path that does not
 * exist) must not report success: CI reads the exit code, not the output.
 */
final class ExitCodeTest extends TestCase
{
    private string $tmpDir;
    private string $previousCwd;

    protected function setUp(): void
    {
        // The command writes its artefacts relative to the working directory:
        // run it from a throwaway one.
        $this->previousCwd = getcwd();
        $this->tmpDir = sys_get_temp_dir() . '/prestaflow-exit-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir . '/empty', 0777, true);
        chdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    public function testAMissingSuitesPathExitsNonZero(): void
    {
        $tester = $this->tester();

        $exitCode = $this->runCommand($tester, ['command' => 'run', 'folder' => $this->tmpDir . '/does-not-exist']);

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('ERROR', $tester->getErrorOutput());
        $this->assertStringContainsString('does-not-exist', $tester->getErrorOutput());
    }

    public function testAnUnknownCommandExitsNonZero(): void
    {
        $this->assertNotSame(0, $this->runCommand($this->tester(), ['command' => 'no-such-command']));
    }

    public function testAnEmptySuitesFolderStillExitsZero(): void
    {
        $this->assertSame(0, $this->runCommand($this->tester(), ['command' => 'run', 'folder' => $this->tmpDir . '/empty']));
    }

    /**
     * End to end on the real entry point, so the binary cannot drift away from
     * the application it is supposed to run.
     */
    public function testTheBinaryExitsNonZeroOnAMissingSuitesPath(): void
    {
        $bin = dirname(__DIR__, 3) . '/bin/prestaflow';

        $process = proc_open(
            [PHP_BINARY, $bin, 'run', $this->tmpDir . '/does-not-exist'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->tmpDir
        );
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertNotSame(0, $exitCode, "stdout:\n" . $stdout . "\nstderr:\n" . $stderr);
        $this->assertStringContainsString('does-not-exist', $stdout . $stderr);
    }

    /**
     * ExecuteSuite writes to console sections, which only a ConsoleOutput
     * offers: capturing stderr separately gives the tester one.
     */
    private function runCommand(ApplicationTester $tester, array $input): int
    {
        return $tester->run($input, ['capture_stderr_separately' => true]);
    }

    private function tester(): ApplicationTester
    {
        $application = new Application();
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }
}
