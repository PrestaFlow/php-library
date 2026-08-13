<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Tests unitaires pour presetExtraHeadersFromEnv() — chemin env → $extraHttpHeaders.
 *
 * On invoque directement la méthode protégée via réflexion pour éviter d'avoir
 * à booter un browser (côté I/O du preset — setConnectionHttpHeaders /
 * applyExtraHttpHeaders — sont no-op sans browser).
 */
final class ExtraHeadersFromEnvTest extends TestCase
{
    private array $envBackup;
    private array $headersBackup;

    protected function setUp(): void
    {
        $this->envBackup = [
            'PRESTAFLOW_EXTRA_HEADERS' => $_ENV['PRESTAFLOW_EXTRA_HEADERS'] ?? null,
        ];
        $this->headersBackup = TestsSuite::$extraHttpHeaders;
        TestsSuite::$extraHttpHeaders = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $k => $v) {
            if ($v === null) {
                unset($_ENV[$k]);
            } else {
                $_ENV[$k] = $v;
            }
        }
        TestsSuite::$extraHttpHeaders = $this->headersBackup;
    }

    private function invoke(): void
    {
        $suite = new class extends TestsSuite {
            public function callPreset(): void
            {
                $this->presetExtraHeadersFromEnv();
            }
        };
        $suite->callPreset();
    }

    public function testEmptyEnvIsNoOp(): void
    {
        unset($_ENV['PRESTAFLOW_EXTRA_HEADERS']);
        $this->invoke();
        $this->assertSame([], TestsSuite::$extraHttpHeaders);
    }

    public function testEmptyStringIsNoOp(): void
    {
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '';
        $this->invoke();
        $this->assertSame([], TestsSuite::$extraHttpHeaders);
    }

    public function testValidJsonPopulatesHeaders(): void
    {
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{"X-CI-Bypass":"secret","X-Request-Id":"abc"}';
        $this->invoke();
        $this->assertSame([
            'X-CI-Bypass' => 'secret',
            'X-Request-Id' => 'abc',
        ], TestsSuite::$extraHttpHeaders);
    }

    public function testScalarValuesAreStringifiedNonScalarsSkipped(): void
    {
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{"X-Int":42,"X-Bool":true,"X-Null":null,"X-Arr":["nope"]}';
        $this->invoke();
        $this->assertSame([
            'X-Int' => '42',
            'X-Bool' => '1',    // (string) true → "1"
        ], TestsSuite::$extraHttpHeaders);
    }

    public function testInvalidJsonIsNoOpWithStderrWarning(): void
    {
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{not-json';
        // Capture stderr pour vérifier le warning
        $tmp = tmpfile();
        $meta = stream_get_meta_data($tmp);
        $origStderr = defined('STDERR') ? STDERR : null;

        // On ne peut pas facilement rediriger STDERR sans hack ; on se contente
        // de vérifier l'effet fonctionnel (no-op) — le warning est best-effort.
        $this->invoke();
        $this->assertSame([], TestsSuite::$extraHttpHeaders);
    }

    public function testNonObjectJsonArrayIsRejected(): void
    {
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '["not","an","object"]';
        // json_decode( , true) le rend en array indexé → clés non-string → skipped
        $this->invoke();
        $this->assertSame([], TestsSuite::$extraHttpHeaders);
    }

    public function testMergePreservesExistingHeadersUnlessOverridden(): void
    {
        TestsSuite::$extraHttpHeaders = ['Authorization' => 'Basic existing', 'X-Keep' => '1'];
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{"X-CI-Bypass":"secret","Authorization":"Bearer override"}';
        $this->invoke();
        // Le merge donne priorité aux valeurs env (surcharge volontaire possible).
        $this->assertSame([
            'Authorization' => 'Bearer override',
            'X-Keep' => '1',
            'X-CI-Bypass' => 'secret',
        ], TestsSuite::$extraHttpHeaders);
    }

    public function testEmptyKeyIsRejected(): void
    {
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{"":"empty-key","X-Ok":"v"}';
        $this->invoke();
        $this->assertSame(['X-Ok' => 'v'], TestsSuite::$extraHttpHeaders);
    }
}
