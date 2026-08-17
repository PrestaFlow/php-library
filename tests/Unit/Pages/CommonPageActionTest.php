<?php

namespace PrestaFlow\Tests\Unit\Pages;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;

final class CommonPageActionTest extends TestCase
{
    public function testClickAndWaitReloadRunsBothActionsInOrder(): void
    {
        $page = new class ('en', '8.1.0', []) extends CommonPage {
            public array $calls = [];

            public function click($selector, $nth = 1)
            {
                $this->calls[] = ['click', $selector, $nth];
            }

            public function waitForPageReload()
            {
                $this->calls[] = ['reload'];
            }
        };

        $page->clickAndWaitReload('#save', 2);

        $this->assertSame([['click', '#save', 2], ['reload']], $page->calls);
    }
}
