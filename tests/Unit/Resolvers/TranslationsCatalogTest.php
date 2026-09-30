<?php

namespace PrestaFlow\Tests\Unit\Resolvers;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Resolvers\Translations;

/**
 * getCatalog() fusionne Translations/{major}/{minor}/{patch}/ — ces tests
 * vérifient que chaque dossier de catalogue est bien atteint par une version.
 */
final class TranslationsCatalogTest extends TestCase
{
    private function makePage(string $patchVersion): object
    {
        $page = new class {
            use Translations;
            public array $customs = ['messages' => null];
        };
        $page::$versions = [
            'patchVersion' => null,
            'minorVersion' => null,
            'majorVersion' => null,
        ];
        $page->initLocale('fr');
        $page->initTranslations(locale: 'fr', patchVersion: $patchVersion);

        return $page;
    }

    public function testPatchCatalog1744IsLoaded(): void
    {
        $this->assertSame('Gérer les modules installés', $this->makePage('1.7.4.4')->translate('Manage'));
    }

    public function testOther17PatchFallsBackToMajorCatalog(): void
    {
        $this->assertSame('Gestionnaire de modules', $this->makePage('1.7.4.3')->translate('Manage'));
    }

    public function testMinorCatalog178IsLoaded(): void
    {
        $this->assertSame('Catalogue de modules', $this->makePage('1.7.8.11')->translate('Module selection'));
    }

    /**
     * Garde-fou : tout dossier de catalogue doit suivre le chemin
     * {major}/{minor}/{patch} que getCatalog() construit.
     */
    public function testEveryCatalogDirectoryIsReachable(): void
    {
        $root = realpath(__DIR__ . '/../../../src/Translations');
        $unreachable = [];
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        ) as $dir) {
            if (! $dir->isDir()) {
                continue;
            }
            $parts = explode('/', substr($dir->getPathname(), strlen($root) + 1));
            $major = $parts[0];
            // exctractVersions : minor = 1.7.x / 8.x, patch = 1.7.x.y / 8.x.y.
            $minorSegments = str_starts_with($major, '1.') ? 3 : 2;
            $isVersion = fn (string $v, string $parent, int $segments) => str_starts_with($v, $parent . '.')
                && count(explode('.', $v)) === $segments;
            $ok = match (count($parts)) {
                1 => in_array($major, ['1.6', '1.7', '8', '9'], true),
                2 => $isVersion($parts[1], $major, $minorSegments),
                3 => $isVersion($parts[1], $major, $minorSegments) && $isVersion($parts[2], $parts[1], $minorSegments + 1),
                default => false,
            };
            if (! $ok) {
                $unreachable[] = implode('/', $parts);
            }
        }

        $this->assertSame([], $unreachable);
    }
}
