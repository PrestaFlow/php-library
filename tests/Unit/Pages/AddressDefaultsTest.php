<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\FrontOfficePage;

/**
 * Addresses typed by the scenarios must pass the shop's validation whatever
 * country it preselects: the postcode follows that country's format.
 */
final class AddressDefaultsTest extends TestCase
{
    /** Each postcode matches the zip_code_format PrestaShop installs for its country (N digit, L letter, C iso code). */
    public function test_postcodes_match_the_install_formats(): void
    {
        $formats = [
            'FR' => 'NNNNN', 'BE' => 'NNNN', 'CH' => 'NNNN', 'DE' => 'NNNNN', 'AT' => 'NNNN',
            'ES' => 'NNNNN', 'IT' => 'NNNNN', 'US' => 'NNNNN', 'CA' => 'LNL NLN',
            'NL' => 'NNNN LL', 'PT' => 'NNNN-NNN', 'LU' => 'NNNN', 'PL' => 'NN-NNN',
        ];

        foreach ($formats as $iso => $format) {
            $regex = '/^' . strtr(preg_quote($format, '/'), ['N' => '[0-9]', 'L' => '[A-Z]']) . '$/';
            $this->assertMatchesRegularExpression($regex, FrontOfficePage::postcodeFor($iso), $iso);
        }

        // GB has several formats; a real central London postcode.
        $this->assertSame('SW1A 1AA', FrontOfficePage::postcodeFor('GB'));
    }

    public function test_unknown_country_has_no_postcode(): void
    {
        // The form then skips with a reason when the postcode is required.
        $this->assertNull(FrontOfficePage::postcodeFor(null));
        $this->assertNull(FrontOfficePage::postcodeFor('ZZ'));
        $this->assertSame('1000', FrontOfficePage::postcodeFor('be'));
    }

    public function test_install_country_ids(): void
    {
        $this->assertSame('FR', FrontOfficePage::countryIsoFromId(8));
        $this->assertSame('FR', FrontOfficePage::countryIsoFromId('8'));
        $this->assertSame('US', FrontOfficePage::countryIsoFromId(21));
        $this->assertSame('GB', FrontOfficePage::countryIsoFromId(17));
        $this->assertNull(FrontOfficePage::countryIsoFromId(999));
        $this->assertNull(FrontOfficePage::countryIsoFromId(null));
        $this->assertNull(FrontOfficePage::countryIsoFromId(''));
    }

    public function test_every_install_country_has_a_postcode(): void
    {
        foreach (FrontOfficePage::INSTALL_COUNTRY_IDS as $iso) {
            $this->assertArrayHasKey($iso, FrontOfficePage::POSTCODES, $iso);
        }
    }
}
