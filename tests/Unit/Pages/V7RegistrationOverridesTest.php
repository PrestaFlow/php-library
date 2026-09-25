<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\v7\FrontOffice\Registration\Page as V7Registration;
use PrestaFlow\Library\Pages\v9\FrontOffice\Registration\Page as V9Registration;

/**
 * 1.7 reaches account creation through the login page, and the page it lands on
 * is body#authentication. The override that encodes this re-declares two
 * selectors on top of the parent map, which is the fragile part: written as a
 * plain array instead of a spread it would silently drop every other field the
 * form needs, and the failure would surface as a missing input rather than as a
 * missing override.
 */
final class V7RegistrationOverridesTest extends TestCase
{
    private function page(string $class): object
    {
        return new $class('en', '1.7.8.11', ['FO' => ['URL' => 'http://shop.test/'], 'THEME' => 'classic'], []);
    }

    public function testTheTwoIdAnchoredSelectorsMoveToTheAuthenticationPage(): void
    {
        $selectors = $this->page(V7Registration::class)->selectors;

        $this->assertSame('body#authentication', $selectors['registrationForm']);
        $this->assertSame(
            'body#authentication input[type="checkbox"][required]',
            $selectors['requiredConsentCheckbox']
        );
    }

    /** THE fragile part: everything the parent declares must survive. */
    public function testEveryOtherFieldIsInheritedFromV9(): void
    {
        $v9 = $this->page(V9Registration::class)->selectors;
        $v7 = $this->page(V7Registration::class)->selectors;

        $moved = ['registrationForm', 'requiredConsentCheckbox'];

        foreach ($v9 as $key => $value) {
            if (in_array($key, $moved, true)) {
                continue;
            }

            $this->assertArrayHasKey($key, $v7, sprintf('v7 dropped the "%s" selector', $key));
            $this->assertSame($value, $v7[$key], sprintf('v7 changed "%s" without reason', $key));
        }
    }

    /** The overrides are additions, not a replacement of the parent map. */
    public function testTheOverrideDoesNotShrinkTheSelectorMap(): void
    {
        $this->assertCount(
            count($this->page(V9Registration::class)->selectors),
            $this->page(V7Registration::class)->selectors
        );
    }
}
