<?php

namespace PrestaFlow\Tests\Browser;

use HeadlessChromium\BrowserFactory;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\ConsoleErrors;

/** Les erreurs JS d'une vraie page arrivent par Runtime.exceptionThrown et consoleAPICalled. */
final class ConsoleErrorsBrowserTest extends TestCase
{
    public function test_page_errors_are_collected_in_a_real_chrome(): void
    {
        try {
            $browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true]);
        } catch (\Throwable $e) {
            if (getenv('CI') === false || getenv('CI') === '') {
                $this->markTestSkipped('Chrome indisponible : '.$e->getMessage());
            }
            $this->fail('Chrome indisponible en CI : '.$e->getMessage());
        }

        try {
            $page = $browser->createPage();
            $errors = new ConsoleErrors;
            $errors->beginTest($page->getSession());

            // Un module qui casse au chargement, puis un console.error et une exception asynchrone.
            $html = '<meta charset="utf-8"><body><script>console.error("Paiement indisponible", 42); undefinedModule.init();</script>'
                .'<script>setTimeout(function(){ throw new Error("après coup"); }, 50);</script></body>';
            $page->navigate('data:text/html,'.rawurlencode($html))->waitForNavigation();
            usleep(200000);
            $page->evaluate('1')->getReturnValue();

            $messages = array_column($errors->endTest(), 'message');
            $this->assertContains('Paiement indisponible 42', $messages);
            $this->assertNotEmpty(array_filter($messages, fn ($m) => str_contains($m, 'undefinedModule is not defined')), implode(' | ', $messages));
            $this->assertNotEmpty(array_filter($messages, fn ($m) => str_contains($m, 'après coup')), implode(' | ', $messages));
        } finally {
            $browser->close();
        }
    }
}
