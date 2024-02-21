<?php

namespace PrestaFlow\Library\Resolvers;

use PrestaFlow\Library\Exceptions\FileNotFoundException;
use PrestaFlow\Library\Utils\Locale;
use PrestaFlow\Library\Utils\Versions;

class VersionSelectResolver
{
    use Locale;
    use Versions;

    public $configClassMap = [];

    public function __construct(string $patchVersion, string $locale, $configClassMap = [])
    {
        $this->setVersions($patchVersion);
        $this->setLocale($locale);

        $this->configClassMap = $configClassMap;
    }

    public function getFilePath(string $selector)
    {
        /*
            if (this.configClassMap) {
                // Search a reference for this file in the configClassMap
                const referenceExists = this.configClassMap.find(el => el.file === selector);

                if (referenceExists) {
                    // we have this file in the configClassMap
                    const {versions} = referenceExists;
                    if (versions[this.version]) {
                    // we have the file for the correct version !
                    return versions[this.version];
                    }
                }
            }

            // either we don't have the file in configClassMap or we don't have a target for this version
            let versionForFilepath = this.majorVersion + '/' + this.minorVersion + '/' + this.patchVersion;

            const basePath = path.resolve(__dirname, '../..');

            if (fs.existsSync(`${basePath}/versions/${versionForFilepath}/${selector}`)) {
                return `${basePath}/versions/${versionForFilepath}/${selector}`;
            }

            versionForFilepath = this.majorVersion + '/' + this.minorVersion;
            if (fs.existsSync(`${basePath}/versions/${versionForFilepath}/${selector}`)) {
                return `${basePath}/versions/${versionForFilepath}/${selector}`;
            }

            versionForFilepath = this.majorVersion;
            if (fs.existsSync(`${basePath}/versions/${versionForFilepath}/${selector}`)) {
                return `${basePath}/versions/${versionForFilepath}/${selector}`;
            }

            throw new Error(`Couldn't find the file '${selector}' in version folder '${this.version}'`);
        */
        throw new FileNotFoundException('Couldn\'t find the file '.$selector.' in version folder ['.$this->majorVersion.', '.$this->minorVersion.', '.$this->patchVersion.']');
    }

    public function require($selector)
    {
        /*
            return require(
                this.getFilePath(selector),
            );
        */
    }
}
