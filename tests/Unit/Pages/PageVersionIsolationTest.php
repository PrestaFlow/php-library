<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Exceptions\InvalidVersionException;
use PrestaFlow\Library\Pages\BackOfficePage;
use PrestaFlow\Library\Pages\FrontOfficePage;
use PrestaFlow\Library\Traits\Version;

/**
 * La version vivait dans un statique de trait, partagé entre toutes les
 * pages (et, avant PHP 8.3, entre back-office et front-office) : la dernière
 * page construite imposait sa version aux autres.
 */
final class PageVersionIsolationTest extends TestCase
{
    public function test_two_back_office_pages_keep_their_own_version(): void
    {
        $first = new class ('en', '8.1.0', []) extends BackOfficePage {};
        $second = new class ('en', '9.0.0', []) extends BackOfficePage {};

        $this->assertSame('8', $first->getMajorVersion());
        $this->assertSame('9', $second->getMajorVersion());
    }

    public function test_a_back_office_page_does_not_leak_into_a_front_office_page(): void
    {
        $bo = new class ('en', '8.1.0', []) extends BackOfficePage {};
        $fo = new class ('en', '9.0.0', []) extends FrontOfficePage {};

        $this->assertSame('8', $bo->getMajorVersion());
        $this->assertSame('9', $fo->getMajorVersion());
    }

    public function test_an_object_without_any_version_throws_instead_of_assuming_8(): void
    {
        $object = new class {
            use Version;
            public array $globals = [];
        };

        $this->expectException(InvalidVersionException::class);
        $this->expectExceptionMessage('version PrestaShop inconnue');

        $object->getMajorVersion();
    }

    public function test_set_versions_keeps_only_the_three_known_keys(): void
    {
        $object = new class {
            use Version;
            public array $globals = [];
        };

        $object->setVersions(['majorVersion' => '9', 'minorVersion' => '9.2', 'patchVersion' => '9.2.0', 'other' => 'x']);

        $this->assertSame(
            ['patchVersion' => '9.2.0', 'minorVersion' => '9.2', 'majorVersion' => '9'],
            $object->getVersions()
        );
    }
}
