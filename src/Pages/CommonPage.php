<?php

namespace PrestaFlow\Library\Pages;

use Exception;
use HeadlessChromium\Exception\ElementNotFoundException;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Exception\TargetDestroyed;
use HeadlessChromium\Page as DomPage;
use PrestaFlow\Library\Exceptions\TimeoutException;
use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Resolvers\Translations;
use PrestaFlow\Library\Tests\TestsSuite;
use PrestaFlow\Library\Traits\Locale;


class CommonPage
{
    use Translations;
    use Locale;

    protected $customs = [
        'selectors' => [],
        'messages' => [],
        'urls' => [],
    ];

    protected $globals = [];
    public $selectors = [];
    public $messages = [];

    public string $url = '';
    public string $pageTitle = '';

    protected $patchVersion = null;

    public function __construct(string $locale, string $patchVersion, array $globals, array $customs = [])
    {
        $this->globals = $globals;
        $this->customs = array_merge($this->customs, $customs);
        $this->patchVersion = $patchVersion;
        $this->initLocale(locale: $locale);

        /*
        $this->init(
            $locale,
            $patchVersion
        );
        */

        return $this;
    }

    public function setCustoms(string $type, array $data = [])
    {
        if (isset($this->customs[$type]) && is_array($this->customs[$type])) {
            $this->customs[$type] = array_merge($this->customs[$type], $data);
        } else {
            $this->customs[$type] = $data;
        }

        return $this;
    }

    public function init(string $locale, string $patchVersion): CommonPage
    {
        /*
        $this->initTranslations(
            $locale,
            $patchVersion
        );
        */

        /*
        $this->initUrls(
            $locale
        );
        */

        return $this;
    }

    public function message($message)
    {
        return $this->getMessage($message);
    }

    public function getMessage($message)
    {
        if (isset($this->messages[$message])) {
            return $this->messages[$message];
        }

        return null;
    }

    public function defineSelectors()
    {
        return [];
    }

    public function selector($selector, $replacements = [])
    {
        return $this->getSelector($selector, $replacements);
    }

    public function getSelector($selector, $replacements = [])
    {
        if (isset($this->selectors[$selector])) {
            $selector = $this->selectors[$selector];
            if (is_array($replacements)) {
                foreach ($replacements as $key => $value) {
                    $selector = str_replace('${' . $key . '}', $value, $selector);
                }
            }
            return $selector;
        }

        throw new Exception('Selector "' . $selector . '" is not defined');
    }

    public function __call($name, $arguments)
    {
        // getPage() rouvre une connexion CDP : les trois évaluations d'origine
        // (condition, method_exists, invocation) triplaient chaque appel proxifié.
        $page = $this->getPage();

        if (!is_null($page) && method_exists($page, $name)) {
            call_user_func_array([$page, $name], $arguments);
        }
    }

    public function setGlobals($globals)
    {
        $this->globals = $globals;

        $locale = $this->getGlobal('LOCALE');

        /*
        $this->init(
            $locale,
            $this->patchVersion
        );
        */
    }

    public function getGlobals(): array
    {
        return $this->globals;
    }

    public function setGlobal(string $global, $value)
    {
        $this->globals[$global] = $value;

        if ($global === 'LOCALE') {
            $this->setLocale($value);

            /*
            $this->init(
                $value,
                $this->patchVersion
            );
            */
        }
    }

    /**
     * The theme whose selector variants apply to this page.
     *
     * Read from the globals rather than from a static trait like Locale: page
     * constructors call getSelectors() before importPage() could invoke a
     * setter, so trait-held state would always arrive too late to affect the
     * merge. $this->globals is assigned on the constructor's first line.
     */
    public function getTheme(): string
    {
        $theme = $this->globals['THEME'] ?? '';

        return is_string($theme) && $theme !== '' ? $theme : 'classic';
    }

    public function setTheme(string $theme): void
    {
        $this->globals['THEME'] = $theme;
    }

    /** Set by tests to point the theme loader at fixtures. */
    public array $themeDirs = [];

    /** The last missing-theme warning, kept so callers can surface it. */
    public string $lastThemeWarning = '';

    /**
     * Directories searched for <theme>.json, least specific first.
     *
     * The library's own themes, then the consuming project's — so a project can
     * correct or extend what the library ships without forking it. Mirrors the
     * root that Tests/Selectors/<locale>.json is already read from.
     */
    protected function themeDirectories(): array
    {
        if ($this->themeDirs !== []) {
            return $this->themeDirs;
        }

        return [
            __DIR__ . '/../Themes',
            __DIR__ . '/../../../../../Tests/Themes',
        ];
    }

    /**
     * Selector overrides for the current theme.
     *
     * Classic gets no special case: the loader looks for classic.json like it
     * would for any other theme, finds nothing, and the caller keeps the base
     * map — which already IS Classic. That is what makes "Classic by default" a
     * structural property rather than a branch.
     */
    protected function getThemeSelectors(array $pageNames): array
    {
        $theme = $this->getTheme();
        $selectors = [];
        $found = false;

        $directories = $this->themeDirectories();
        $catalogs = [];

        foreach ($directories as $dir) {
            $path = rtrim($dir, '/') . '/' . $theme . '.json';
            if (!file_exists($path)) {
                continue;
            }

            $found = true;
            $decoded = json_decode(file_get_contents($path), true);
            if (!is_array($decoded)) {
                continue;
            }

            $catalogs[] = $decoded;
        }

        // Least specific first: a block declared on an ancestor page applies to
        // every page that inherits from it, and the concrete page still wins.
        foreach ($this->themePageChains($pageNames) as $chain) {
            foreach ($catalogs as $catalog) {
                $selectors = [...$selectors, ...$this->walkThemeCatalog($catalog, $chain)];
            }
        }

        // Silence here would recreate the failure mode this whole mechanism
        // exists to remove: a suite that looks like it ran and proved nothing.
        if (!$found && $theme !== 'classic') {
            $this->lastThemeWarning = sprintf(
                'No selector file found for theme "%s"; falling back to the Classic base selectors.',
                $theme
            );
        }

        return $selectors;
    }

    /**
     * The page-name chains whose theme blocks apply to this page, least
     * specific first.
     *
     * Theme overrides used to be keyed on the CONCRETE class alone, so a page
     * that extends a sibling page (Category extends Listing, PricesDrop extends
     * Listing, ...) never saw its parent's overrides even though it inherits
     * every selector the parent defines. Walking class_parents() fixes that.
     *
     * The concrete page keeps going through getPageName() so a subclass that
     * overrides it (tests do) still steers its own lookup.
     *
     * @param array $pageNames the concrete page's chain, as computed by the caller
     * @return array<int, array<int, string>>
     */
    protected function themePageChains(array $pageNames): array
    {
        $chains = [];

        // The area's shared block, least specific of all. A selector declared
        // on the area base (FrontOfficePage's desktopLogo, userInfoLink, ...)
        // is inherited by every page in the area, but its own chain --
        // "FrontOffice\Page" -- is dropped by the guard below, so there was
        // nowhere to theme it once. The alternative was repeating it in all 31
        // page blocks. "_common" cannot collide with a page name: page segments
        // come from class namespaces and never start with an underscore.
        $area = $pageNames[0] ?? '';
        if (is_string($area) && $area !== '' && $area !== 'Page') {
            $chains[] = [$area, '_common'];
        }

        // array_reverse puts the furthest ancestor first, which is the order we
        // want to merge in.
        foreach (array_reverse(array_values(class_parents($this) ?: [])) as $class) {
            $name = preg_replace(
                '#^PrestaFlow\\\\Library\\\\Pages\\\\(?:v\\d+|Common)\\\\#',
                '',
                $class
            );

            if ($name === null || $name === $class) {
                // Not a page class under Pages\v{N}\ or Pages\Common\.
                continue;
            }

            $chains[] = explode('\\', $name);
        }

        $chains[] = $pageNames;

        // Trap 1: "Common\FrontOffice\Page" strips to "FrontOffice\Page". Its
        // walk descends FrontOffice, skips the trailing "Page" and lands on the
        // FrontOffice node -- a map of PAGE NAMES, not selectors. Merging that
        // would inject "Product", "Listing", ... as if they were selector keys.
        // A usable chain names an area AND a page, so anything shorter is dropped.
        $chains = array_values(array_filter(
            $chains,
            fn (array $chain): bool => count(array_filter($chain, fn ($s) => $s !== 'Page')) >= 2
        ));

        // A parent may resolve to the same chain as the child (same names under
        // two version namespaces); merging it twice is harmless but pointless.
        $unique = [];
        foreach ($chains as $chain) {
            $unique[implode('\\', $chain)] = $chain;
        }

        return array_values($unique);
    }

    /**
     * Descend the theme catalog one level per page-name segment, skipping the
     * trailing "Page", and return the selectors found there.
     *
     * Trap 2: only flat string values are selectors. Filtering to strings is the
     * belt to the chain-length braces above -- if a walk ever lands on a node of
     * page blocks again, nested arrays are dropped instead of polluting the map.
     */
    protected function walkThemeCatalog(array $catalog, array $chain): array
    {
        $node = $catalog;

        foreach ($chain as $pageName) {
            if ($pageName === 'Page') {
                continue;
            }
            $node = is_array($node) && isset($node[$pageName]) ? $node[$pageName] : [];
        }

        if (!is_array($node)) {
            return [];
        }

        return array_filter($node, fn ($value): bool => is_string($value));
    }

    public function getGlobal($index)
    {
        $globals = $this->getGlobals();

        if (!str_contains($index, '_')) {
            if (isset($globals[$index])) {
                return $globals[$index];
            }
        } else {
            // Test is, by the way, like PS_VERSION
            if (isset($globals[$index])) {
                return $globals[$index];
            }
            $indexes = explode('_', $index);
            if (is_array($indexes) && count($indexes)) {
                foreach ($indexes as $_index) {
                    if (isset($globals[$_index])) {
                        $globals = $globals[$_index];
                    }
                }
                return $globals;
            }
        }

        return null;
    }

    public function getPage()
    {
        return TestsSuite::getPage();
    }

    /**
     * Scrolle jusqu'à ce que $selector arrive en haut du viewport. À utiliser
     * juste avant une capture en mode VIEWPORT (visualCheckpoint sans sélecteur,
     * $fullPage=false) pour cadrer la capture sur le contenu utile plutôt que
     * sur le haut de la page (bandeaux promo, cookies, etc.). No-op si le
     * sélecteur est absent — la lib laisse au projet le soin de garantir la
     * présence de l'ancre (waitFor, etc.).
     */
    public function scrollToAnchor(string $selector, int $settleMs = 400): void
    {
        try {
            $selectorJson = json_encode($selector);
            $this->getPage()->evaluate(
                "(function(){"
                . " var el = document.querySelector($selectorJson);"
                . " if (el) { el.scrollIntoView({block: 'start'}); }"
                . "})()"
            );
            usleep($settleMs * 1000);
        } catch (\Throwable $e) {
            // best-effort : l'écart de capture tranchera si le scroll a échoué
        }
    }

    /**
     * Best-effort : dimensions du viewport courant via le navigateur. `null`
     * pour un des deux (ou les deux) si l'info n'est pas disponible (page
     * absente, evaluate en échec, etc.) — VisualTag::resolve() sait gérer
     * les segments manquants avec un placeholder `?`.
     *
     * @return array{0: ?int, 1: ?int} [width, height]
     */
    private function getViewportSize(): array
    {
        try {
            $value = $this->getPage()->evaluate(
                '(function(){return [window.innerWidth, window.innerHeight];})()'
            )->getReturnValue();

            if (is_array($value) && count($value) === 2) {
                return [
                    $value[0] !== null ? (int) $value[0] : null,
                    $value[1] !== null ? (int) $value[1] : null,
                ];
            }
        } catch (\Throwable $e) {
            // best-effort : le tag auto retombera sur des placeholders `?`
        }

        return [null, null];
    }

    /**
     * Point de contrôle de régression visuelle.
     * - pas de référence => capture-la (auto-baseline), PASS.
     * - référence présente => compare (score >= seuil = PASS, sinon FAIL + attaches actual/diff).
     *
     * Modes de capture :
     * - $selector non null => capture de l'élément ;
     * - $selector null && $fullPage=true (défaut) => pleine page ;
     * - $selector null && $fullPage=false => VIEWPORT seul (hauteur fixe de la
     *   fenêtre). À utiliser pour les pages HAUTES à contenu lazy dont la hauteur
     *   pleine page varie selon l'état de chargement (faux écarts).
     *
     * $tag : identifiant de baseline `(name, tag)`. `'auto'` (défaut) dérive le
     * tag de la version PS majeure + du viewport + de la locale (cf.
     * VisualTag::resolve()). Un tag libre est utilisé tel quel — il doit
     * matcher `^[a-z0-9._-]+$`, sinon exception explicite.
     */
    public function visualCheckpoint(string $name, ?string $selector = null, float $threshold = 0.98, bool $fullPage = true, string $tag = 'auto'): void
    {
        $rawMajorVersion = $this->getMajorVersion();
        $majorVersion = (is_string($rawMajorVersion) || is_int($rawMajorVersion))
            && ctype_digit((string) $rawMajorVersion)
            ? (int) $rawMajorVersion
            : null;

        [$viewportWidth, $viewportHeight] = $this->getViewportSize();

        $resolvedTag = \PrestaFlow\Library\Visual\VisualTag::resolve(
            $tag,
            $majorVersion,
            $viewportWidth,
            $viewportHeight,
            $this->getLocale()
        );

        $file = $name . '--' . $resolvedTag . '.png';
        $actualPath = \PrestaFlow\Library\Utils\Screenshots::actualPath($file, create: true);
        $refPath = \PrestaFlow\Library\Utils\Screenshots::referencePath($file, create: true);

        $page = $this->getPage();

        if ($selector !== null) {
            $node = $page->dom()->querySelector($selector);
            if ($node === null) {
                throw new \RuntimeException("visualCheckpoint : sélecteur introuvable « {$selector} »");
            }
            $page->screenshotElement($node)->saveToFile($actualPath);
        } elseif ($fullPage) {
            $page->screenshot([
                'captureBeyondViewport' => true,
                'clip' => $page->getFullPageClip(),
                'format' => 'png',
            ])->saveToFile($actualPath);
        } else {
            // Viewport seul : hauteur fixe (fenêtre), indépendante du total de la page.
            $page->screenshot(['format' => 'png'])->saveToFile($actualPath);
        }

        if (!is_file($refPath)) {
            copy($actualPath, $refPath);
            \PrestaFlow\Library\Tests\TestsSuite::recordVisualResult([
                'name' => $name, 'tag' => $resolvedTag, 'status' => 'baseline', 'score' => null, 'threshold' => $threshold,
                'reference' => $refPath, 'actual' => $actualPath, 'diff' => null,
            ]);
            \PrestaFlow\Library\Expects\Expect::that(true)->isTheSameAs(true);
            return;
        }

        $comparator = new \PrestaFlow\Library\Visual\VisualComparator();
        $score = $comparator->compare($refPath, $actualPath);
        $diffPath = \PrestaFlow\Library\Utils\Screenshots::diffPath($file, create: true);
        $comparator->generateDiff($refPath, $actualPath, $diffPath);

        $status = $score >= $threshold ? 'pass' : 'fail';
        \PrestaFlow\Library\Tests\TestsSuite::recordVisualResult([
            'name' => $name, 'tag' => $resolvedTag, 'status' => $status, 'score' => $score, 'threshold' => $threshold,
            'reference' => $refPath, 'actual' => $actualPath, 'diff' => $diffPath,
        ]);

        if ($status === 'fail') {
            \PrestaFlow\Library\Expects\Expect::setVisualAttachments([
                \PrestaFlow\Library\Utils\Screenshots::relativeVisualPath('actual', $file),
                \PrestaFlow\Library\Utils\Screenshots::relativeVisualPath('diff', $file),
            ]);
        }

        \PrestaFlow\Library\Expects\Expect::that($score >= $threshold)->isTheSameAs(true);
    }

    public function pageTitle()
    {
        return $this->translate($this->pageTitle);
    }

    public function getPageTitle()
    {
        return $this->getPage()->evaluate('document.title')->getReturnValue();
    }

    public function getMetaTitle()
    {
        return $this->getPage()->evaluate('document.title')->getReturnValue();
    }

    public function goToUrl(string $url)
    {
        // Filet : réappliquer les en-têtes persistants (ex. Authorization Basic
        // Auth) avant chaque navigation. Sans ça, la 1re navigation d'un run peut
        // partir sans les en-têtes si la page courante n'a jamais été (re)configurée
        // → 401 → chrome-error://. Idempotent, no-op sans en-têtes définis.
        \PrestaFlow\Library\Tests\TestsSuite::applyExtraHttpHeaders();
        // DOM_CONTENT_LOADED (au lieu du LOAD par défaut) : on n'attend pas les
        // assets tardifs (images, tracking, iframes). Les getters ont chacun leur
        // waitUntilContainsElement, donc c'est safe pour la majorité des cas.
        $this->getPage()->navigate($url)->waitForNavigation(DomPage::DOM_CONTENT_LOADED);
    }

    public function waitForPageLoaded()
    {
        $this->waitForNavigation(DomPage::DOM_CONTENT_LOADED, 10000);
    }

    public function getTextContent($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
    {
        try {
            $page = $this->getPage();
            if ($waitForSelector) {
                $page->waitUntilContainsElement($selector, $timeout);
            }
            $element = $page->dom()->querySelector($selector);
            $value = $element->getText();
            if ($value === null) {
                return '';
            }
            return trim(str_replace(['&nbsp;'], '', $value));
        } catch (OperationTimedOut | Exception $e) {
            return false;
        }
    }

    public function getInputValue($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
    {
        try {
            $page = $this->getPage();
            if ($waitForSelector) {
                $page->waitUntilContainsElement($selector, $timeout);
            }
            // Read the live `.value` property (works for <textarea> and for
            // values that differ from the initial `value` attribute).
            $value = $page->evaluate(sprintf(
                '(function(){var e=document.querySelector(%s);return e?e.value:null;})()',
                json_encode($selector)
            ))->getReturnValue();
            if ($value === null) {
                return '';
            }
            return trim(str_replace(['&nbsp;'], '', (string) $value));
        } catch (OperationTimedOut | Exception $e) {
            return false;
        }
    }

    public function navigateTo($selector, $index = 1, $waitForSelector = true, $timeout = 3000)
    {
        try {
            $page = $this->getPage();
            if ($waitForSelector) {
                $page->waitUntilContainsElement($selector, $timeout);
            }
            $element = $page->dom()->querySelector($selector);
            return $element->click();
        } catch (OperationTimedOut | Exception $e) {
            return false;
        }
    }

    public function click($selector, $nth = 1)
    {
        $page = $this->getPage();

        try {
            $element = $page->dom()->querySelector($selector);
            if ($element !== null) {
                return $element->click();
            }
        } catch (\Throwable $e) {
            // Element is hidden or outside the viewport (e.g. a collapsed menu
            // sub-link): fall back to a JS click, which navigates regardless.
        }

        return $page->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);if(e){e.click();return true;}return false;})()',
            json_encode($selector)
        ))->getReturnValue();
    }

    public function leftClick($selector, $nth = 1)
    {
        return $this->getPage()->mouse()->find($selector, $nth)->click();
    }

    public function clickAndWaitReload($selector, $nth = 1): void
    {
        $this->click($selector, $nth);
        $this->waitForPageReload();
    }

    public function waitForPageReload()
    {
        try {
            // Wait for the navigation triggered by the preceding action, bounded and
            // non-blocking: if it already completed (or is slow), downstream selector
            // waits take over instead of hanging on chrome-php's 30s default.
            $this->getPage()->waitForReload(\HeadlessChromium\Page::LOAD, 10000);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Poll a JS expression until it is truthy or the timeout expires.
     *
     * Complements waitForPageReload() (navigation) and elementIsVisible()
     * (presence) with a wait on *state*: a section that finished rendering over
     * AJAX, a button that lost its disabled attribute. Returns whether the
     * condition was met, so callers choose between asserting and branching.
     *
     * Not to be confused with waitUntil(), which is the other waiter on this
     * class and behaves differently in three ways that matter:
     *
     *  - the condition runs HERE, in the browser, not in PHP;
     *  - a timeout returns false instead of throwing TimeoutException;
     *  - a page that is busy mid-poll (navigation in flight, target detached)
     *    is retried rather than blowing up the step.
     *
     * Reach for waitUntil() when the condition is PHP-side, or when you want a
     * timeout to fail the step by itself.
     *
     * @param string $jsExpression a single JS *expression* (no statements, no
     *                             trailing `//` comments), evaluated for
     *                             truthiness; it is spliced into an expression
     *                             position, so a malformed one is a parse error
     *                             that surfaces as a thrown exception rather than
     *                             a `false` return
     * @param int    $timeout      total budget in milliseconds
     * @param int    $interval     delay between two polls, in milliseconds
     */
    public function waitForJsCondition(string $jsExpression, int $timeout = 10000, int $interval = 200): bool
    {
        $deadline = microtime(true) + ($timeout / 1000);
        $interval = max(1, $interval);

        $everPolled = false;
        $lastError = null;

        do {
            try {
                // The expression is wrapped in its own try/catch: a TypeError on a
                // node that is not in the DOM yet means "not ready", not "failed".
                // This only catches runtime errors: a parse error in $jsExpression
                // fails the whole script and is caught below instead.
                $remaining = max(1, (int) (($deadline - microtime(true)) * 1000));
                $met = $this->getPage()->evaluate(
                    '(function(){try{return !!(' . $jsExpression . ');}catch(e){return false;}})()'
                )->getReturnValue($remaining);

                $everPolled = true;

                if ($met === true) {
                    return true;
                }
            } catch (\Throwable $e) {
                // The page is busy (navigation in flight, target detached): retry
                // until the deadline rather than failing the whole step. But if
                // every single poll fails this way, it is far more likely a
                // malformed $jsExpression (parse error) than a transient blip, so
                // it gets rethrown below instead of masquerading as a timeout.
                $lastError = $e;
            }

            $remainingBeforeSleep = $deadline - microtime(true);
            if ($remainingBeforeSleep <= 0) {
                break;
            }

            usleep((int) min($interval * 1000, $remainingBeforeSleep * 1000000));
        } while (microtime(true) < $deadline);

        if (!$everPolled && $lastError !== null) {
            throw $lastError;
        }

        return false;
    }

    /**
     * @deprecated since 1.8.0, use waitForJsCondition() instead.
     *
     * The old name said "condition" without saying whose: this class also has
     * waitUntil(), which takes a PHP callable and throws on timeout. Neither
     * name told a reader which one they wanted, so the JS one now says so.
     *
     * Kept as a thin alias rather than removed — the rename is cosmetic, and
     * breaking a public method on a point release would cost callers more than
     * the confusion did.
     */
    public function waitForCondition(string $jsExpression, int $timeout = 10000, int $interval = 200): bool
    {
        return $this->waitForJsCondition($jsExpression, $timeout, $interval);
    }

    public function selectOption($selector, $value)
    {
        // Works with any CSS selector (incl. compound). Select the <option> by
        // its label and fire "change" so JS-enhanced selects (select2) update.
        $found = $this->getPage()->evaluate(sprintf(
            '(function(){var s=document.querySelector(%s);if(!s)return false;'
            . 'var o=[].slice.call(s.options).find(function(x){return x.text.trim()===%s;});'
            . 'if(!o)return false;s.value=o.value;s.dispatchEvent(new Event("change",{bubbles:true}));return true;})()',
            json_encode($selector),
            json_encode($value)
        ))->getReturnValue();

        if ($found !== true) {
            Expect::that($found)->equals(true, 'Option "' . $value . '" not found for selector "' . $selector . '"');
        }
    }

    public function selectValue($selector, $value)
    {
        $this->selectOption($selector, $value);
    }

    public function setValueByJs(string $selector, string $value): void
    {
        // Set a value directly via JS (value + input/change). Reliable for fields
        // on inactive tabs or otherwise not focusable, where click+type fails.
        $this->getPage()->evaluate(sprintf(
            '(function(){var e=document.querySelector(%s);if(e){e.value=%s;'
            . 'e.dispatchEvent(new Event("input",{bubbles:true}));'
            . 'e.dispatchEvent(new Event("change",{bubbles:true}));}})()',
            json_encode($selector),
            json_encode($value)
        ));
    }

    /**
     * Delete the existing text then type new value on input
     */
    public function setValue($selector, $value)
    {
        $this->click($selector);

        $textContent = $this->getInputValue($selector);

        $page = $this->getPage();

        if ($textContent !== null && $textContent !== '') {
            $element = $page->dom()->querySelector($selector);
            if ($element !== null) {
                // Clear the input value
                $element->setAttributeValue('value', '');
            }
        }

        // Alternatively, you can use the keyboard to delete the text
        // $page->keyboard()->typeRawKey('Del'); // Delete key
        $page->keyboard()->typeText($value);
        // or
        // $element->sendKeys($value);
    }

    public function fillQuantity($selector, int $qty): void
    {
        $this->setValue($selector, $qty);
    }

    public function elementIsVisible($selector, $timeout = 1000)
    {
        try {
            $elem = $this->getPage()->waitUntilContainsElement($selector, $timeout);
            if (get_class($elem->dom()) instanceof \HeadlessChromium\Dom) {
                return true;
            }
        } catch (TargetDestroyed $e) {
            // The browser or the page went away (closed by another process,
            // crashed). That is not "the element is missing": answering false
            // would turn a lost browser into a failed assertion on the shop.
            throw $e;
        } catch (ElementNotFoundException | OperationTimedOut | Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Poll a PHP callable until it returns truthy, or throw TimeoutException.
     *
     * The base the waitVisible() / waitHidden() / waitForText() helpers are
     * built on. The condition is NOT wrapped: if the callable throws, that
     * exception propagates — which is what you want for a condition that reads
     * state PrestaFlow already holds, and not what you want for one that has to
     * reach into a page that may be navigating.
     *
     * For the latter, use waitForJsCondition(): it evaluates a JS expression in
     * the browser, survives a busy page, and returns false on timeout rather
     * than throwing.
     */
    public function waitUntil(callable $condition, int $timeout = 10000, string $message = 'Condition was not met.'): void
    {
        $deadline = microtime(true) + ($timeout / 1000);
        $interval = 100000; // 100ms

        while (true) {
            if ($condition()) {
                return;
            }

            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                break;
            }

            // Cap the sleep so we don't overshoot the deadline.
            usleep((int) min($interval, $remaining * 1_000_000));
        }

        throw new TimeoutException($message);
    }

    public function waitVisible($selector, int $timeout = 10000, ?string $message = null): void
    {
        $this->waitUntil(
            fn () => $this->isVisible($selector, 100),
            $timeout,
            $message ?? sprintf('Element did not become visible: %s', $selector)
        );
    }

    public function waitHidden($selector, int $timeout = 10000, ?string $message = null): void
    {
        $this->waitUntil(
            fn () => !$this->isVisible($selector, 100),
            $timeout,
            $message ?? sprintf('Element did not become hidden: %s', $selector)
        );
    }

    public function waitForText(string $text, int $timeout = 10000, string $selector = 'body', ?string $message = null): void
    {
        $waitForSelector = true;

        $this->waitUntil(
            function () use ($selector, $text, &$waitForSelector) {
                $content = $this->getTextContent($selector, 1, $waitForSelector, 100);
                // Only wait for the selector on the first poll; subsequent
                // polls only care about the text content.
                $waitForSelector = false;

                // getTextContent returns false on timeout and '' when the
                // element has no text yet — is_string filters out the false.
                return is_string($content) && str_contains($content, $text);
            },
            $timeout,
            $message ?? sprintf('Text did not appear in %s: %s', $selector, $text)
        );
    }

    public function isVisible($selector, $timeout = 1000)
    {
        return $this->elementIsVisible($selector, $timeout);
    }

    public function getStoragePath($dir)
    {
        if (function_exists('storage_path')) {
            return storage_path();
        }

        return $dir . '/../../../..';
    }

    public function getPageName(): string
    {
        return str_replace('PrestaFlow\\Library\\Pages\\v' . $this->getMajorVersion(namespace: true) . '\\', '', get_class($this));
    }

    public function getSelectors(array $selectors = []): array
    {
        $pageSelectors = [];
        if (method_exists($this, 'defineSelectors')) {
            $pageSelectors = $this->defineSelectors();
        }

        $pageNames = explode('\\', $this->getPageName());

        $baseSelectors = [...$selectors, ...$pageSelectors];

        $customPath = __DIR__ . '/../../../../../Tests/Selectors/';

        $fileName = $this->getLocale() . '.json';

        $customSelectors = [];
        $pathToCatalog = $customPath . $fileName;
        if (file_exists($pathToCatalog)) {
            $customSelectors = json_decode(file_get_contents($pathToCatalog), true);

            if (count($customSelectors)) {
                foreach ($pageNames as $pageName) {
                    if ($pageName !== 'Page') {
                        if (isset($customSelectors[$pageName])) {
                            $customSelectors = $customSelectors[$pageName];
                        } else {
                            $customSelectors = [];
                        }
                    }
                }
            }
        }

        $specificSelectors = [];
        if (is_array($this->customs['selectors'])) {
            $specificSelectors = $this->customs['selectors'];
            foreach ($pageNames as $pageName) {
                if ($pageName !== 'Page') {
                    if (isset($specificSelectors[$pageName])) {
                        $specificSelectors = $specificSelectors[$pageName];
                    } else {
                        $specificSelectors = [];
                    }
                }
            }
        }

        $mergedSelectors = [
            ...$baseSelectors,
            ...$this->getThemeSelectors($pageNames),
            ...$customSelectors,
            ...$specificSelectors,
        ];

        return $mergedSelectors;
    }

    public function getMessages(): array
    {
        $messages = [];

        $pageMessages = [];
        if (method_exists($this, 'defineMessages')) {
            $pageMessages = $this->defineMessages();
        }

        $pageNames = explode('\\', $this->getPageName());

        $baseMessages = [...$messages, ...$pageMessages];

        $customPath = __DIR__ . '/../../../../../Tests/Messages/';
        $fileName = $this->getLocale() . '.json';
        $customMessages = [];
        $pathToCatalog = $customPath . $fileName;
        if (file_exists($pathToCatalog)) {
            $customMessages = json_decode(file_get_contents($pathToCatalog), true);

            if (count($customMessages)) {
                $pageName = str_replace('PrestaFlow\\Library\\Pages\\v' . $this->getMajorVersion(namespace: true) . '\\', '', get_class($this));
                $pageNames = explode('\\', $pageName);

                foreach ($pageNames as $pageName) {
                    if ($pageName !== 'Page') {
                        if (isset($customMessages[$pageName])) {
                            $customMessages = $customMessages[$pageName];
                        } else {
                            $customMessages = [];
                        }
                    }
                }
            }
        }

        $specificMessages = [];
        if (is_array($this->customs['messages'])) {
            $specificMessages = $this->customs['messages'];
            foreach ($pageNames as $pageName) {
                if ($pageName !== 'Page') {
                    if (isset($specificMessages[$pageName])) {
                        $specificMessages = $specificMessages[$pageName];
                    } else {
                        $specificMessages = [];
                    }
                }
            }
        }

        $mergedMessages = [
            ...$baseMessages,
            ...$customMessages,
            ...$specificMessages,
        ];

        return $mergedMessages;
    }
}
