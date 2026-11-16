<?php

namespace PrestaFlow\Tests\Browser;

use HeadlessChromium\BrowserFactory;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\ScreenRecorder;

/** Le flux d'images de Chrome (Page.startScreencast) arrive et s'écrit, dans un vrai Chrome. */
final class ScreenRecorderBrowserTest extends TestCase
{
    public function test_a_changing_page_is_recorded_as_jpeg_frames(): void
    {
        try {
            $browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true]);
        } catch (\Throwable $e) {
            if (getenv('CI') === false || getenv('CI') === '') {
                $this->markTestSkipped('Chrome indisponible : '.$e->getMessage());
            }
            $this->fail('Chrome indisponible en CI : '.$e->getMessage());
        }

        $dir = sys_get_temp_dir().'/pf-video-browser-'.bin2hex(random_bytes(4));
        try {
            $page = $browser->createPage();
            $page->navigate('data:text/html,<body style="margin:0"><div id="b" style="height:100vh">0</div></body>')->waitForNavigation();
            $recorder = new ScreenRecorder($dir);
            $recorder->beginTest(0, 'compteur', $page->getSession());

            // La page change : Chrome envoie des images, lues pendant les appels synchrones.
            for ($i = 1; $i <= 12; $i++) {
                $page->evaluate("document.getElementById('b').textContent = '{$i}'; document.body.style.background = 'hsl(".($i * 30).",80%,50%)'")->getReturnValue();
                usleep(120000);
                $recorder->pump(50);
            }
            $recorder->endTest('pass');

            // Test suivant, sur la même page, sans rien changer : la capture de départ suffit à lui donner une image.
            $recorder->beginTest(1, 'lecture seule', $page->getSession());
            $page->evaluate("document.getElementById('b').textContent")->getReturnValue();
            $recorder->endTest('pass');
            $recorder->stop();
            $this->assertNotSame([], glob($dir.'/1/*.jpg'), 'un test sans changement a son image de départ');
            $this->assertStringStartsWith("\xFF\xD8\xFF", (string) file_get_contents($dir.'/1/1.jpg'));

            $frames = glob($dir.'/0/*.jpg');
            $this->assertGreaterThanOrEqual(3, count($frames), 'au moins quelques images');
            $this->assertStringStartsWith("\xFF\xD8\xFF", (string) file_get_contents($frames[0]));
            $lines = array_map(fn ($l) => json_decode($l, true), file($dir.'/0/frames.jsonl', FILE_IGNORE_NEW_LINES));
            $this->assertSame('start', $lines[0]['m']);
            $this->assertSame('end', end($lines)['m']);
        } finally {
            $browser->close();
            exec('rm -rf '.escapeshellarg($dir));
        }
    }
}
