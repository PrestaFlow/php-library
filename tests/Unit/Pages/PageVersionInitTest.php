<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\InvalidVersionException;
use PrestaFlow\Library\Pages\CommonPage;

/**
 * Une page prend sa version à sa construction : son argument patchVersion,
 * sinon le PS_VERSION des globals. Sans l'un ni l'autre, erreur explicite
 * (plus de repli silencieux sur '8').
 */
final class PageVersionInitTest extends TestCase
{
    public function test_patch_version_argument_sets_the_page_version(): void
    {
        $page = new CommonPage('en', '1.7.8.11', []);

        $this->assertSame('1.7', $page->getMajorVersion());
        $this->assertSame('7', $page->getMajorVersion(namespace: true));
        $this->assertSame('1.7.8.11', $page->getPatchVersion());
    }

    public function test_patch_version_argument_wins_over_the_globals(): void
    {
        $page = new CommonPage('en', '8.1.0', ['PS_VERSION' => '9.2.0']);

        $this->assertSame('8', $page->getMajorVersion());
    }

    public function test_globals_ps_version_is_used_when_patch_version_is_empty(): void
    {
        $page = new CommonPage('en', '', ['PS_VERSION' => '9.2.0']);

        $this->assertSame('9', $page->getMajorVersion());
        $this->assertSame('9.2.0', $page->getPatchVersion());
    }

    public function test_a_page_without_any_version_throws(): void
    {
        $this->expectException(InvalidVersionException::class);
        $this->expectExceptionMessage('version PrestaShop inconnue');

        new CommonPage('en', '', []);
    }

    public function test_an_unsupported_version_format_throws_the_library_exception(): void
    {
        $this->expectException(InvalidVersionException::class);
        $this->expectExceptionMessage('Error with version 8.1.10');

        new CommonPage('en', '8.1.10', []);
    }
}
