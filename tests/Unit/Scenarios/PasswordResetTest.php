<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class PasswordResetTest extends TestCase
{
    public function test_defaults_are_discovered(): void
    {
        $params = (new \ReflectionClass(\PrestaFlow\Library\Scenarios\PasswordReset::class))->getDefaultProperties()['params'];

        foreach (['locale', 'email'] as $key) {
            $this->assertArrayHasKey($key, $params);
            $this->assertNull($params[$key], $key);
        }
    }

    public function test_the_password_page_resolves_through_the_shop(): void
    {
        $page = (new \ReflectionClass(\PrestaFlow\Library\Pages\v9\FrontOffice\Password\Page::class))->getDefaultProperties();

        $this->assertSame('password-recovery', $page['url']);
        $this->assertSame('password', $page['shopPage']);
        $this->assertSame(['password', 'password'], \PrestaFlow\Library\Pages\FrontOfficePage::SHOP_PAGES['password']);
    }

    public function test_versions_17_and_8_reuse_the_v9_page(): void
    {
        foreach (['v7', 'v8'] as $version) {
            $class = 'PrestaFlow\\Library\\Pages\\' . $version . '\\FrontOffice\\Password\\Page';
            $this->assertTrue(is_subclass_of($class, \PrestaFlow\Library\Pages\v9\FrontOffice\Password\Page::class), $class);
        }
    }

    public function test_the_skip_reasons_are_exact_sentences(): void
    {
        $page = \PrestaFlow\Library\Pages\v9\FrontOffice\Password\Page::class;

        $this->assertSame('This shop has no forgotten-password form on its password recovery page.', $page::NO_PASSWORD_FORM);
        $this->assertSame(
            'No customer account configured: set PRESTAFLOW_FO_EMAIL (the project\'s customer account), or the email param.',
            $page::NO_ACCOUNT_EMAIL
        );
    }
}
