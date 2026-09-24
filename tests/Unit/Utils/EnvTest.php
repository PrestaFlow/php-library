<?php

namespace PrestaFlow\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Utils\Env;

final class EnvTest extends TestCase
{
    private array $envBackup;

    protected function setUp(): void
    {
        // Snapshot only the keys we touch, plus track anything we set.
        $this->envBackup = [];
        foreach (['PF_TEST_A', 'PF_TEST_B', 'PF_TEST_EMPTY', 'PF_TEST_MISSING'] as $k) {
            $this->envBackup[$k] = [
                'env' => array_key_exists($k, $_ENV) ? $_ENV[$k] : null,
                'envHas' => array_key_exists($k, $_ENV),
                'getenv' => getenv($k),
            ];
            unset($_ENV[$k]);
            putenv($k);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $k => $snap) {
            unset($_ENV[$k]);
            putenv($k);
            if ($snap['envHas']) {
                $_ENV[$k] = $snap['env'];
            }
            if ($snap['getenv'] !== false) {
                putenv("$k={$snap['getenv']}");
            }
        }
    }

    public function testGetReturnsDefaultWhenAbsentEverywhere(): void
    {
        $this->assertNull(Env::get('PF_TEST_MISSING'));
        $this->assertSame('fallback', Env::get('PF_TEST_MISSING', 'fallback'));
    }

    public function testGetReadsFromEnvSuperglobalFirst(): void
    {
        $_ENV['PF_TEST_A'] = 'from-env-superglobal';
        putenv('PF_TEST_A=from-process');
        $this->assertSame('from-env-superglobal', Env::get('PF_TEST_A'));
    }

    public function testGetFallsBackToGetenvWhenSuperglobalAbsent(): void
    {
        putenv('PF_TEST_B=from-process-only');
        $this->assertSame('from-process-only', Env::get('PF_TEST_B'));
    }

    public function testGetReturnsEmptyStringFromSuperglobalVerbatim(): void
    {
        // Preserves `?? $default` semantics: a key set to '' still wins over the default.
        $_ENV['PF_TEST_EMPTY'] = '';
        $this->assertSame('', Env::get('PF_TEST_EMPTY', 'default'));
    }

    public function testHasTrueWhenInSuperglobal(): void
    {
        $_ENV['PF_TEST_A'] = 'x';
        $this->assertTrue(Env::has('PF_TEST_A'));
    }

    public function testHasTrueWhenInProcessOnly(): void
    {
        putenv('PF_TEST_B=x');
        $this->assertTrue(Env::has('PF_TEST_B'));
    }

    public function testHasFalseWhenAbsentEverywhere(): void
    {
        $this->assertFalse(Env::has('PF_TEST_MISSING'));
    }
}
