<?php

namespace PrestaFlow\Library\Utils;

class Versions
{
    public string $patchVersion;
    public string $minorVersion;
    public string $majorVersion;

    public function setVersions(string $patchVersion)
    {
        $this->patchVersion = $patchVersion;
        /*
        if (this.patchVersion.length === 7 || this.patchVersion.length === 8) {
            this.minorVersion = this.patchVersion.slice(0, 5);
            if (this.minorVersion.includes('1.7')) {
              this.majorVersion = '1.7';
            } else if (this.minorVersion.includes('1.6')) {
              this.majorVersion = '1.6';
            } else {
              this.majorVersion = this.minorVersion.slice(0, 1);
            }
          } else if (this.patchVersion.length === 5) {
            this.minorVersion = this.patchVersion.slice(0, 3);
            if (this.minorVersion.includes('1.7')) {
              this.majorVersion = '1.7';
            } else if (this.minorVersion.includes('1.6')) {
              this.majorVersion = '1.6';
            } else {
              this.majorVersion = this.minorVersion.slice(0, 1);
            }
          } else {
            throw new Error(`Error with version '${this.patchVersion}'`);
          }
        */

        throw new InvalidVersionException('Error with version ' . $this->patchVersion);
    }
}
