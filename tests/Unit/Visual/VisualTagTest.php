<?php

namespace PrestaFlow\Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Visual\VisualTag;

final class VisualTagTest extends TestCase
{
    public function testExplicitTagMatchingSafePatternIsReturnedAsIs(): void
    {
        $this->assertSame(
            'my-custom.tag_1',
            VisualTag::resolve('my-custom.tag_1', 9, 1280, 720, 'fr')
        );
    }

    public function testExplicitTagWithForbiddenCharThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('visual tag must match [a-z0-9._-]+');

        VisualTag::resolve('bad tag', 9, 1280, 720, 'fr');
    }

    public function testExplicitTagWithColonOrSlashThrows(): void
    {
        foreach (['bad:tag', 'bad/tag'] as $tag) {
            try {
                VisualTag::resolve($tag, 9, 1280, 720, 'fr');
                $this->fail("Expected InvalidArgumentException for tag « {$tag} »");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('visual tag must match [a-z0-9._-]+', $e->getMessage());
            }
        }
    }

    public function testAutoTagResolvesFromVersionViewportAndLocale(): void
    {
        $this->assertSame(
            'auto-v9-1280x720-fr',
            VisualTag::resolve('auto', 9, 1280, 720, 'fr')
        );
    }

    public function testAutoTagUsesQuestionMarkPlaceholderForMissingMajorVersion(): void
    {
        $this->assertSame(
            'auto-v?-1280x720-fr',
            VisualTag::resolve('auto', null, 1280, 720, 'fr')
        );
    }

    public function testAutoTagWithAllNullPiecesUsesPlaceholderForEverySegment(): void
    {
        $this->assertSame(
            'auto-v?-?x?-?',
            VisualTag::resolve('auto', null, null, null, null)
        );
    }
}
