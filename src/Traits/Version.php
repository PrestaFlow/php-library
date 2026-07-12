<?php

namespace PrestaFlow\Library\Traits;

use PrestaFlow\Library\Utils\Env;

trait Version
{
    const SUPPORTED_VERSIONS = [
        '1.7',
        '8',
        '9'
    ];

    public static $versions = [
        'patchVersion' => null,
        'minorVersion' => null,
        'majorVersion' => null,
    ];

    /**
     * Fluent override set via onVersion(); wins over the $psVersion property and env.
     */
    protected ?string $psVersionOverride = null;

    /**
     * Pin a specific PrestaShop version for this suite. Fluent, chainable.
     * Overrides the $psVersion property and the PRESTAFLOW_PS_VERSION env variable.
     */
    public function onVersion(string $version): self
    {
        if (!preg_match('/^\d+\.\d+(\.\d+){0,2}$/', $version)) {
            throw new \InvalidArgumentException(
                "Invalid PS version: '" . $version . "'. Expected format like '1.7.8.11' or '9.0.1'."
            );
        }

        $this->psVersionOverride = $version;

        return $this;
    }

    /**
     * Resolve the effective PS version and populate $this->globals['PS_VERSION'] + version parts.
     * Priority: fluent onVersion() > $psVersion property > PRESTAFLOW_PS_VERSION env > '8.1.0'.
     */
    public function resolveVersion(): void
    {
        $propertyVersion = property_exists($this, 'psVersion') ? ($this->psVersion ?? null) : null;

        $version = $this->psVersionOverride
            ?? $propertyVersion
            ?? Env::get('PRESTAFLOW_PS_VERSION')
            ?? ($this->globals['PS_VERSION'] ?? null)
            ?? '8.1.0';

        if (!is_array($this->globals ?? null)) {
            $this->globals = [];
        }
        $this->globals['PS_VERSION'] = $version;

        // Reset all cached version parts defensively so consumers (e.g. Translations, Scenario)
        // that read the static state without calling resolveVersion() don't see stale values
        // from a previous suite pinned to a different version.
        self::$versions['patchVersion'] = null;
        self::$versions['minorVersion'] = null;
        self::$versions['majorVersion'] = null;

        $this->exctractVersions($version);
    }

    public function isVersionSupported()
    {
        if (in_array($this->getMajorVersion(), self::SUPPORTED_VERSIONS)) {
            return true;
        }

        return false;
    }

    public function setVersions($versions = []): array
    {
        return self::$versions = $versions;
    }

    public function getVersions(): array
    {
        return self::$versions;
    }

    public function setPatchVersion(string $patchVersion)
    {
        self::$versions['patchVersion'] = $patchVersion;
    }

    public function getPatchVersion()
    {
        return self::$versions['patchVersion'] ;
    }

    public function setMinorVersion(string $minorVersion)
    {
        self::$versions['minorVersion']  = $minorVersion;
    }

    public function getMinorVersion()
    {
        return self::$versions['minorVersion'] ;
    }

    public function setMajorVersion(string $majorVersion)
    {
        self::$versions['majorVersion']  = $majorVersion;
    }

    public function getMajorVersion(bool $namespace = false)
    {
        if (!empty(self::$versions['majorVersion'])) {
            if ($namespace && str_starts_with(self::$versions['majorVersion'], 1.7)) {
                return substr(self::$versions['majorVersion'], strlen('1.'));
            }

            return self::$versions['majorVersion'];
        }

        if (isset($this->globals['PS_VERSION'])) {
            if (version_compare($this->globals['PS_VERSION'], '9.0.0', '>=')) {
                $this->setMajorVersion('9');
                return '9';
            } else if (version_compare($this->globals['PS_VERSION'], '8.0.0', '>=')) {
                $this->setMajorVersion('8');
                return '8';
            } else if (version_compare($this->globals['PS_VERSION'], '1.7.0', '>=')) {
                $this->setMajorVersion('1.7');

                if ($namespace) {
                    return '7';
                }

                return '1.7';
            } else if (version_compare($this->globals['PS_VERSION'], '1.6.0', '>=')) {
                $this->setMajorVersion('1.6');

                if ($namespace) {
                    return '6';
                }

                return '1.6';
            }
        }

        $this->setMajorVersion('8');
        return '8';
    }

    public function exctractVersions(string $patchVersion)
    {
        self::$versions['patchVersion'] = $patchVersion;

        if (strlen(self::$versions['patchVersion']) === 7 || strlen(self::$versions['patchVersion']) === 8) {
            self::$versions['minorVersion'] = substr(self::$versions['patchVersion'], 0, 5);
            if (str_starts_with(self::$versions['minorVersion'], '1.7')) {
                self::$versions['majorVersion'] = '1.7';
            } else if (str_starts_with(self::$versions['minorVersion'], '1.6')) {
                self::$versions['majorVersion'] = '1.6';
            } else {
                self::$versions['majorVersion'] = substr(self::$versions['minorVersion'], 0, 1);
            }
        } else if (strlen(self::$versions['patchVersion']) === 5) {
            self::$versions['minorVersion'] = substr(self::$versions['patchVersion'], 0, 3);
            if (str_starts_with(self::$versions['minorVersion'], '1.7')) {
                self::$versions['majorVersion'] = '1.7';
            } else if (str_starts_with(self::$versions['minorVersion'], '1.6')) {
                self::$versions['majorVersion'] = '1.6';
            } else {
                self::$versions['majorVersion'] = substr(self::$versions['minorVersion'], 0, 1);
            }
        } else {
            throw new InvalidVersionException('Error with version ' . self::$versions['patchVersion']);
        }
    }
}
