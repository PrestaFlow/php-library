<?php

namespace PrestaFlow\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\NonBlockingReadSocket;

final class NonBlockingReadSocketTest extends TestCase
{
    /** @return array{0: NonBlockingReadSocket, 1: resource} socket branchée sur une paire locale, et l'autre bout */
    private function socketPair(): array
    {
        [$ours, $theirs] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        stream_set_timeout($ours, 5);

        $socket = new NonBlockingReadSocket('ws://127.0.0.1:1/');
        $set = function ($resource): void {
            $this->socket = $resource;
            $this->connected = true;
        };
        \Closure::bind($set, $socket, NonBlockingReadSocket::class)($ours);

        return [$socket, $theirs];
    }

    public function test_returns_available_data_and_restores_blocking_mode(): void
    {
        [$socket, $peer] = $this->socketPair();
        fwrite($peer, 'abc');

        $this->assertSame('abc', $socket->receive(4096, 1.0));
        $this->assertTrue(stream_get_meta_data($socket->getResource())['blocked']);
    }

    public function test_empty_socket_returns_at_once(): void
    {
        [$socket, $peer] = $this->socketPair(); // $peer gardé ouvert : sinon fin de flux

        $start = microtime(true);
        $this->assertSame('', $socket->receive());
        $this->assertLessThan(0.5, microtime(true) - $start);
        $this->assertTrue(stream_get_meta_data($socket->getResource())['blocked']);
    }

    public function test_waits_for_data_when_asked(): void
    {
        [$socket, $peer] = $this->socketPair(); // $peer gardé ouvert : sinon fin de flux

        $start = microtime(true);
        $this->assertSame('', $socket->receive(4096, 0.3));
        $this->assertGreaterThanOrEqual(0.25, microtime(true) - $start);
    }
}
