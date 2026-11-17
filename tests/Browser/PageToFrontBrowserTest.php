<?php

namespace PrestaFlow\Tests\Browser;

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use PHPUnit\Framework\TestCase;

/**
 * Pourquoi TestsSuite::bringPageToFront() : une page qui n'est pas l'onglet actif est masquée
 * (visibilityState = hidden) et Chrome n'y répond à un événement souris qu'après plusieurs
 * secondes, au-delà du délai CDP par défaut de 5 s (« Operation timed out » sur Stripe Checkout).
 */
final class PageToFrontBrowserTest extends TestCase
{
    public function test_a_hidden_page_is_slow_to_take_mouse_events_until_brought_to_front(): void
    {
        try {
            $browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true, 'sendSyncDefaultTimeout' => 20000]);
        } catch (\Throwable $e) {
            if (getenv('CI') === false || getenv('CI') === '') {
                $this->markTestSkipped('Chrome indisponible : '.$e->getMessage());
            }
            $this->fail('Chrome indisponible en CI : '.$e->getMessage());
        }

        try {
            $page = $browser->createPage();
            $page->navigate('data:text/html,<body>run</body>')->waitForNavigation();
            $browser->createPage(); // devient l'onglet actif

            $visibility = fn () => $page->evaluate('document.visibilityState')->getReturnValue();
            $move = function () use ($page): float {
                $start = microtime(true);
                $page->getSession()->sendMessageSync(new Message('Input.dispatchMouseEvent', ['type' => 'mouseMoved', 'x' => 10, 'y' => 10]), 20000);

                return microtime(true) - $start;
            };

            $this->assertSame('hidden', $visibility());
            $this->assertGreaterThan(2.0, $move(), 'page masquée : événement souris ralenti');

            $page->getSession()->sendMessageSync(new Message('Page.bringToFront'), 3000);

            $this->assertSame('visible', $visibility());
            $this->assertLessThan(1.0, $move(), 'page au premier plan : réponse immédiate');
        } finally {
            $browser->close();
        }
    }
}
