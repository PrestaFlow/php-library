<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Scenarios\Registration;
use PrestaFlow\Library\Tests\TestsSuite;

/**
 * Sans `locale` explicite, un scénario garde la langue de la suite (celle du projet dans l'appli).
 * Avant, Registration & co imposaient `en` (ou `fr`) : une surcharge d'URL dans la langue du
 * projet (Tests/Urls/fr.json, « inscription ») n'était jamais lue.
 */
final class ScenarioLocaleTest extends TestCase
{
    private const SCENARIOS = [
        'Registration', 'EnsureTestAccount', 'OnePageCheckoutGuest', 'OnePageCheckoutOrder',
        'SwitchTheme', 'CheckoutOrder', 'GuestCheckout', 'ManageOrder',
    ];

    private string $testsPath;

    protected function setUp(): void
    {
        $this->testsPath = sys_get_temp_dir() . '/pf-scenario-locale-' . uniqid();
        mkdir($this->testsPath . '/Urls', 0777, true);
        file_put_contents($this->testsPath . '/Urls/fr.json', json_encode(['registration' => 'inscription']));
    }

    protected function tearDown(): void
    {
        @unlink($this->testsPath . '/Urls/fr.json');
        @rmdir($this->testsPath . '/Urls');
        @rmdir($this->testsPath);
    }

    private function suite(string $locale): TestsSuite
    {
        $suite = new class (loadGlobals: false, getBrowser: false) extends TestsSuite {};
        $suite->onVersion('8.1.7');
        $suite->setGlobals([
            'LOCALE' => $locale,
            'PATCH_VERSION' => '8.1.7',
            'PREFIX_LOCALE' => false,
            'FO' => ['URL' => 'http://shop.test', 'EMAIL' => '', 'PASSWD' => ''],
            'BO' => ['URL' => 'http://shop.test/admin', 'EMAIL' => '', 'PASSWD' => ''],
            'TESTS_PATH' => $this->testsPath,
        ]);

        return $suite;
    }

    private function registrationUrl(TestsSuite $suite): string
    {
        $page = $suite->pages['frontOfficeRegistrationPage'];
        $page->urls = null;

        return $page->getPageURL($page);
    }

    public function test_scenarios_default_to_the_suite_locale(): void
    {
        foreach (self::SCENARIOS as $name) {
            $ref = new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\' . $name);
            $params = $ref->getDefaultProperties()['params'];
            $this->assertArrayHasKey('locale', $params, $name);
            $this->assertNull($params['locale'], $name);
        }
    }

    /** Sans identifiants passés au scénario, Login::login() prend le compte client du projet (FO_EMAIL / FO_PASSWD). */
    public function test_checkout_scenarios_default_to_the_project_customer_account(): void
    {
        foreach (['CheckoutOrder', 'OnePageCheckoutOrder'] as $name) {
            $params = (new \ReflectionClass('PrestaFlow\\Library\\Scenarios\\' . $name))->getDefaultProperties()['params'];
            $this->assertArrayHasKey('customerEmail', $params, $name);
            $this->assertNull($params['customerEmail'], $name);
            $this->assertNull($params['customerPassword'], $name);
        }
    }

    public function test_registration_reads_the_url_override_in_the_suite_locale(): void
    {
        $suite = $this->suite('fr');
        new Registration($suite);

        $this->assertSame('http://shop.test/inscription', $this->registrationUrl($suite));
    }

    public function test_an_explicit_locale_still_wins(): void
    {
        $suite = $this->suite('fr');
        new Registration($suite, ['locale' => 'en']);

        $this->assertSame('http://shop.test/registration', $this->registrationUrl($suite));
    }
}
