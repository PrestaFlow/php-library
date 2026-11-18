<?php

namespace PrestaFlow\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Utils\TestsPath;

/** Dossier Tests/ du projet : global, puis environnement, puis le chemin historique relatif à vendor/. */
final class TestsPathTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV[TestsPath::ENV_VAR]);
        putenv(TestsPath::ENV_VAR);
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

    public function testReadJsonToleratesMissingAndInvalidFiles(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pf-json');
        file_put_contents($file, '{oops');

        $this->assertSame([], TestsPath::readJson($file));
        $this->assertSame([], TestsPath::readJson($file.'-missing'));
        unlink($file);
    }
}
