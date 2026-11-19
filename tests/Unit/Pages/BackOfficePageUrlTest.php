<?php

namespace PrestaFlow\Tests\Unit\Pages\BackOfficeUrlFixtures {

    use PrestaFlow\Library\Pages\BackOfficePage;

    /**
     * getPageURL() only needs the globals and the locale; the real constructor
     * pulls in selectors, translations and a browser session we do not want here.
     */
    class Page extends BackOfficePage
    {
        public function __construct(array $globals, string $locale)
        {
            $this->globals = $globals;
            $this->customs = ['urls' => []];
            $this->initLocale(locale: $locale);
        }

        public function getGlobals(): array
        {
            return $this->globals;
        }
    }
}

namespace PrestaFlow\Tests\Unit\Pages {

    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;
    use PrestaFlow\Tests\Unit\Pages\BackOfficeUrlFixtures\Page as UrlPage;

    final class BackOfficePageUrlTest extends TestCase
    {
        private function page(string $locale): UrlPage
        {
            return new UrlPage([
                'BO' => ['URL' => 'http://localhost:8092/admin-dev'],
                'LOCALE' => $locale,
                // No project catalog: only the library's own Urls/<locale>.json.
                'TESTS_PATH' => sys_get_temp_dir() . '/prestaflow-no-tests-dir',
            ], $locale);
        }

        public static function locales(): array
        {
            return [['en'], ['fr']];
        }

        /**
         * The back-office login and dashboard live at the admin folder root.
         * The front-office catalog maps "login" to a localized slug ("connexion"
         * in French); the back office must never read it, or the login page
         * becomes /admin-dev/connexion, a 404.
         */
        #[DataProvider('locales')]
        public function testLoginAndIndexAreTheAdminRootInEveryLocale(string $locale): void
        {
            $this->assertSame('http://localhost:8092/admin-dev/', $this->page($locale)->getPageURL('login'));
            $this->assertSame('http://localhost:8092/admin-dev/', $this->page($locale)->getPageURL('index'));
        }

        public function testOtherPagesAreAppendedToTheAdminFolder(): void
        {
            $this->assertSame(
                'http://localhost:8092/admin-dev/index.php?controller=AdminOrders',
                $this->page('fr')->getPageURL('index.php?controller=AdminOrders')
            );
        }
    }
}
