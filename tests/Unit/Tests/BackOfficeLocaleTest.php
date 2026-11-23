<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * The back office speaks the employee's language, not the shop's: a French shop
 * administered by an English-speaking employee shows "Carriers • PrestaShop".
 * BO_LOCALE lets the back-office pages expect that language while the front
 * office keeps LOCALE.
 */
final class BackOfficeLocaleTest extends TestCase
{
    private function suite(array $globals): TestsSuite
    {
        $suite = new class (loadGlobals: true, getBrowser: false) extends TestsSuite {
            public function init()
            {
                $this->importPage('BackOffice\Carriers');
                $this->importPage('FrontOffice\Home');

                return $this;
            }

            public function page(string $name): object
            {
                return $this->pages[$name];
            }
        };
        $suite->setGlobals(['PS_VERSION' => '9.0.0', ...$globals]);
        $suite->init();

        return $suite;
    }

    public function test_back_office_pages_follow_bo_locale(): void
    {
        $suite = $this->suite(['LOCALE' => 'fr', 'BO_LOCALE' => 'en']);

        $this->assertSame('Carriers', $suite->page('backOfficeCarriersPage')->pageTitle());
        $this->assertSame('fr', $suite->page('frontOfficeHomePage')->getLocale());
    }

    public function test_back_office_pages_follow_locale_without_bo_locale(): void
    {
        $suite = $this->suite(['LOCALE' => 'fr', 'BO_LOCALE' => '']);

        $this->assertSame('Transporteurs', $suite->page('backOfficeCarriersPage')->pageTitle());
    }
}
