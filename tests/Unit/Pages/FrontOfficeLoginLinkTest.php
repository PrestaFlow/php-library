<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v9\FrontOffice\Login\Page as LoginPage;
use PrestaFlow\Library\Utils\CustomerLoginLink;
use RuntimeException;

final class FakeFrontOfficeLoginLinkPage extends LoginPage
{
    public array $calls = [];

    public function goToUrl(string $url)
    {
        $this->calls[] = ['goToUrl', $url];
    }

    public function setValue($selector, $value)
    {
        $this->calls[] = ['setValue', $value];
    }

    public function click($selector, $nth = 1)
    {
        $this->calls[] = ['click'];
    }

    public function waitForPageReload()
    {
        $this->calls[] = ['reload'];
    }
}

/**
 * Cloud run on a shop linked by the module: the customer logs in through the
 * module's single-use link (PRESTAFLOW_FO_LOGIN_URL), never with a password.
 */
final class FrontOfficeLoginLinkTest extends TestCase
{
    private const LINK = 'https://shop.example/module/prestaflow/login?t=tok';

    protected function setUp(): void
    {
        CustomerLoginLink::reset();
        unset($_ENV['PRESTAFLOW_FO_LOGIN_URL']);
    }

    protected function tearDown(): void
    {
        CustomerLoginLink::reset();
        unset($_ENV['PRESTAFLOW_FO_LOGIN_URL']);
    }

    private function page(): FakeFrontOfficeLoginLinkPage
    {
        return new FakeFrontOfficeLoginLinkPage(
            locale: 'en',
            patchVersion: '9.2.0',
            globals: [
                'PS_VERSION' => '9.2.0',
                'LOCALE' => 'en',
                'PREFIX_LOCALE' => false,
                'BO' => ['URL' => 'http://localhost/admin-dev/', 'EMAIL' => 'a@b.c', 'PASSWD' => 'x'],
                'FO' => ['URL' => 'http://localhost/', 'EMAIL' => 'fo@b.c', 'PASSWD' => 'fo-pass'],
                'DEBUG' => false,
                'VERBOSE' => false,
            ],
        );
    }

    public function test_without_a_link_the_form_is_filled(): void
    {
        $page = $this->page();
        $page->login();

        $this->assertSame([['setValue', 'fo@b.c'], ['setValue', 'fo-pass'], ['click'], ['reload']], $page->calls);
    }

    public function test_with_a_link_the_first_login_opens_it(): void
    {
        $_ENV['PRESTAFLOW_FO_LOGIN_URL'] = self::LINK;
        $page = $this->page();
        $page->login();

        $this->assertSame([['goToUrl', self::LINK]], $page->calls);
    }

    public function test_explicit_credentials_still_use_the_form(): void
    {
        $_ENV['PRESTAFLOW_FO_LOGIN_URL'] = self::LINK;
        $page = $this->page();
        $page->login('other@b.c', 'secret');

        $this->assertSame([['setValue', 'other@b.c'], ['setValue', 'secret'], ['click'], ['reload']], $page->calls);
        // The link is left for the run's customer login.
        $this->assertSame(self::LINK, CustomerLoginLink::take());
    }

    public function test_a_second_login_says_the_link_only_works_once(): void
    {
        $_ENV['PRESTAFLOW_FO_LOGIN_URL'] = self::LINK;
        $page = $this->page();
        $page->login();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('only works once per run');
        $page->login();
    }
}
