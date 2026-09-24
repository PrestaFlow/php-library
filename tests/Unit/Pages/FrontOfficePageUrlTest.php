<?php

namespace PrestaFlow\Tests\Unit\Pages\UrlFixtures {

    use PrestaFlow\Library\Pages\FrontOfficePage;

    /**
     * getPageURL() only needs the globals and the locale; the real constructor
     * pulls in selectors, translations and a browser session we do not want here.
     */
    class Page extends FrontOfficePage
    {
        public function __construct(array $globals)
        {
            $this->globals = $globals;
            $this->initLocale(locale: 'en');
        }

        public function getGlobals(): array
        {
            return $this->globals;
        }
    }
}

namespace PrestaFlow\Tests\Unit\Pages {

    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Tests\Unit\Pages\UrlFixtures\Page as UrlPage;

    final class FrontOfficePageUrlTest extends TestCase
    {
        private function page(): UrlPage
        {
            return new UrlPage([
                'FO' => ['URL' => 'http://localhost:8092/'],
                'LOCALE' => 'en',
                'PREFIX_LOCALE' => false,
            ]);
        }

        public function testScalarParamSubstitutesTheIndexPlaceholder(): void
        {
            $this->assertSame(
                'http://localhost:8092/3-category',
                $this->page()->getPageURL('category', 3)
            );

            $this->assertSame(
                'http://localhost:8092/6-product.html',
                $this->page()->getPageURL('product', '6')
            );
        }

        public function testArrayParamStillSubstitutes(): void
        {
            $this->assertSame(
                'http://localhost:8092/3-category',
                $this->page()->getPageURL('category', ['index' => 3])
            );
        }

        public function testNoParamLeavesTheTemplateUntouched(): void
        {
            $this->assertSame(
                'http://localhost:8092/{index}-category',
                $this->page()->getPageURL('category')
            );

            $this->assertSame(
                'http://localhost:8092/{index}-category',
                $this->page()->getPageURL('category', [])
            );
        }
    }
}
