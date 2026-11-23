<?php

namespace PrestaFlow\Tests\Unit\Tests;

use HeadlessChromium\Exception\BrowserConnectionFailed;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\RemoteBrowser;
use PrestaFlow\Library\Tests\TestsSuite;

final class RemoteBrowserConfigTest extends TestCase
{
    private const TOKEN = 'tok_9f8e7d6c5b4a3210';

    protected function tearDown(): void
    {
        unset($_ENV['PRESTAFLOW_BROWSER_WS'], $_ENV['PRESTAFLOW_BROWSER_WS_TOKEN']);
        TestsSuite::useBrowserOptions(null, null, null);
    }

    public function test_no_variable_means_no_remote_browser(): void
    {
        $this->assertNull(TestsSuite::remoteBrowserConfig());

        $_ENV['PRESTAFLOW_BROWSER_WS'] = '   ';
        $this->assertNull(TestsSuite::remoteBrowserConfig());
    }

    public function test_address_without_token_sends_no_header(): void
    {
        $_ENV['PRESTAFLOW_BROWSER_WS'] = 'wss://browser.example.test/devtools/browser?keep_alive=60000';

        $this->assertSame(
            ['uri' => 'wss://browser.example.test/devtools/browser?keep_alive=60000', 'headers' => []],
            TestsSuite::remoteBrowserConfig()
        );
    }

    public function test_token_is_sent_as_bearer_authorization(): void
    {
        $_ENV['PRESTAFLOW_BROWSER_WS'] = ' ws://127.0.0.1:9222/devtools/browser/abc ';
        $_ENV['PRESTAFLOW_BROWSER_WS_TOKEN'] = self::TOKEN;

        $this->assertSame(
            ['uri' => 'ws://127.0.0.1:9222/devtools/browser/abc', 'headers' => ['Authorization' => 'Bearer '.self::TOKEN]],
            TestsSuite::remoteBrowserConfig()
        );
    }

    public function test_token_alone_does_not_enable_remote_mode(): void
    {
        $_ENV['PRESTAFLOW_BROWSER_WS_TOKEN'] = self::TOKEN;

        $this->assertNull(TestsSuite::remoteBrowserConfig());
    }

    public function test_redact_masks_secrets_credentials_and_sensitive_query_parameters(): void
    {
        $text = 'wss://user:pw@host.test/b?apiKey=abc123&keep_alive=60000&token=xyz '.self::TOKEN;

        $redacted = RemoteBrowser::redact($text, [self::TOKEN]);

        $this->assertSame('wss://***@host.test/b?apiKey=***&keep_alive=60000&token=*** ***', $redacted);
    }

    public function test_non_websocket_address_is_refused_without_leaking_the_token(): void
    {
        try {
            RemoteBrowser::connect('https://host.test/?token='.self::TOKEN, ['Authorization' => 'Bearer '.self::TOKEN]);
            $this->fail('adresse https acceptée');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('ws:// ou wss://', $e->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
        }
    }

    public function test_unreachable_browser_error_never_contains_the_token(): void
    {
        // Port 1 : connexion refusée tout de suite.
        try {
            RemoteBrowser::connect('ws://127.0.0.1:1/devtools/browser?token='.self::TOKEN, ['Authorization' => 'Bearer '.self::TOKEN], 2000);
            $this->fail('connexion acceptée');
        } catch (BrowserConnectionFailed $e) {
            $this->assertStringContainsString('Navigateur distant injoignable', $e->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, $e->getTraceAsString());
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_remote_mode_never_falls_back_to_a_local_chrome(): void
    {
        $_ENV['PRESTAFLOW_BROWSER_WS'] = 'ws://127.0.0.1:1/devtools/browser';
        $_ENV['PRESTAFLOW_BROWSER_WS_TOKEN'] = self::TOKEN;
        TestsSuite::scopeBrowserFilesTo('remote-test-'.getmypid());

        try {
            TestsSuite::getBrowser();
            $this->fail('un navigateur a été obtenu');
        } catch (BrowserConnectionFailed $e) {
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
        }

        $this->assertFalse(TestsSuite::pfHasBrowser());
        $this->assertFileDoesNotExist(TestsSuite::getFilePath('.browser'));
        TestsSuite::scopeBrowserFilesTo(null);
    }

    public function test_remote_mode_without_force_does_not_connect(): void
    {
        $_ENV['PRESTAFLOW_BROWSER_WS'] = 'ws://127.0.0.1:1/devtools/browser';

        $this->assertNull(TestsSuite::getBrowser(force: false));
    }

    public function test_reset_in_remote_mode_leaves_local_browser_files_alone(): void
    {
        $socket = TestsSuite::getFilePath('.browser');
        file_put_contents($socket, 'ws://127.0.0.1:1/devtools/browser/local');
        $_ENV['PRESTAFLOW_BROWSER_WS'] = 'ws://127.0.0.1:1/devtools/browser';

        try {
            TestsSuite::resetBrowser();
            $this->assertFileExists($socket);
        } finally {
            @unlink($socket);
        }
    }

    public function test_without_variables_get_browser_without_force_is_unchanged(): void
    {
        @unlink(TestsSuite::getFilePath('.browser'));

        $this->assertNull(TestsSuite::getBrowser(force: false));
    }
}
