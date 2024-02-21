<?php

namespace PrestaFlow\Library\Utils;

use PrestaFlow\Library\Exceptions\InvalidVersionException;

trait Versions
{
    public string $patchVersion;
    public string $minorVersion;
    public string $majorVersion;

    public function setVersions(string $patchVersion)
    {
        $this->patchVersion = $patchVersion;

        if (strlen($this->patchVersion) === 7 || strlen($this->patchVersion) === 8) {
            $this->minorVersion = substr($this->patchVersion, 0, 5);
            if (str_starts_with($this->minorVersion, '1.7')) {
                $this->majorVersion = '1.7';
            } else if (str_starts_with($this->minorVersion, '1.6')) {
                $this->majorVersion = '1.6';
            } else {
                $this->majorVersion = substr($this->minorVersion, 0, 1);
            }
        } else if (strlen($this->patchVersion) === 5) {
            $this->minorVersion = substr($this->patchVersion, 0, 3);
            if (str_starts_with($this->minorVersion, '1.7')) {
                $this->majorVersion = '1.7';
            } else if (str_starts_with($this->minorVersion, '1.6')) {
                $this->majorVersion = '1.6';
            } else {
                $this->majorVersion = substr($this->minorVersion, 0, 1);
            }
        } else {
            throw new InvalidVersionException('Error with version ' . $this->patchVersion);
        }
    }
}
