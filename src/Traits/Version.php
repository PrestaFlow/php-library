<?php

namespace PrestaFlow\Library\Traits;

trait Version
{
    public function getVersion()
    {
        if (isset($this->globals['PS_VERSION'])) {
            if (version_compare($this->globals['PS_VERSION'], '9.0.0', '>=')) {
                return 'v9';
            } else if (version_compare($this->globals['PS_VERSION'], '8.0.0', '>=')) {
                return 'v8';
            } else if (version_compare($this->globals['PS_VERSION'], '1.7.0', '>=')) {
                return 'v1.7';
            } else if (version_compare($this->globals['PS_VERSION'], '1.6.0', '>=')) {
                return 'v1.6';
            }
        }

        return 'v8';
    }
}
