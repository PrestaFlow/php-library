<?php

namespace PrestaFlow\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Command\ExecuteSuite;

/**
 * One broken suite must not take the whole run down: the other suites run,
 * results.json is written, and the run exits non-zero. A PHP file without a
 * namespace (a module's index.php guard) is skipped without a warning.
 */
final class ExecuteSuiteRobustnessTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/prestaflow-robust-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    public function testNamespaceOfAFileWithoutNamespaceIsEmpty(): void
    {
        $command = new ExecuteSuite();

        $this->assertSame('', $command->namespaceOf("<?php\nheader('Location: ../');\nexit;\n"));
        $this->assertSame('Tests\Suites', $command->namespaceOf("<?php\n\nnamespace Tests\Suites;\n\nclass Smoke {}\n"));
        $this->assertSame('Tests\Suites', $command->namespaceOf("<?php\nnamespace Tests\Suites {\n}\n"));
    }

    public function testABrokenSuiteFailsAloneAndResultsAreWritten(): void
    {
        [$exitCode, $output] = $this->runBinary($this->fixtures('Robustness'));

        $this->assertNotSame(0, $exitCode, $output);
        $this->assertStringNotContainsString('Undefined array key', $output);
        $this->assertStringContainsString('BBrokenInit could not run', $output);

        $suites = $this->results($output);
        $bySuite = array_column($suites, null, 'suite');

        $this->assertCount(3, $suites, $output);
        $this->assertSame(1, $bySuite['PrestaFlow\Tests\Fixtures\Suites\Robustness\APasses']['stats']['passes']);
        $this->assertSame(1, $bySuite['PrestaFlow\Tests\Fixtures\Suites\Robustness\CAlsoPasses']['stats']['passes']);

        $broken = $bySuite['PrestaFlow\Tests\Fixtures\Suites\Robustness\BBrokenInit'];
        $this->assertSame(1, $broken['stats']['failures']);
        $this->assertSame('fail', $broken['tests'][0]['state']);
        $this->assertStringContainsString('PrestaFlowFixtureMissingClass', $broken['tests'][0]['expect']['fail'][0]);
    }

    public function testASuiteThatCallsExitStillWritesResultsAndFails(): void
    {
        [$exitCode, $output] = $this->runBinary($this->fixtures('RobustnessExit'));

        $this->assertSame(1, $exitCode, $output);

        $bySuite = array_column($this->results($output), null, 'suite');

        $this->assertSame(1, $bySuite['PrestaFlow\Tests\Fixtures\Suites\RobustnessExit\APasses']['stats']['passes'], $output);
        $exited = $bySuite['PrestaFlow\Tests\Fixtures\Suites\RobustnessExit\BExits'];
        $this->assertSame(1, $exited['stats']['failures']);
        $this->assertStringContainsString('process stopped', $exited['tests'][0]['expect']['fail'][0]);
    }

    private function fixtures(string $folder): string
    {
        return dirname(__DIR__, 2) . '/Fixtures/Suites/' . $folder;
    }

    /** @return array{int, string} */
    private function runBinary(string $folder): array
    {
        $bin = dirname(__DIR__, 3) . '/bin/prestaflow';

        $process = proc_open(
            [PHP_BINARY, $bin, 'run', '--output=JSON', '--file', $folder],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->tmpDir
        );
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), "stdout:\n" . $stdout . "\nstderr:\n" . $stderr];
    }

    private function results(string $output): array
    {
        $path = $this->tmpDir . '/prestaflow/results.json';
        $this->assertFileExists($path, $output);

        return json_decode((string) file_get_contents($path), true)['suites'];
    }
}
