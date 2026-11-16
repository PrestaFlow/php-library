<?php

namespace PrestaFlow\Tests\Unit\Support;

use Evenement\EventEmitter;
use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Support\ConsoleErrors;

/** Erreurs JS d'un test : exceptions et console.error, dédoublonnées, par test. */
final class ConsoleErrorsTest extends TestCase
{
    private function session(string $id = 'S1'): EventEmitter
    {
        return new class($id) extends EventEmitter
        {
            public function __construct(private string $id) {}

            public function getSessionId(): string
            {
                return $this->id;
            }

            public function exception(string $description, string $url = 'https://shop.test/modules/pay/front.js', int $line = 41): void
            {
                $this->emit('method:Runtime.exceptionThrown', [['exceptionDetails' => [
                    'text' => 'Uncaught', 'url' => $url, 'lineNumber' => $line,
                    'exception' => ['type' => 'object', 'description' => $description."\n    at front.js:42:3"],
                ]]]);
            }

            public function console(string $type, array $args): void
            {
                $this->emit('method:Runtime.consoleAPICalled', [['type' => $type, 'args' => $args,
                    'stackTrace' => ['callFrames' => [['url' => 'https://shop.test/checkout.js', 'lineNumber' => 9]]]]]);
            }
        };
    }

    public function test_exceptions_and_console_errors_are_collected_per_test(): void
    {
        $session = $this->session();
        $errors = new ConsoleErrors;

        $errors->beginTest($session);
        $session->exception("TypeError: Cannot read properties of undefined (reading 'mount')");
        $session->exception("TypeError: Cannot read properties of undefined (reading 'mount')");
        $session->console('error', [['type' => 'string', 'value' => 'Stripe:'], ['type' => 'object', 'description' => 'Error: invalid key']]);
        $session->console('log', [['type' => 'string', 'value' => 'rien à voir']]);
        $session->console('warning', [['type' => 'string', 'value' => 'dépréciée']]);

        $this->assertSame([
            ['message' => "TypeError: Cannot read properties of undefined (reading 'mount')", 'url' => 'https://shop.test/modules/pay/front.js', 'line' => 42, 'count' => 2],
            ['message' => 'Stripe: Error: invalid key', 'url' => 'https://shop.test/checkout.js', 'line' => 10, 'count' => 1],
        ], $errors->endTest());

        // Test suivant : liste vide ; entre deux tests, rien n'est gardé.
        $session->exception('ReferenceError: entre deux tests');
        $errors->beginTest($session);
        $this->assertSame([], $errors->endTest());
    }

    public function test_only_the_followed_page_is_listened_to(): void
    {
        $old = $this->session('OLD');
        $new = $this->session('NEW');
        $errors = new ConsoleErrors;

        $errors->beginTest($old);
        $errors->follow($new);
        $old->exception('ReferenceError: ancienne page');
        $new->exception('ReferenceError: nouvelle page');

        $this->assertSame(['ReferenceError: nouvelle page'], array_column($errors->endTest(), 'message'));
    }

    public function test_the_list_is_capped(): void
    {
        $session = $this->session();
        $errors = new ConsoleErrors;
        $errors->beginTest($session);
        for ($i = 0; $i < ConsoleErrors::MAX_PER_TEST + 5; $i++) {
            $session->exception("Error: n°{$i}");
        }

        $this->assertCount(ConsoleErrors::MAX_PER_TEST, $errors->endTest());
    }

    public function test_a_broken_session_never_breaks_the_run(): void
    {
        $errors = new ConsoleErrors;
        $errors->beginTest(new class
        {
            public function getSessionId(): string
            {
                throw new \RuntimeException('socket fermé');
            }
        });

        $this->assertSame([], $errors->endTest());
    }
}
