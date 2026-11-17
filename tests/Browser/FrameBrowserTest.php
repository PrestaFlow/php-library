<?php

namespace PrestaFlow\Tests\Browser;

use HeadlessChromium\BrowserFactory;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\Frame;

/**
 * Iframe d'un autre domaine (127.0.0.1 → localhost : deux sites, donc deux processus) : le
 * formulaire ne réagit qu'aux vrais clics et aux vraies frappes, comme les champs de paiement.
 */
final class FrameBrowserTest extends TestCase
{
    private string $root;

    /** @var list<resource> */
    private array $servers = [];

    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            proc_terminate($server);
        }
        foreach (glob($this->root.'/*/index.php') ?: [] as $file) {
            @unlink($file);
            @rmdir(dirname($file));
        }
        @rmdir($this->root);
    }

    public function test_actions_reach_a_cross_origin_iframe(): void
    {
        $this->root = sys_get_temp_dir().'/pf-frame-'.getmypid();
        $outerPort = random_int(20000, 30000);
        $innerPort = $outerPort + 1;
        $this->serve('outer', '127.0.0.1', $outerPort, '<meta charset="utf-8"><body><h1>Boutique</h1>'
            .'<iframe src="http://localhost:'.$innerPort.'/pay" style="width:600px;height:400px;margin-top:300px"></iframe></body>');
        $this->serve('inner', 'localhost', $innerPort, <<<'HTML'
            <meta charset="utf-8"><body style="height:900px">
            <div id="methods" style="margin-top:500px">
              <div role="button" id="bancontact">Bancontact</div>
              <div role="button" id="card-tab">Carte bancaire</div>
              <div role="button">EPS</div>
            </div>
            <div id="card" hidden><input id="cardNumber"><input id="cardExpiry"></div>
            <script>
            window.trustedClick = false; window.keydowns = 0;
            document.getElementById('card-tab').addEventListener('click', function (e) {
                window.trustedClick = e.isTrusted;
                if (e.isTrusted) { document.getElementById('card').hidden = false; }
            });
            document.addEventListener('keydown', function (e) { if (e.isTrusted) { window.keydowns++; } });
            </script></body>
            HTML);

        try {
            $browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [1000, 700], 'noSandbox' => true]);
        } catch (\Throwable $e) {
            if (getenv('CI') === false || getenv('CI') === '') {
                $this->markTestSkipped('Chrome indisponible : '.$e->getMessage());
            }
            $this->fail('Chrome indisponible en CI : '.$e->getMessage());
        }

        try {
            $page = $browser->createPage();
            $page->navigate('http://127.0.0.1:'.$outerPort.'/')->waitForNavigation();

            $this->assertContains('http://localhost:'.$innerPort.'/pay', Frame::urls($page));
            $this->assertNull(Frame::find($page, 'introuvable.test', 500));

            $frame = Frame::find($page, 'localhost:'.$innerPort);
            $this->assertNotNull($frame);
            $this->assertFalse($frame->has('#cardNumber', 300), 'caché tant que la carte n\'est pas choisie');

            // Sous la ligne de flottaison de l'iframe : défilé puis cliqué pour de vrai. Le plus
            // petit élément qui porte le texte, pas le conteneur #methods (son centre est ailleurs).
            $frame->clickText('carte BANCAIRE', '[role=button],div');
            $this->assertTrue($frame->evaluate('window.trustedClick'));
            $this->assertTrue($frame->has('#cardNumber'));

            $frame->type('#cardNumber', '4242 4242');
            $frame->type('#cardExpiry', '1234');
            $this->assertSame('4242 4242', $frame->value('#cardNumber'));
            $this->assertSame('1234', $frame->value('#cardExpiry'));
            $this->assertSame(13, $frame->evaluate('window.keydowns'));

            $frame->type('#cardExpiry', '0130', clear: true);
            $this->assertSame('0130', $frame->value('#cardExpiry'));
            $this->assertNull($frame->value('#absent'));
        } finally {
            $browser->close();
        }
    }

    private function serve(string $name, string $host, int $port, string $html): void
    {
        @mkdir($this->root.'/'.$name, 0777, true);
        file_put_contents($this->root.'/'.$name.'/index.php', '<?php ?>'.$html);
        $this->servers[] = proc_open(
            [PHP_BINARY, '-S', $host.':'.$port, $this->root.'/'.$name.'/index.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        usleep(300000);
    }
}
