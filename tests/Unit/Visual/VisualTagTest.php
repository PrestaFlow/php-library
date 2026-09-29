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

    public function testAutoTagUsesFilenameSafeTokenForMissingMajorVersion(): void
    {
        $this->assertSame(
            'auto-vx-1280x720-fr',
            VisualTag::resolve('auto', null, 1280, 720, 'fr')
        );
    }

    public function testAutoTagWithAllNullPiecesIsFilenameSafe(): void
    {
        $tag = VisualTag::resolve('auto', null, null, null, null);

        $this->assertSame('auto-vx-0x0-xx', $tag);
        $this->assertStringNotContainsString('?', $tag);
    }

    public function testAutoTagAcceptsDottedMajorVersion(): void
    {
        $this->assertSame('auto-v1.7-390x844-fr', VisualTag::resolve('auto', '1.7', 390, 844, 'fr'));
        $this->assertSame('auto-v8-390x844-fr', VisualTag::resolve('auto', '8', 390, 844, 'fr'));
    }

    public function testAutoTagFallsBackWhenMajorVersionIsUnsafe(): void
    {
        $this->assertSame('auto-vx-390x844-fr', VisualTag::resolve('auto', '?', 390, 844, 'fr'));
    }

    public function testExplicitTagWithQuestionMarkThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VisualTag::resolve('auto-v?-1280x720-fr', 9, 1280, 720, 'fr');
    }

    public function testMajorFromPsVersion(): void
    {
        $this->assertSame('8', VisualTag::majorFromVersion('8.1.0'));
        $this->assertSame('9', VisualTag::majorFromVersion('9.0.1'));
        $this->assertSame('1.7', VisualTag::majorFromVersion('1.7.8.11'));
        $this->assertSame('1.6', VisualTag::majorFromVersion('1.6.1.24'));
        $this->assertSame('1.7', VisualTag::majorFromVersion('1.7'));
        $this->assertSame('8', VisualTag::majorFromVersion('8'));
        $this->assertNull(VisualTag::majorFromVersion(null));
        $this->assertNull(VisualTag::majorFromVersion(''));
        $this->assertNull(VisualTag::majorFromVersion('latest'));
    }
}
