<?php

namespace PrestaFlow\Library\Tests;

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

    public function __construct(bool $loadGlobals = true, bool $getBrowser = true)
    {
        // CLI : aucun override posé par l'app → preset du device (env ou 1er déclaré).
        if ($getBrowser && self::$browserOptionOverrides === null) {
            $preset = VisualDevices::get($this->deviceFromEnv() ?? ($this->devices[0] ?? 'desktop'));
            self::useBrowserOptions($preset['width'], $preset['height'], $preset['userAgent']);
        }
        parent::__construct(loadGlobals: $loadGlobals, getBrowser: $getBrowser);
    }

    public function devices(): array { return $this->devices; }
    public function locales(): array { return $this->locales; }
    public function checkpoints(): array { return array_map([self::class, 'normalize'], $this->checkpoints); }

    public static function normalize(array $cp): array
    {
        return array_merge([
            'path' => '', 'paths' => [], 'zone' => 'viewport', 'selector' => null,
            'waitFor' => null, 'scrollBelow' => null, 'threshold' => 0.98,
            'excludeDevices' => [], 'masks' => [],
        ], $cp);
    }

    public function currentDevice(): string
    {
        $globals = $this->globals ?? [];

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

        $this->describe($this->title ?: static::class);

        foreach ($this->checkpoints() as $cp) {
            $title = 'capture visuelle : '.$cp['name'];
            $path = $this->resolvePath($cp, $locale);

            if ($path === null || in_array($device, $cp['excludeDevices'], true)) {
                $this->skip($title, function () {});
                continue;
            }

            $url = $this->resolveUrl($path, $locale);
            $this->it($title, function () use ($page, $cp, $url) {
                $page->goToUrl($url);
                $page->waitForStable();
                if ($cp['waitFor']) {
                    $page->waitVisible($cp['waitFor']);
                }
                if ($cp['scrollBelow'] && $cp['zone'] === 'viewport') {
                    $page->scrollBelow($cp['scrollBelow']);
                }
                $page->visualCheckpoint(
                    $cp['name'],
                    $cp['zone'] === 'element' ? $cp['selector'] : null,
                    (float) $cp['threshold'],
                    $cp['zone'] === 'full',
                    'auto',
                    $cp['masks'],
                );
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
