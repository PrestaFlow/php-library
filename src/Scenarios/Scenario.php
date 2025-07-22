<?php

namespace PrestaFlow\Library\Scenarios;

use PrestaFlow\Library\Traits\ImportPage;
use PrestaFlow\Library\Traits\Locale;
use PrestaFlow\Library\Traits\Version;

class Scenario
{
    use Locale;
    use Version;
    use ImportPage;

    public $globals = [];
    public $params = [];
    public $pages = [];

    public function __construct($testSuite, $params = [])
    {
        $this->globals = $testSuite->getGlobals();
        $this->params = [...$this->params, ...$params];

        if (isset($this->params['locale']) && is_string($this->params['locale'])) {
            $this->globals['LOCALE'] = $this->params['locale'];
        }
        if (isset($this->params['useIsoCode'])) {
            $this->globals['PREFIX_LOCALE'] = (bool) $this->params['useIsoCode'];
        }

        $locale = $this->globals['LOCALE'] ?? $testSuite->getLocale();
        $versions = $this->globals['PATCH_VERSION'] ?? $testSuite->getVersions();

        $this->setVersions(versions: $versions);
        $this->setLocale(locale: $locale);
        $this->steps($testSuite);
    }

    public function steps($testSuite)
    {
        return $this;
    }

    public function it(string $description, $steps)
    {
        $this->suites[$this->getSuite()]['tests'][] = [
            'title' => $description,
            'steps' => $steps
        ];

        return $this;
    }
}
