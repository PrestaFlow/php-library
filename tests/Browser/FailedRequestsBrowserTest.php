<?php

namespace PrestaFlow\Tests\Browser;

use HeadlessChromium\BrowserFactory;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\FailedRequests;

final class FailedRequestsBrowserTest extends TestCase
{
    public function test_failed_requests_are_collected_in_a_real_chrome(): void
    {
        $root = sys_get_temp_dir().'/pf-failed-requests-'.getmypid();
        @mkdir($root);
        file_put_contents($root.'/index.php', <<<'PHP'
            <?php
            if ($_SERVER['REQUEST_URI'] === '/') {
                echo '<meta charset="utf-8"><body><script src="/modules/pay/front.js"></script>'
                    .'<script>fetch("/cart?ajax=1", {method: "POST"});</script></body>';
                return;
            }
            http_response_code(str_starts_with($_SERVER['REQUEST_URI'], '/cart') ? 500 : 404);
            PHP);
        $port = random_int(20000, 40000);
        $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, $root.'/index.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        usleep(300000);

        try {
            $browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true]);
        } catch (\Throwable $e) {
            proc_terminate($server);
            if (getenv('CI') === false || getenv('CI') === '') {
                $this->markTestSkipped('Chrome indisponible : '.$e->getMessage());
            }
            $this->fail('Chrome indisponible en CI : '.$e->getMessage());
        }

        try {
            $page = $browser->createPage();
            $requests = new FailedRequests;
            $requests->beginTest($page->getSession());

            $page->navigate('http://127.0.0.1:'.$port.'/')->waitForNavigation();
            usleep(300000);
            $page->evaluate('1')->getReturnValue();

            $found = array_map(fn ($r) => $r['method'].' '.parse_url($r['url'], PHP_URL_PATH).' '.$r['status'], $requests->endTest());
            sort($found);
            $this->assertSame(['GET /modules/pay/front.js 404', 'POST /cart 500'], $found);
        } finally {
            $browser->close();
            proc_terminate($server);
            @unlink($root.'/index.php');
            @rmdir($root);
        }
    }
}
