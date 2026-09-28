<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\Common\BackOffice\Page as CommonBackOfficePage;
use PrestaFlow\Library\Pages\Common\FrontOffice\Page as CommonFrontOfficePage;

/**
 * Every version namespace keeps its area base pages. importPage('FrontOffice')
 * resolves to Pages\v<major>\FrontOffice\Page, and custom pages extend these
 * classes: the v9 pair went missing in the Common-to-v9 migration, which broke
 * both on PrestaShop 9 (VisualTestsSuite included).
 */
final class VersionBasePagesTest extends TestCase
{
    public static function areaBases(): array
    {
        $cases = [];
        foreach (['v7', 'v8', 'v9'] as $version) {
            $cases["$version FrontOffice"] = ["PrestaFlow\\Library\\Pages\\$version\\FrontOffice\\Page", CommonFrontOfficePage::class];
            $cases["$version BackOffice"] = ["PrestaFlow\\Library\\Pages\\$version\\BackOffice\\Page", CommonBackOfficePage::class];
        }

        return $cases;
    }

    /** @dataProvider areaBases */
    public function test_each_version_has_its_area_base_page(string $fqcn, string $parent): void
    {
        $this->assertTrue(class_exists($fqcn), "$fqcn is missing");
        $this->assertTrue(is_subclass_of($fqcn, $parent), "$fqcn must extend $parent");
    }
}
