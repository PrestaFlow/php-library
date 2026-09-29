<?php

namespace PrestaFlow\Library\Tests;

use PrestaFlow\Library\Expects\Expect;
use PrestaFlow\Library\Utils\Env;
use PrestaFlow\Library\Visual\VisualDevices;

/**
 * Suite de régression visuelle pilotée par données. Les sous-classes ne
 * déclarent que $devices, $locales et $checkpoints (littéraux, édités par l'app).
 * Une exécution couvre UNE combinaison device × locale (globals DEVICE / LOCALE,
 * ou env PRESTAFLOW_DEVICE / PRESTAFLOW_LOCALE en CLI).
 */
abstract class VisualTestsSuite extends TestsSuite
{
    protected array $devices = ['desktop'];
    protected array $locales = [];
    protected array $checkpoints = [];

    /**
     * Préfixe des noms de checkpoint (`{scope}.{name}`) : les noms ne sont uniques
     * qu'au sein d'une suite, le scope évite que deux suites avec un checkpoint
     * `header` écrasent mutuellement captures et références. Vide = kebab-case du
     * nom court de la classe (NouvelleScene → nouvelle-scene). Doit matcher [a-z0-9-]+.
     */
    protected string $visualScope = '';

    /** Avertissement posé sur le test quand waitForStable() expire (la capture est prise quand même). */
    public const UNSTABLE_WARNING = 'Page non stabilisée après 5 s (images/polices encore en chargement) : capture prise quand même.';

    /**
     * URL sur laquelle le dernier checkpoint exécuté (et réussi) a capturé :
     * le suivant sur la même URL (header / footer / home = '') ne recharge pas
     * la page. Remis à null dès qu'un checkpoint lève.
     */
    private ?string $lastVisualUrl = null;

    /**
     * Dernier override posé par applyDevicePreset(). Tant que les overrides
     * courants lui sont identiques, ils « nous appartiennent » et peuvent être
     * remplacés (suite visuelle suivante, autre device). Si l'app a posé les
     * siens entre-temps, ils diffèrent → on n'y touche jamais.
     */
    private static ?array $appliedPreset = null;

    public function __construct(bool $loadGlobals = true, bool $getBrowser = true)
    {
        // CLI : preset du device (env ou 1er déclaré), sauf si l'app a posé ses options.
        if ($getBrowser) {
            static::applyDevicePreset($this->deviceFromEnv() ?? ($this->devices[0] ?? 'desktop'));
        }

        parent::__construct(loadGlobals: $loadGlobals, getBrowser: $getBrowser);

        // loadGlobals() pose toujours LOCALE (défaut 'en') : sans PRESTAFLOW_LOCALE
        // explicite, la locale par défaut d'une suite visuelle est sa 1re déclarée.
        if ($loadGlobals && $this->locales !== [] && (string) Env::get('PRESTAFLOW_LOCALE', '') === '') {
            $this->setGlobals(['LOCALE' => $this->locales[0]]);
        }
    }

    /**
     * Applique le preset du device si les options navigateur ne sont pas déjà
     * pilotées par l'app. Retourne true si le navigateur partagé a été réinitialisé
     * (options différentes → un navigateur keepAlive réutilisé garderait sinon
     * l'ancienne taille / UA).
     */
    protected static function applyDevicePreset(string $device): bool
    {
        $overrides = self::$browserOptionOverrides;
        if ($overrides !== null && $overrides !== self::$appliedPreset) {
            return false; // options posées par l'app : intouchables
        }

        $preset = VisualDevices::get($device);
        $wanted = ['windowSize' => [$preset['width'], $preset['height']], 'userAgent' => $preset['userAgent']];
        if (TestsSuite::browserOptions() === $wanted) {
            return false;
        }

        self::useBrowserOptions($preset['width'], $preset['height'], $preset['userAgent']);
        self::$appliedPreset = self::$browserOptionOverrides;
        TestsSuite::resetBrowser();

        return true;
    }

    /**
     * Remplace la définition littérale de la classe par des données fraîchement
     * parsées : un worker PHP long-vivant qui a déjà chargé la classe ne voit pas
     * les éditions ultérieures du fichier. À appeler avant init().
     */
    public function useDefinition(array $devices, array $locales, array $checkpoints): static
    {
        $this->devices = array_values($devices);
        $this->locales = array_values($locales);
        $this->checkpoints = array_values($checkpoints);

        return $this;
    }

    public function visualScope(): string
    {
        if ($this->visualScope !== '') {
            if (preg_match('/^[a-z0-9-]+$/', $this->visualScope) !== 1) {
                throw new \InvalidArgumentException(sprintf('%s : visualScope « %s » doit matcher [a-z0-9-]+', static::class, $this->visualScope));
            }

            return $this->visualScope;
        }

        $class = static::class;
        if (str_contains($class, '@anonymous')) {
            $class = get_parent_class($this) ?: 'Visual';
        }

        return self::slugify(substr(strrchr('\\'.$class, '\\'), 1)) ?: 'visual';
    }

    /** Kebab-case filename-safe : NouvelleScene → nouvelle-scene, ProdVisual2 → prod-visual2. */
    public static function slugify(string $name): string
    {
        $kebab = preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $name);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $kebab)), '-');
    }

    public function devices(): array { return $this->devices; }
    public function locales(): array { return $this->locales; }
    public function checkpoints(): array { return array_map([self::class, 'normalize'], $this->checkpoints); }

    public static function normalize(array $cp): array
    {
        $cp = array_merge([
            'path' => '', 'paths' => [], 'zone' => 'viewport', 'selector' => null,
            'waitFor' => null, 'scrollBelow' => null, 'threshold' => 0.999,
            'excludeDevices' => [], 'masks' => [],
        ], $cp);
        $cp['threshold'] = max(0.5, min(1.0, (float) $cp['threshold']));

        return $cp;
    }

    public function currentDevice(): string
    {
        try {
            $globals = $this->getGlobals();
        } catch (\Throwable) {
            $globals = [];
        }

        return $globals['DEVICE'] ?? $this->deviceFromEnv() ?? ($this->devices[0] ?? 'desktop');
    }

    public function currentLocale(): string
    {
        return (string) ($this->getGlobals()['LOCALE'] ?? ($this->locales[0] ?? 'fr'));
    }

    public function resolvePath(array $cp, string $locale): ?string
    {
        $override = $cp['paths'][$locale] ?? null;
        if ($override === false) {
            return null;
        }

        return is_string($override) && $override !== '' ? $override : (string) ($cp['path'] ?? '');
    }

    public function resolveUrl(string $path, string $locale): string
    {
        $globals = $this->getGlobals();
        $base = rtrim((string) ($globals['FO']['URL'] ?? ''), '/').'/';
        $prefix = !empty($globals['PREFIX_LOCALE']) ? $locale.'/' : '';

        return $base.$prefix.ltrim($path, '/');
    }

    public function init()
    {
        parent::init();
        $this->importVisualPage();
        $page = $this->pages['frontOfficePage'] ?? null;
        $device = $this->currentDevice();
        $locale = $this->currentLocale();

        if (!in_array($device, $this->devices, true)) {
            throw new \InvalidArgumentException(sprintf('%s : device « %s » non déclaré (%s)', static::class, $device, implode(', ', $this->devices)));
        }
        if ($this->locales !== [] && !in_array($locale, $this->locales, true)) {
            throw new \InvalidArgumentException(sprintf('%s : locale « %s » non déclarée (%s)', static::class, $locale, implode(', ', $this->locales)));
        }

        $this->describe($this->title ?: substr(strrchr('\\'.static::class, '\\'), 1));

        $scope = $this->visualScope();
        $this->lastVisualUrl = null;

        foreach ($this->checkpoints() as $cp) {
            $title = 'capture visuelle : '.$cp['name'];
            $path = $this->resolvePath($cp, $locale);

            if ($path === null || in_array($device, $cp['excludeDevices'], true)) {
                $this->skip($title, function () {});
                continue;
            }

            $url = $this->resolveUrl($path, $locale);
            $this->it($title, function () use ($page, $cp, $url, $scope) {
                // Un avertissement de stabilisation ne concerne que son propre checkpoint
                // (Expect::$latestWarning est global et persistant).
                if (Expect::$latestWarning === self::UNSTABLE_WARNING) {
                    Expect::setWarning('');
                }

                $sameUrl = $this->lastVisualUrl === $url;
                $this->lastVisualUrl = null; // invalidé tant que ce checkpoint n'a pas abouti

                if (!$sameUrl) {
                    $page->goToUrl($url);
                }
                if ($page->waitForStable() === false) {
                    Expect::setWarning(self::UNSTABLE_WARNING);
                }
                if ($cp['waitFor']) {
                    $page->waitVisible($cp['waitFor']);
                }
                if ($cp['scrollBelow'] && $cp['zone'] === 'viewport') {
                    $page->scrollBelow($cp['scrollBelow']); // remet d'abord le scroll en haut
                } elseif ($sameUrl) {
                    $page->scrollToTop(); // ne pas hériter du scroll du checkpoint précédent
                }
                $page->visualCheckpoint(
                    $scope.'.'.$cp['name'],
                    $cp['zone'] === 'element' ? $cp['selector'] : null,
                    (float) $cp['threshold'],
                    $cp['zone'] === 'full',
                    'auto',
                    $cp['masks'],
                );

                $this->lastVisualUrl = $url;
            });
        }

        return $this;
    }

    /** Surchargé en test unitaire (pas de navigateur). */
    protected function importVisualPage(): void
    {
        $this->importPage('FrontOffice');
    }

    private function deviceFromEnv(): ?string
    {
        $env = Env::get('PRESTAFLOW_DEVICE');

        return is_string($env) && $env !== '' ? $env : null;
    }
}
