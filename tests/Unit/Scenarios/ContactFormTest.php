<?php

namespace PrestaFlow\Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class ContactFormTest extends TestCase
{
    public function test_defaults_are_discovered_or_unique(): void
    {
        $params = (new \ReflectionClass(\PrestaFlow\Library\Scenarios\ContactForm::class))->getDefaultProperties()['params'];

        foreach (['locale', 'email', 'message', 'contactId'] as $key) {
            $this->assertArrayHasKey($key, $params);
            $this->assertNull($params[$key], $key);
        }
    }

    public function test_the_contact_page_resolves_through_the_shop(): void
    {
        $page = (new \ReflectionClass(\PrestaFlow\Library\Pages\v9\FrontOffice\Contact\Page::class))->getDefaultProperties();

        $this->assertSame('contact', $page['url']);
        $this->assertSame('contact', $page['shopPage']);
    }
}
