<?php

namespace PrestaFlow\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Utils\TestsPath;

/** Dossier Tests/ du projet : global, puis environnement, puis le chemin historique relatif à vendor/. */
final class TestsPathTest extends TestCase
{
    /** Valeurs d'avant le test : la variable peut être posée sur la machine ou en CI. */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->saved = [
            'env' => $_ENV[TestsPath::ENV_VAR] ?? null,
            'server' => $_SERVER[TestsPath::ENV_VAR] ?? null,
            'process' => getenv(TestsPath::ENV_VAR),
        ];
        putenv(TestsPath::ENV_VAR);
        unset($_ENV[TestsPath::ENV_VAR], $_SERVER[TestsPath::ENV_VAR]);
    }

    protected function tearDown(): void
    {
        putenv(TestsPath::ENV_VAR);
        unset($_ENV[TestsPath::ENV_VAR], $_SERVER[TestsPath::ENV_VAR]);
        if ($this->saved['process'] !== false) {
            putenv(TestsPath::ENV_VAR.'='.$this->saved['process']);
        }
        if ($this->saved['env'] !== null) {
            $_ENV[TestsPath::ENV_VAR] = $this->saved['env'];
        }
        if ($this->saved['server'] !== null) {
            $_SERVER[TestsPath::ENV_VAR] = $this->saved['server'];
        }
    }

    /** `a/b/../c` → `a/c`, sans toucher au disque (le dossier par défaut n'existe pas ici). */
    private static function normalize(string $path): string
    {
        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($out);
            } elseif ($segment !== '.' && $segment !== '') {
                $out[] = $segment;
            }
        }

        return '/'.implode('/', $out);
    }

    public function testTheGlobalWins(): void
    {
        $_ENV[TestsPath::ENV_VAR] = '/from/env';

        $this->assertSame('/from/global', TestsPath::root([TestsPath::GLOBAL_KEY => '/from/global/']));
        $this->assertSame('/from/global/Selectors/', TestsPath::dir('Selectors', [TestsPath::GLOBAL_KEY => '/from/global']));
    }

    public function testTheEnvironmentComesNext(): void
    {
        putenv(TestsPath::ENV_VAR.'=/from/process');

        $this->assertSame('/from/process', TestsPath::root([TestsPath::GLOBAL_KEY => '']));
    }

    public function testTheDefaultIsTheHistoricalVendorRelativeFolder(): void
    {
        $historical = dirname(__DIR__, 3).'/src/Pages/../../../../../Tests';

        $this->assertSame(self::normalize($historical), self::normalize(TestsPath::root([])));
        foreach (['Selectors', 'Messages', 'Translations', 'Urls', 'Themes'] as $kind) {
            $this->assertSame(self::normalize($historical.'/'.$kind), self::normalize(TestsPath::dir($kind, [])));
        }
    }

    public function testNonArrayGlobalsFallBackToTheDefault(): void
    {
        $this->assertSame(TestsPath::defaultRoot(), TestsPath::root(null));
        $this->assertSame(TestsPath::defaultRoot().'/Urls/', TestsPath::dir('Urls', 'oops'));
    }

    public function testReadJsonToleratesMissingAndInvalidFiles(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pf-json');
        file_put_contents($file, '{oops');

        $this->assertSame([], TestsPath::readJson($file));
        $this->assertSame([], TestsPath::readJson($file.'-missing'));
        unlink($file);
    }
}
