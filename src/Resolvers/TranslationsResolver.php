<?php

namespace PrestaFlow\Library\Resolvers;

use Utils\Versions;

class TranslationsResolver
{
    use Versions;

    public string $locale;

    public function __construct(string $patchVersion, string $locale)
    {
        $this->setVersions($patchVersion);
        $this->locale = $locale;
    }
}
