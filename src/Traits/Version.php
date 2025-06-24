<?php

namespace PrestaFlow\Library\Traits;

trait Version
{
    const SUPPORTED_VERSIONS = [
        '8',
        '9'
    ];

    public static $versions = [
        'patchVersion' => null,
        'minorVersion' => null,
        'majorVersion' => null,
    ];

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

    public function getMajorVersion()
    {
        if (!empty(self::$versions['majorVersion'])) {
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
                return '1.7';
            } else if (version_compare($this->globals['PS_VERSION'], '1.6.0', '>=')) {
                $this->setMajorVersion('1.6');
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
