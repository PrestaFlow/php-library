<?php

namespace PrestaFlow\Tests\Browser;

use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\BrowserConnectionFailed;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\RemoteBrowser;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Navigateur distant (PRESTAFLOW_BROWSER_WS) : un Chrome local tient lieu de
 * fournisseur, joint en ws:// avec un en-tête Authorization factice que Chrome
 * ignore. Chaque « session » est un Chrome neuf, comme une session Cloudflare.
 */
final class RemoteBrowserBrowserTest extends TestCase
{
    /** @var list<ProcessAwareBrowser> */
    private array $chromes = [];

    protected function tearDown(): void
    {
        TestsSuite::resetBrowser();
        unset($_ENV['PRESTAFLOW_BROWSER_WS'], $_ENV['PRESTAFLOW_BROWSER_WS_TOKEN']);
        TestsSuite::useBrowserOptions(null, null, null);
        TestsSuite::scopeBrowserFilesTo(null);

        foreach ($this->chromes as $chrome) {
            try {
                $chrome->close();
            } catch (\Throwable) {
                // déjà fermé par la session distante
            }
        }
    }

    /** Lance un Chrome local et rend son adresse ws://, que la librairie verra comme distante. */
    private function startProvider(): string
    {
        try {
            $chrome = (new BrowserFactory())->createBrowser(['headless' => true, 'noSandbox' => true, 'keepAlive' => true]);
        } catch (\Throwable $e) {
            if (getenv('CI') === false || getenv('CI') === '') {
                $this->markTestSkipped('Chrome indisponible : '.$e->getMessage());
            }
            $this->fail('Chrome indisponible en CI : '.$e->getMessage());
        }
        $this->chromes[] = $chrome;

        return $chrome->getSocketUri();
    }

    private function waitUntilUnreachable(string $uri): void
    {
        $port = (int) parse_url($uri, PHP_URL_PORT);
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $probe = @stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $errstr, 0.5);
            if ($probe === false) {
                return;
            }
            fclose($probe);
            usleep(200000);
        }
        $this->fail('le Chrome de la première session ne s\'est pas arrêté');
    }

    public function test_connect_applies_size_and_user_agent_to_every_page(): void
    {
        $uri = $this->startProvider();

        $browser = RemoteBrowser::connect($uri, ['Authorization' => 'Bearer factice'], 10000, [412, 915], 'PrestaFlowRemote/1.0');

        try {
            foreach ([$browser->createPage(), $browser->createPage()] as $page) {
                $page->navigate('data:text/html,<body>remote</body>')->waitForNavigation();
                $this->assertSame(412, $page->evaluate('window.innerWidth')->getReturnValue());
                $this->assertSame(915, $page->evaluate('window.innerHeight')->getReturnValue());
                $this->assertSame('PrestaFlowRemote/1.0', $page->evaluate('navigator.userAgent')->getReturnValue());
            }
        } finally {
            $browser->close();
        }
    }

    public function test_get_browser_uses_the_remote_browser_and_reset_opens_a_new_session(): void
    {
        TestsSuite::scopeBrowserFilesTo('remote-browser-test-'.getmypid());
        $_ENV['PRESTAFLOW_BROWSER_WS'] = $this->startProvider();
        $_ENV['PRESTAFLOW_BROWSER_WS_TOKEN'] = 'jeton-factice-0123456789';
        TestsSuite::useBrowserOptions(390, 844, 'PrestaFlowMobile/1.0');

        $first = TestsSuite::getBrowser();
        $this->assertInstanceOf(RemoteBrowser::class, $first);
        $this->assertSame($first, TestsSuite::getBrowser(), 'session gardée entre deux appels');

        $page = TestsSuite::getPage();
        $page->navigate('data:text/html,<body>remote</body>')->waitForNavigation();
        $this->assertSame(390, $page->evaluate('window.innerWidth')->getReturnValue());
        $this->assertSame('PrestaFlowMobile/1.0', $page->evaluate('navigator.userAgent')->getReturnValue());
        $this->assertFileDoesNotExist(TestsSuite::getFilePath('.browser'), 'rien dans .browser en mode distant');

        // Changement de taille (applyDevicePreset) : la session distante est fermée…
        TestsSuite::useBrowserOptions(1280, 720, 'PrestaFlowDesktop/1.0');
        TestsSuite::resetBrowser();
        $this->assertFalse($first->getConnection()->isConnected());
        $this->assertFalse(TestsSuite::pfHasBrowser());

        // … et sans fournisseur joignable, jamais de Chrome local de repli.
        // (Chrome met un instant à s'arrêter après Browser.close.)
        $this->waitUntilUnreachable((string) $_ENV['PRESTAFLOW_BROWSER_WS']);
        try {
            TestsSuite::getBrowser();
            $this->fail('un navigateur a été obtenu sans fournisseur');
        } catch (BrowserConnectionFailed $e) {
            $this->assertStringNotContainsString('jeton-factice-0123456789', $e->getMessage());
        }

        // Le fournisseur donne une nouvelle session : getBrowser() l'ouvre, à la nouvelle taille.
        $_ENV['PRESTAFLOW_BROWSER_WS'] = $this->startProvider();
        $second = TestsSuite::getBrowser();
        $this->assertInstanceOf(RemoteBrowser::class, $second);
        $this->assertNotSame($first, $second);
        $this->assertNotInstanceOf(ProcessAwareBrowser::class, $second);

        $page = TestsSuite::getPage();
        $page->navigate('data:text/html,<body>remote</body>')->waitForNavigation();
        $this->assertSame(1280, $page->evaluate('window.innerWidth')->getReturnValue());
        $this->assertSame('PrestaFlowDesktop/1.0', $page->evaluate('navigator.userAgent')->getReturnValue());
    }
}
