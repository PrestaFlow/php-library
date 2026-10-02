<?php

namespace PrestaFlow\Library\Traits;

use PrestaFlow\Library\Exceptions\InvalidVersionException;
use PrestaFlow\Library\Utils\Env;

trait Version
{
    private static array $supportedVersions = [
        '1.7',
        '8',
        '9'
    ];

    /**
     * Version PrestaShop de CET objet (page, suite, scénario). Propriété
     * d'instance : un statique de trait est partagé par les sous-classes (et,
     * avant PHP 8.3, entre classes qui refont `use`), si bien qu'une page
     * imposait sa version aux suivantes.
     */
    protected array $versions = [
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

        // Once the globals are loaded, the version has already been resolved:
        // resolve it again so the pages imported next use this one.
        if (!empty($this->globals)) {
            $this->resolveVersion();
        }

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

        // Reset all cached version parts defensively so a re-resolution (e.g. after
        // onVersion()) never keeps stale values from a previous version.
        $this->setVersions([]);

        $this->exctractVersions($version);
    }

    public function isVersionSupported()
    {
        if (in_array($this->getMajorVersion(), self::$supportedVersions)) {
            return true;
        }

        return false;
    }

    public function setVersions(array $versions = []): array
    {
        return $this->versions = [
            'patchVersion' => $versions['patchVersion'] ?? null,
            'minorVersion' => $versions['minorVersion'] ?? null,
            'majorVersion' => $versions['majorVersion'] ?? null,
        ];
    }

    public function getVersions(): array
    {
        return $this->versions;
    }

    public function setPatchVersion(string $patchVersion)
    {
        $this->versions['patchVersion'] = $patchVersion;
    }

    public function getPatchVersion()
    {
        return $this->versions['patchVersion'];
    }

    public function setMinorVersion(string $minorVersion)
    {
        $this->versions['minorVersion'] = $minorVersion;
    }

    public function getMinorVersion()
    {
        return $this->versions['minorVersion'];
    }

    public function setMajorVersion(string $majorVersion)
    {
        $this->versions['majorVersion'] = $majorVersion;
    }

    /**
     * Majeure de cet objet ('1.7', '8', '9'). Si elle n'a jamais été posée,
     * elle se déduit du PS_VERSION des globals ; sans lui, erreur explicite
     * (il n'y a plus de repli sur '8').
     */
    public function getMajorVersion(bool $namespace = false)
    {
        if (empty($this->versions['majorVersion'])) {
            $psVersion = $this->globals['PS_VERSION'] ?? null;

            if (!is_string($psVersion) || $psVersion === '') {
                throw new InvalidVersionException(
                    static::class . ' : version PrestaShop inconnue (aucune version reçue, ni PS_VERSION dans les globals).'
                );
            }

            $this->exctractVersions($psVersion);
        }

        $majorVersion = $this->versions['majorVersion'];

        if ($namespace && str_starts_with($majorVersion, '1.')) {
            return substr($majorVersion, strlen('1.'));
        }

        return $majorVersion;
    }

    public function exctractVersions(string $patchVersion)
    {
        $this->versions['patchVersion'] = $patchVersion;

        if (strlen($patchVersion) === 7 || strlen($patchVersion) === 8) {
            $this->versions['minorVersion'] = substr($patchVersion, 0, 5);
        } else if (strlen($patchVersion) === 5) {
            $this->versions['minorVersion'] = substr($patchVersion, 0, 3);
        } else {
            throw new InvalidVersionException('Error with version ' . $patchVersion);
        }

        $minorVersion = $this->versions['minorVersion'];
        if (str_starts_with($minorVersion, '1.7')) {
            $this->versions['majorVersion'] = '1.7';
        } else if (str_starts_with($minorVersion, '1.6')) {
            $this->versions['majorVersion'] = '1.6';
        } else {
            $this->versions['majorVersion'] = substr($minorVersion, 0, 1);
        }
    }
}
