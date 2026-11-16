<?php

namespace PrestaFlow\Library\Support;

use HeadlessChromium\Communication\Message;
use Throwable;

/**
 * Vidéo de débogage d'un run : le flux d'images de Chrome (Page.startScreencast), rangé par test.
 *
 * Activé par PRESTAFLOW_RECORD_DIR (dossier de sortie, posé par l'appelant). Pour chaque test :
 *   {dossier}/{index}/{n}.jpg      les images, numérotées à partir de 1 ;
 *   {dossier}/{index}/frames.jsonl une ligne par image ({"f":"3.jpg","t":…}) et par repère
 *                                  ({"m":"start|end|fail|truncated","t":…, …}).
 * « t » est en secondes depuis l'epoch (horodatage de l'image fourni par Chrome).
 *
 * Chrome n'envoie l'image suivante qu'après l'accusé (Page.screencastFrameAck) : chaque image
 * est accusée, même ignorée. chrome-php ne lit les événements qu'en lisant le socket ; les appels
 * synchrones des tests suffisent pendant l'activité, pump() vide la file entre deux tests.
 *
 * L'enregistrement ne doit jamais faire échouer un run : toute erreur le coupe, sans exception.
 */
class ScreenRecorder
{
    public const MAX_FPS = 10;

    public const MAX_FRAMES_PER_TEST = 1500;

    private static ?self $instance = null;

    private ?object $session = null;

    private ?string $sessionId = null;

    private ?int $test = null;

    private int $frames = 0;

    private float $lastFrameAt = 0.0;

    private bool $truncated = false;

    private bool $broken = false;

    /** Le flux voulu par Chrome : JPEG réduit, une image sur deux. */
    public const SCREENCAST = ['format' => 'jpeg', 'quality' => 55, 'maxWidth' => 1280, 'maxHeight' => 1280, 'everyNthFrame' => 2];

    public function __construct(private string $dir) {}

    /** L'enregistreur du run, ou null si PRESTAFLOW_RECORD_DIR n'est pas posé. */
    public static function current(): ?self
    {
        $dir = trim((string) \PrestaFlow\Library\Utils\Env::get('PRESTAFLOW_RECORD_DIR', ''));
        if ($dir === '') {
            self::$instance = null;

            return null;
        }
        if (self::$instance === null || self::$instance->dir !== $dir) {
            self::$instance = new self($dir);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance?->stop();
        self::$instance = null;
    }

    public function dir(): string
    {
        return $this->dir;
    }

    /**
     * Début d'un test : le flux est (re)branché sur la session de la page courante.
     *
     * @param  object|null  $session  HeadlessChromium\Communication\Session (on, sendMessage, getSessionId)
     */
    public function beginTest(int $index, string $title, ?object $session): void
    {
        if ($this->broken) {
            return;
        }
        $this->test = $index;
        $this->frames = 0;
        $this->lastFrameAt = 0.0;
        $this->truncated = false;
        $this->follow($session);
        $this->mark('start', ['title' => $title]);
    }

    /** Fin du test : état final (pass, fail, skip…). */
    public function endTest(string $state): void
    {
        if ($this->test === null) {
            return;
        }
        $this->pump();
        $this->mark('end', ['state' => $state]);
        $this->test = null;
    }

    public function fail(string $message): void
    {
        $this->mark('fail', ['message' => mb_substr($message, 0, 500)]);
    }

    /**
     * Branche le flux sur une session (nouvelle page après recreatePage(), changement FO/BO).
     * Sans effet si c'est déjà la session suivie.
     */
    public function follow(?object $session): void
    {
        if ($this->broken || $session === null) {
            return;
        }
        try {
            $id = (string) $session->getSessionId();
            if ($id === $this->sessionId) {
                return;
            }
            $this->stopSession();
            $this->session = $session;
            $this->sessionId = $id;
            $session->on('method:Page.screencastFrame', function (array $params) use ($session, $id): void {
                // Une ancienne page est accusée mais plus écrite.
                $this->onFrame($params, $session, $id === $this->sessionId);
            });
            $session->sendMessage(new Message('Page.startScreencast', self::SCREENCAST));
        } catch (Throwable) {
            $this->broken = true;
        }
    }

    /** Lit les événements en attente sur le socket, pendant au plus $maxMs. */
    public function pump(int $maxMs = 150): void
    {
        if ($this->session === null || $this->broken) {
            return;
        }
        try {
            $connection = $this->session->getConnection();
            $deadline = microtime(true) + $maxMs / 1000;
            while (microtime(true) < $deadline && $connection->waitForData(0.03)) {
                for ($i = 0; $i < 50; $i++) {
                    $connection->readLine();
                }
            }
        } catch (Throwable) {
            // Socket fermé (page détruite) : rien à lire.
        }
    }

    public function stop(): void
    {
        $this->stopSession();
        $this->test = null;
    }

    /** @param array<string, mixed> $params */
    public function onFrame(array $params, object $session, bool $write = true): void
    {
        try {
            // Toujours accuser : sans accusé, Chrome cesse d'envoyer.
            if (isset($params['sessionId'])) {
                $session->sendMessage(new Message('Page.screencastFrameAck', ['sessionId' => $params['sessionId']]));
            }
            if (! $write || $this->test === null || ! isset($params['data'])) {
                return;
            }
            $t = (float) ($params['metadata']['timestamp'] ?? microtime(true));
            if ($this->frames > 0 && $t - $this->lastFrameAt < 1 / self::MAX_FPS) {
                return;
            }
            if ($this->frames >= self::MAX_FRAMES_PER_TEST) {
                if (! $this->truncated) {
                    $this->truncated = true;
                    $this->mark('truncated');
                }

                return;
            }
            $jpeg = base64_decode((string) $params['data'], true);
            if ($jpeg === false) {
                return;
            }
            $this->frames++;
            $this->lastFrameAt = $t;
            $name = $this->frames.'.jpg';
            file_put_contents($this->testDir().'/'.$name, $jpeg);
            $this->append(['f' => $name, 't' => $t]);
        } catch (Throwable) {
            $this->broken = true;
        }
    }

    /** @param array<string, mixed> $data */
    private function mark(string $type, array $data = []): void
    {
        if ($this->test === null) {
            return;
        }
        $this->append(['m' => $type, 't' => microtime(true)] + $data);
    }

    /** @param array<string, mixed> $line */
    private function append(array $line): void
    {
        try {
            file_put_contents($this->testDir().'/frames.jsonl', json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n", FILE_APPEND);
        } catch (Throwable) {
            $this->broken = true;
        }
    }

    private function testDir(): string
    {
        $dir = rtrim($this->dir, '/').'/'.(int) $this->test;
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Dossier vidéo inaccessible : '.$dir);
        }

        return $dir;
    }

    private function stopSession(): void
    {
        if ($this->session !== null) {
            try {
                $this->session->sendMessage(new Message('Page.stopScreencast'));
            } catch (Throwable) {
                // Page déjà fermée.
            }
        }
        $this->session = null;
        $this->sessionId = null;
    }
}
