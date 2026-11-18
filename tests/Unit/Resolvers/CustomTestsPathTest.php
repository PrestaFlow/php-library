<?php

namespace PrestaFlow\Tests\Unit\Resolvers;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Pages\CommonPage;
use PrestaFlow\Library\Resolvers\Translations;
use PrestaFlow\Library\Resolvers\Urls;
use PrestaFlow\Library\Utils\TestsPath;

/** Double de page sans navigateur : sélecteurs et messages fusionnés comme une page de la librairie. */
final class FakeCustomPage extends CommonPage
{
    public function __construct(array $globals = [])
    {
        $this->globals = $globals;
        $this->customs = ['selectors' => [], 'messages' => [], 'urls' => []];
        $this->exctractVersions('9.0.0');
        $this->initLocale('fr');
    }

    public function defineSelectors()
    {
        return ['submitButton' => '.lib-submit', 'title' => 'h1'];
    }

    public function defineMessages()
    {
        return ['saved' => 'Library saved'];
    }

    public function getPageName(): string
    {
        return 'FrontOffice\\Registration\\Page';
    }

    public function themeDirs(): array
    {
        return $this->themeDirectories();
    }
}

final class CustomTestsPathTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/pf-tests-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
    }

    private function put(string $relative, string $content): void
    {
        @mkdir(dirname($this->root.'/'.$relative), 0777, true);
        file_put_contents($this->root.'/'.$relative, $content);
    }

    private function globals(): array
    {
        return [TestsPath::GLOBAL_KEY => $this->root];
    }

    public function testSelectorsComeFromTheConfiguredFolder(): void
    {
        $this->put('Selectors/fr.json', json_encode(['FrontOffice' => ['Registration' => ['submitButton' => '#mine']]]));

        $selectors = (new FakeCustomPage($this->globals()))->getSelectors();

        $this->assertSame('#mine', $selectors['submitButton']);
        $this->assertSame('h1', $selectors['title']);
    }

    public function testAnInvalidSelectorsFileKeepsTheLibraryValues(): void
    {
        $this->put('Selectors/fr.json', '{oops');

        $this->assertSame('.lib-submit', (new FakeCustomPage($this->globals()))->getSelectors()['submitButton']);
    }

    public function testMessagesComeFromTheConfiguredFolder(): void
    {
        $this->put('Messages/fr.json', json_encode(['FrontOffice' => ['Registration' => ['saved' => 'Enregistré !']]]));

        $this->assertSame('Enregistré !', (new FakeCustomPage($this->globals()))->getMessages()['saved']);
    }

    public function testThemesComeFromTheConfiguredFolder(): void
    {
        $this->put('Themes/panda.json', json_encode(['FrontOffice' => ['Registration' => ['submitButton' => '.panda-submit']]]));
        $page = new FakeCustomPage($this->globals() + ['THEME' => 'panda']);

        $this->assertSame($this->root.'/Themes/', $page->themeDirs()[1]);
        $this->assertSame('.panda-submit', $page->getSelectors()['submitButton']);
    }

    public function testTheDefaultThemeFolderIsUnchanged(): void
    {
        $this->assertSame(TestsPath::dir('Themes', []), (new FakeCustomPage([]))->themeDirs()[1]);
    }

    public function testUrlsComeFromTheConfiguredFolderAndAnInvalidFileIsIgnored(): void
    {
        $page = new class($this->globals())
        {
            use Urls;

            public array $customs = ['urls' => []];

            public function __construct(public array $globals) {}

            public function getGlobal($index)
            {
                return $this->globals[$index] ?? null;
            }
        };
        $page->initLocale('fr');

        $this->put('Urls/fr.json', json_encode(['login' => 'se-connecter']));
        $this->assertSame('se-connecter', $page->getUrls()['login']);

        // Urls.php:55 vérifiait $defaultCatalog : le null d'un fichier invalide cassait la fusion.
        $this->put('Urls/fr.json', '{oops');
        $this->assertSame('connexion', $page->getUrls()['login']);
    }

    public function testTranslationsComeFromTheConfiguredFolder(): void
    {
        $this->put('Translations/fr.json', json_encode(['Sign in' => 'Se connecter (boutique)']));
        $page = new class($this->globals())
        {
            use Translations;

            public array $customs = ['messages' => null];

            public function __construct(public array $globals) {}
        };
        $page->initLocale('fr');
        $page->initTranslations(locale: 'fr', patchVersion: '9.0.0');

        $this->assertSame('Se connecter (boutique)', $page->translate('Sign in'));
    }

    public function testAnUndefinedSelectorNamesThePage(): void
    {
        $this->expectExceptionMessage('Selector "nope" is not defined on page "FrontOffice\\Registration"');

        (new FakeCustomPage([]))->getSelector('nope');
    }
}
