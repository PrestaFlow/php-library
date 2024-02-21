<?php

namespace PrestaFlow\Library\Resolvers;

use PrestaFlow\Library\Utils\Locale;
use PrestaFlow\Library\Utils\Versions;

class DemoDatasResolver
{
    use Locale;
    use Versions;

    public function __construct(string $patchVersion, string $locale)
    {
        $this->setVersions($patchVersion);
        $this->setLocale($locale);
    }

    public function getFilePath(string $selector)
    {
        /*
            const basePath = path.resolve(__dirname, '../..');

            if (!fs.existsSync(`${basePath}/datas/${this.locale}/${selector}`)) {
            return `${basePath}/datas/${selector}`;
            }

            return `${basePath}/datas/${this.locale}/${selector}`;
        */
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
