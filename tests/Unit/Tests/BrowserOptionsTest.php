<?php
// tests/Unit/Tests/BrowserOptionsTest.php
namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

final class BrowserOptionsTest extends TestCase
{
    protected function tearDown(): void
    {
        TestsSuite::useBrowserOptions(null, null, null);
        putenv('PRESTAFLOW_WINDOW_SIZE_WIDTH');
        putenv('PRESTAFLOW_WINDOW_SIZE_HEIGHT');
        putenv('PRESTAFLOW_USER_AGENT');
        unset($_ENV['PRESTAFLOW_WINDOW_SIZE_WIDTH'], $_ENV['PRESTAFLOW_WINDOW_SIZE_HEIGHT'], $_ENV['PRESTAFLOW_USER_AGENT']);
    }

    public function test_overrides_win(): void
    {
        TestsSuite::useBrowserOptions(1024, 768, 'UA');
        $this->assertSame(['windowSize' => [1024, 768], 'userAgent' => 'UA'], TestsSuite::browserOptions());
    }

    public function test_defaults_without_override(): void
    {
        $this->assertSame(['windowSize' => [1920, 1080], 'userAgent' => 'PrestaFlow'], TestsSuite::browserOptions());
    }

    public function test_env_used_without_override(): void
    {
        $_ENV['PRESTAFLOW_WINDOW_SIZE_WIDTH'] = '390';
        $_ENV['PRESTAFLOW_WINDOW_SIZE_HEIGHT'] = '844';
        $_ENV['PRESTAFLOW_USER_AGENT'] = 'Mobile';
        $this->assertSame(['windowSize' => [390, 844], 'userAgent' => 'Mobile'], TestsSuite::browserOptions());
    }

    public function test_reset_browser_removes_socket_files(): void
    {
        $socket = TestsSuite::getFilePath('.browser');
        $options = TestsSuite::getFilePath('.browser-options');
        file_put_contents($socket, 'ws://127.0.0.1:1/devtools/browser/nope');
        file_put_contents($options, '{}');

        TestsSuite::resetBrowser();

        $this->assertFileDoesNotExist($socket);
        $this->assertFileDoesNotExist($options);
    }
}
