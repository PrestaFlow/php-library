<?php

namespace PrestaFlow\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\VisualTestsSuite;
use PrestaFlow\Library\Utils\BackOfficeUrl;

final class BackOfficeUrlTest extends TestCase
{
    /**
     * Mêmes cas que App\Support\BackOfficeUrl::resolve() (tests BackOfficeUrlTest de l'app).
     *
     * @return array<string, array{string, string, string}>
     */
    public static function resolveCases(): array
    {
        return [
            'absolue gardée' => ['http://shop.test/admin-dev/', 'http://other.test/', 'http://shop.test/admin-dev/'],
            'absolue rognée' => [' https://shop.test/admin123 ', '', 'https://shop.test/admin123/'],
            'absolue majuscules' => ['HTTP://shop.test/a', '', 'HTTP://shop.test/a/'],
            'relative sans slash' => ['admin-dev', 'http://shop.test', 'http://shop.test/admin-dev/'],
            'relative' => ['admin-dev/', 'http://shop.test/', 'http://shop.test/admin-dev/'],
            'relative à slash initial' => ['/admin-dev/', 'http://shop.test/', 'http://shop.test/admin-dev/'],
            'relative sous chemin' => ['admin/sub', ' http://shop.test/fr/ ', 'http://shop.test/fr/admin/sub/'],
            'protocole https' => ['//h.test/admin', 'https://fo.test', 'https://h.test/admin/'],
            'protocole majuscules' => ['//h.test/admin', 'HTTP://fo.test/fr', 'http://h.test/admin/'],
            'requête et fragment FO ignorés' => ['admin', 'http://fo.test/fr?x=1#a', 'http://fo.test/fr/admin/'],
            'fragment FO seul' => ['/admin', 'http://fo.test/#frag', 'http://fo.test/admin/'],
            'point dans un segment non initial' => ['/admin.v2/', 'http://fo.test', 'http://fo.test/admin.v2/'],
            'segment à point' => ['admin/v2.1/', 'http://fo.test', 'http://fo.test/admin/v2.1/'],
        ];
    }

    /** @dataProvider resolveCases */
    public function test_resolve(string $backOffice, string $frontOffice, string $expected): void
    {
        $this->assertSame($expected, BackOfficeUrl::resolve($backOffice, $frontOffice));
    }

    /** @return array<string, array{string, bool}> */
    public static function relativeCases(): array
    {
        return [
            'chemin' => ['admin/', true],
            'slash initial' => ['/admin', true],
            'défaut' => ['admin-dev/', true],
            'chemin rogné' => ['  admin  ', true],
            'absolue' => ['http://x/a', false],
            'absolue majuscules' => ['HTTPS://x/a', false],
            'protocole' => ['//x/a', false],
            'vide' => ['', false],
            'blanc' => ['   ', false],
        ];
    }

    /** @dataProvider relativeCases */
    public function test_is_relative(string $backOffice, bool $expected): void
    {
        $this->assertSame($expected, BackOfficeUrl::isRelative($backOffice));
    }

    public function test_visual_suite_keeps_delegating(): void
    {
        $this->assertSame('http://fo.test/fr/admin/', VisualTestsSuite::resolveBackOfficeUrl('admin', 'http://fo.test/fr?x=1#a'));
    }
}
