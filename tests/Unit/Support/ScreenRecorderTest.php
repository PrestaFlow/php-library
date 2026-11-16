<?php

namespace PrestaFlow\Tests\Unit\Support;

use Evenement\EventEmitter;
use HeadlessChromium\Communication\Message;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\ScreenRecorder;

/** Session CDP factice : garde les messages envoyés, émet des événements à la demande. */
final class FakeSession extends EventEmitter
{
    /** @var list<Message> */
    public array $sent = [];

    public function __construct(private string $id = 'S1') {}

    public function getSessionId(): string
    {
        return $this->id;
    }

    public function sendMessage(Message $message): void
    {
        $this->sent[] = $message;
    }

    /** @return list<string> */
    public function methods(): array
    {
        return array_map(fn (Message $m) => $m->getMethod(), $this->sent);
    }

    public function frame(float $t, int $ack = 1): void
    {
        $this->emit('method:Page.screencastFrame', [['data' => base64_encode("\xFF\xD8\xFFjpeg"), 'sessionId' => $ack, 'metadata' => ['timestamp' => $t]]]);
    }
}

final class ScreenRecorderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/pf-video-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            exec('rm -rf '.escapeshellarg($this->dir));
        }
        putenv('PRESTAFLOW_RECORD_DIR');
        unset($_ENV['PRESTAFLOW_RECORD_DIR']);
        ScreenRecorder::reset();
    }

    /** @return list<array<string, mixed>> */
    private function lines(int $test): array
    {
        return array_map(fn ($l) => json_decode($l, true), file($this->dir.'/'.$test.'/frames.jsonl', FILE_IGNORE_NEW_LINES));
    }

    public function test_it_is_off_without_the_variable(): void
    {
        $this->assertNull(ScreenRecorder::current());
        $_ENV['PRESTAFLOW_RECORD_DIR'] = $this->dir;
        $this->assertSame($this->dir, ScreenRecorder::current()?->dir());
    }

    public function test_frames_are_written_acked_and_framed_by_test_markers(): void
    {
        $session = new FakeSession;
        $recorder = new ScreenRecorder($this->dir);

        $recorder->beginTest(0, 'ajoute au panier', $session);
        $session->frame(1000.0, 7);
        $session->frame(1000.5, 8);
        $recorder->fail('Le panier est vide');
        $recorder->endTest('fail');
        $session->frame(1001.0, 9); // entre deux tests : accusé, pas écrit

        $this->assertSame(['Page.startScreencast', 'Page.screencastFrameAck', 'Page.screencastFrameAck', 'Page.screencastFrameAck'], $session->methods());
        $this->assertSame(['sessionId' => 7], $session->sent[1]->getParams());
        $this->assertFileExists($this->dir.'/0/1.jpg');
        $this->assertFileExists($this->dir.'/0/2.jpg');
        $this->assertFileDoesNotExist($this->dir.'/0/3.jpg');

        $lines = $this->lines(0);
        $this->assertSame(['start', null, null, 'fail', 'end'], array_map(fn ($l) => $l['m'] ?? null, $lines));
        $this->assertSame('ajoute au panier', $lines[0]['title']);
        $this->assertEquals(['f' => '1.jpg', 't' => 1000.0], $lines[1]);
        $this->assertSame('Le panier est vide', $lines[3]['message']);
        $this->assertSame('fail', $lines[4]['state']);
    }

    public function test_frames_are_capped_by_rate_and_count(): void
    {
        $session = new FakeSession;
        $recorder = new ScreenRecorder($this->dir);
        $recorder->beginTest(2, 't', $session);

        $session->frame(10.0);
        $session->frame(10.05); // < 1/10 s : ignorée
        for ($i = 1; $i <= ScreenRecorder::MAX_FRAMES_PER_TEST + 5; $i++) {
            $session->frame(10.0 + $i * 0.2);
        }
        $recorder->endTest('pass');

        $this->assertCount(ScreenRecorder::MAX_FRAMES_PER_TEST, glob($this->dir.'/2/*.jpg'));
        $markers = array_values(array_filter(array_map(fn ($l) => $l['m'] ?? null, $this->lines(2))));
        $this->assertSame(['start', 'truncated', 'end'], $markers);
        // Toutes les images sont accusées, même ignorées.
        $this->assertSame(ScreenRecorder::MAX_FRAMES_PER_TEST + 7, count(array_filter($session->methods(), fn ($m) => $m === 'Page.screencastFrameAck')));
    }

    public function test_a_new_page_moves_the_stream_to_its_session(): void
    {
        $first = new FakeSession('A');
        $second = new FakeSession('B');
        $recorder = new ScreenRecorder($this->dir);

        $recorder->beginTest(0, 't', $first);
        $recorder->follow($first); // même session : rien
        $recorder->follow($second);
        $first->frame(1.0);  // ancienne page : plus écrite
        $second->frame(2.0);
        $recorder->endTest('pass');

        $this->assertSame(['Page.startScreencast', 'Page.stopScreencast', 'Page.screencastFrameAck'], $first->methods());
        $this->assertSame(['Page.startScreencast', 'Page.screencastFrameAck'], $second->methods());
        $this->assertEquals([['f' => '1.jpg', 't' => 2.0]], array_values(array_filter($this->lines(0), fn ($l) => isset($l['f']))));
    }

    public function test_a_broken_session_never_breaks_the_run(): void
    {
        $session = new class extends EventEmitter
        {
            public function getSessionId(): string
            {
                return 'X';
            }

            public function sendMessage(Message $m): void
            {
                throw new \RuntimeException('socket fermé');
            }
        };
        $recorder = new ScreenRecorder($this->dir);

        $recorder->beginTest(0, 't', $session);
        $recorder->endTest('pass');
        $recorder->stop();

        $this->addToAssertionCount(1);
    }
}
