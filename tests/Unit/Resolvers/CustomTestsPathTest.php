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
    public function __construct(mixed $globals = [])
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

    private ?string $previousEnv = null;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/pf-tests-'.bin2hex(random_bytes(4));
        // Le dossier par défaut ne doit pas dépendre d'une variable posée sur la machine.
        $previous = getenv(TestsPath::ENV_VAR);
        $this->previousEnv = $previous === false ? null : $previous;
        putenv(TestsPath::ENV_VAR);
        unset($_ENV[TestsPath::ENV_VAR], $_SERVER[TestsPath::ENV_VAR]);
    }

    protected function tearDown(): void
    {
        self::deleteTree($this->root);
        if ($this->previousEnv !== null) {
            putenv(TestsPath::ENV_VAR.'='.$this->previousEnv);
        }
    }

    private static function deleteTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    self::deleteTree($path.'/'.$entry);
                }
            }
            rmdir($path);

            return;
        }
        unlink($path);
    }

    /** `a/b/../c` → `a/c`, sans toucher au disque (le dossier par défaut n'existe pas ici). */
    private static function normalize(string $path): string
    {
        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($out);
            } elseif ($segment !== '.' && $segment !== '') {
                $out[] = $segment;
            }
        }

        return '/'.implode('/', $out);
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

    public function testAPageWithNullGlobalsStillBuildsItsSelectors(): void
    {
        $selectors = (new FakeCustomPage(null))->getSelectors();

        $this->assertSame('.lib-submit', $selectors['submitButton']);
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
        $historical = dirname(__DIR__, 3).'/src/Pages/../../../../../Tests/Themes/';

        $this->assertSame(self::normalize($historical), self::normalize((new FakeCustomPage([]))->themeDirs()[1]));
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
