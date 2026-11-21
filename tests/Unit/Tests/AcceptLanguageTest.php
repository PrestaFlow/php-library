<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * presetAcceptLanguage(): the browser sends the run's language without a
 * region, so PrestaShop's country detection (PS_DETECT_COUNTRY) keeps the
 * shop's default country whatever machine runs the suite.
 */
final class AcceptLanguageTest extends TestCase
{
    private array $envBackup;
    private array $headersBackup;

    protected function setUp(): void
    {
        $this->envBackup = [
            'PRESTAFLOW_EXTRA_HEADERS' => $_ENV['PRESTAFLOW_EXTRA_HEADERS'] ?? null,
            'PRESTAFLOW_LOCALE' => $_ENV['PRESTAFLOW_LOCALE'] ?? null,
        ];
        $this->headersBackup = TestsSuite::$extraHttpHeaders;
        TestsSuite::$extraHttpHeaders = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $k => $v) {
            if ($v === null) {
                unset($_ENV[$k]);
            } else {
                $_ENV[$k] = $v;
            }
        }
        TestsSuite::$extraHttpHeaders = $this->headersBackup;
    }

    private function invoke(): void
    {
        $suite = new class(loadGlobals: false, getBrowser: false) extends TestsSuite {
            public function callPreset(): void
            {
                $this->presetAcceptLanguage();
            }
        };
        $suite->callPreset();
    }

    public function testTheLanguageLosesItsRegion(): void
    {
        $this->assertSame('en', TestsSuite::acceptLanguageFor('en'));
        $this->assertSame('fr', TestsSuite::acceptLanguageFor('fr-FR'));
        $this->assertSame('pt', TestsSuite::acceptLanguageFor('pt_BR'));
        $this->assertSame('en', TestsSuite::acceptLanguageFor(''));
        $this->assertSame('en', TestsSuite::acceptLanguageFor('not a locale'));
    }

    public function testTheRunLocaleIsSent(): void
    {
        unset($_ENV['PRESTAFLOW_EXTRA_HEADERS']);
        $_ENV['PRESTAFLOW_LOCALE'] = 'fr-FR';
        $this->invoke();

        $this->assertSame(['Accept-Language' => 'fr'], TestsSuite::$extraHttpHeaders);
    }

    public function testAnEarlierSuitesValueIsReplaced(): void
    {
        unset($_ENV['PRESTAFLOW_EXTRA_HEADERS']);
        $_ENV['PRESTAFLOW_LOCALE'] = 'en';
        TestsSuite::$extraHttpHeaders = ['Accept-Language' => 'de', 'Authorization' => 'Basic x'];
        $this->invoke();

        $this->assertSame(['Accept-Language' => 'en', 'Authorization' => 'Basic x'], TestsSuite::$extraHttpHeaders);
    }

    public function testExtraHeadersFromTheEnvironmentWin(): void
    {
        $_ENV['PRESTAFLOW_EXTRA_HEADERS'] = '{"accept-language":"en-US"}';
        $_ENV['PRESTAFLOW_LOCALE'] = 'fr';
        TestsSuite::$extraHttpHeaders = ['accept-language' => 'en-US'];
        $this->invoke();

        $this->assertSame(['accept-language' => 'en-US'], TestsSuite::$extraHttpHeaders);
    }
}
