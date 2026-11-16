<?php

namespace PrestaFlow\Tests\Unit\Support;

use Evenement\EventEmitter;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\FailedRequests;

final class FailedRequestsTest extends TestCase
{
    private function session(string $id = 'S1'): EventEmitter
    {
        return new class($id) extends EventEmitter
        {
            private int $n = 0;

            public function __construct(private string $id) {}

            public function getSessionId(): string
            {
                return $this->id;
            }

            /** Requête envoyée puis réponse avec ce statut ; renvoie son identifiant. */
            public function respond(string $method, string $url, int $status, string $type = 'XHR'): string
            {
                $id = $this->send($method, $url, $type);
                $this->emit('method:Network.responseReceived', [['requestId' => $id, 'type' => $type, 'response' => ['url' => $url, 'status' => $status]]]);

                return $id;
            }

            /** @param array<string, mixed> $extra */
            public function fail(string $method, string $url, string $error, array $extra = [], string $type = 'Script'): string
            {
                $id = $this->send($method, $url, $type);
                $this->failed($id, $error, $extra, $type);

                return $id;
            }

            /** @param array<string, mixed> $extra */
            public function failed(string $id, string $error, array $extra = [], string $type = 'Script'): void
            {
                $this->emit('method:Network.loadingFailed', [['requestId' => $id, 'type' => $type, 'errorText' => $error] + $extra]);
            }

            private function send(string $method, string $url, string $type): string
            {
                $id = $this->id.'.'.(++$this->n);
                $this->emit('method:Network.requestWillBeSent', [['requestId' => $id, 'type' => $type, 'request' => ['method' => $method, 'url' => $url]]]);

                return $id;
            }
        };
    }

    public function test_http_errors_and_failed_loads_are_collected_per_test(): void
    {
        $session = $this->session();
        $requests = new FailedRequests;

        $requests->beginTest($session);
        $session->respond('GET', 'https://shop.test/', 200, 'Document');
        $session->respond('POST', 'https://shop.test/cart?ajax=1', 500);
        $session->respond('POST', 'https://shop.test/cart?ajax=1', 500);
        $session->respond('GET', 'https://shop.test/modules/pay/front.js', 404, 'Script');
        $session->fail('GET', 'https://api.carrier.test/points', 'net::ERR_NAME_NOT_RESOLVED', [], 'Fetch');

        $this->assertSame([
            ['method' => 'POST', 'url' => 'https://shop.test/cart?ajax=1', 'status' => 500, 'error' => null, 'type' => 'XHR', 'count' => 2],
            ['method' => 'GET', 'url' => 'https://shop.test/modules/pay/front.js', 'status' => 404, 'error' => null, 'type' => 'Script', 'count' => 1],
            ['method' => 'GET', 'url' => 'https://api.carrier.test/points', 'status' => null, 'error' => 'net::ERR_NAME_NOT_RESOLVED', 'type' => 'Fetch', 'count' => 1],
        ], $requests->endTest());

        $session->respond('GET', 'https://shop.test/entre-deux-tests', 500);
        $requests->beginTest($session);
        $this->assertSame([], $requests->endTest());
    }

    public function test_noise_is_ignored(): void
    {
        $session = $this->session();
        $requests = new FailedRequests;
        $requests->beginTest($session);

        $session->respond('GET', 'https://shop.test/favicon.ico', 404, 'Other');
        $session->fail('GET', 'https://shop.test/img/slider.jpg', 'net::ERR_ABORTED', ['canceled' => true], 'Image');
        $session->fail('GET', 'https://shop.test/stats.js', 'net::ERR_ABORTED', [], 'Script');
        $session->fail('GET', 'https://tracker.test/pixel.gif', 'net::ERR_BLOCKED_BY_CLIENT', ['blockedReason' => 'inspector'], 'Image');
        $session->fail('GET', 'data:image/png;base64,AAAA', 'net::ERR_INVALID_URL', [], 'Image');
        // Une réponse 4xx suivie d'un loadingFailed n'est comptée qu'une fois.
        $id = $session->respond('GET', 'https://shop.test/api/stock', 403, 'Fetch');
        $session->failed($id, 'net::ERR_FAILED', [], 'Fetch');

        $this->assertSame(['https://shop.test/api/stock'], array_column($requests->endTest(), 'url'));
    }

    public function test_only_the_followed_page_is_listened_to(): void
    {
        $old = $this->session('OLD');
        $new = $this->session('NEW');
        $requests = new FailedRequests;

        $requests->beginTest($old);
        $requests->follow($new);
        $old->respond('GET', 'https://shop.test/ancienne', 500);
        $new->respond('GET', 'https://shop.test/nouvelle', 500);

        $this->assertSame(['https://shop.test/nouvelle'], array_column($requests->endTest(), 'url'));
    }

    public function test_the_list_is_capped(): void
    {
        $session = $this->session();
        $requests = new FailedRequests;
        $requests->beginTest($session);
        for ($i = 0; $i < FailedRequests::MAX_PER_TEST + 5; $i++) {
            $session->respond('GET', "https://shop.test/p/{$i}", 404);
        }

        $this->assertCount(FailedRequests::MAX_PER_TEST, $requests->endTest());
    }

    public function test_a_broken_session_never_throws(): void
    {
        $requests = new FailedRequests;
        $requests->beginTest(new class
        {
            public function getSessionId(): string
            {
                throw new \RuntimeException('page fermée');
            }
        });

        $this->assertSame([], $requests->endTest());
    }
}
