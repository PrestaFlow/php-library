<?php

namespace PrestaFlow\Library\Utils;

trait Locale
{
    public string $locale;

    public function setLocale(string $locale)
    {
        $this->locale = $locale;
    }
}
