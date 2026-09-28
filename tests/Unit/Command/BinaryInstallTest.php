<?php

namespace PrestaFlow\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;

/**
 * `composer require prestaflow/php-library` must give the project a working
 * ./vendor/bin/prestaflow, loading the PROJECT's autoloader (its Tests\ suites
 * live there, not in the library's).
 */
final class BinaryInstallTest extends TestCase
{
    private string $libRoot;
    private string $project;

    protected function setUp(): void
    {
        $this->libRoot = dirname(__DIR__, 3);
        $this->project = sys_get_temp_dir() . '/prestaflow-install-' . bin2hex(random_bytes(6));

        // Installed layout: <project>/vendor/prestaflow/php-library/bin/prestaflow.
        mkdir($this->project . '/vendor/prestaflow/php-library/bin', 0777, true);
        mkdir($this->project . '/vendor/bin', 0777, true);
        copy($this->libRoot . '/bin/prestaflow', $this->project . '/vendor/prestaflow/php-library/bin/prestaflow');

        // The project's autoloader: leaves a marker, then delegates to the real
        // one so the library classes resolve.
        file_put_contents($this->project . '/vendor/autoload.php', sprintf(
            "<?php\nfwrite(STDERR, \"PROJECT_AUTOLOAD\\n\");\nreturn require %s;\n",
            var_export($this->libRoot . '/vendor/autoload.php', true)
        ));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->project));
    }

    public function testComposerJsonExposesTheBinary(): void
    {
        $composer = json_decode(file_get_contents($this->libRoot . '/composer.json'), true);

        $this->assertSame(['bin/prestaflow'], $composer['bin'] ?? null);
    }

    public function testInstalledBinaryLoadsTheProjectAutoloader(): void
    {
        [$exitCode, $output] = $this->runPhp($this->project . '/vendor/prestaflow/php-library/bin/prestaflow');

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('PROJECT_AUTOLOAD', $output);
        $this->assertStringContainsString('run', $output);
    }

    /**
     * Composer's vendor/bin proxy sets $_composer_autoload_path, then includes
     * the real script.
     */
    public function testComposerBinProxyLoadsTheProjectAutoloader(): void
    {
        $proxy = $this->project . '/vendor/bin/prestaflow';
        file_put_contents($proxy, "<?php\n"
            . "\$GLOBALS['_composer_bin_dir'] = __DIR__;\n"
            . "\$GLOBALS['_composer_autoload_path'] = __DIR__ . '/..'.'/autoload.php';\n"
            . "return include __DIR__ . '/..'.'/prestaflow/php-library/bin/prestaflow';\n");

        [$exitCode, $output] = $this->runPhp($proxy);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('PROJECT_AUTOLOAD', $output);
        $this->assertStringContainsString('run', $output);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runPhp(string $script): array
    {
        // Run from an unrelated directory: the getcwd() fallback must not be
        // what makes these pass.
        $process = proc_open(
            [PHP_BINARY, $script, 'list', '--raw'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            sys_get_temp_dir()
        );
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
