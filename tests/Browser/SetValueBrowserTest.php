<?php

namespace PrestaFlow\Tests\Browser\SetValueFixtures {

    use PrestaFlow\Library\Pages\CommonPage;

    class Page extends CommonPage
    {
        public static $chromePage = null;

        public function __construct()
        {
        }

        public function getPage()
        {
            return self::$chromePage;
        }
    }
}

namespace PrestaFlow\Tests\Browser {

    use HeadlessChromium\BrowserFactory;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Tests\Browser\SetValueFixtures\Page;

    /** setValue() replaces what a field holds; it used to append (1 + "1" typed = 11). */
    final class SetValueBrowserTest extends TestCase
    {
        public function test_a_prefilled_field_is_replaced_not_appended_to(): void
        {
            try {
                $browser = (new BrowserFactory())->createBrowser(['headless' => true, 'windowSize' => [800, 600], 'noSandbox' => true]);
            } catch (\Throwable $e) {
                if (getenv('CI') === false || getenv('CI') === '') {
                    $this->markTestSkipped('Chrome indisponible : ' . $e->getMessage());
                }
                $this->fail('Chrome indisponible en CI : ' . $e->getMessage());
            }

            try {
                $page = $browser->createPage();
                $page->navigate('data:text/html,' . rawurlencode('<meta charset="utf-8"><body><input id="quantity_wanted" value="1"><input id="empty"></body>'))->waitForNavigation();
                Page::$chromePage = $page;

                (new Page())->setValue('#quantity_wanted', '1');
                (new Page())->setValue('#empty', 'abc');

                $this->assertSame('1', $page->evaluate('document.getElementById("quantity_wanted").value')->getReturnValue());
                $this->assertSame('abc', $page->evaluate('document.getElementById("empty").value')->getReturnValue());
            } finally {
                $browser->close();
            }
        }
    }
}
