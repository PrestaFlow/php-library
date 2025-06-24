<?php

namespace PrestaFlow\Library\Traits;

trait Locale
{
    public static string $locale = 'en';

    public function getLocale(): string
    {
        return self::$locale;
    }

    public function setLocale(string $locale)
    {
        self::$locale = $locale;
    }
}
