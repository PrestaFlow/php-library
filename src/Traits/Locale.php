<?php

namespace PrestaFlow\Library\Traits;

trait Locale
{
    protected static $localeInit = null;
    protected static string $locale = 'en';

    public function localeIsInit(): mixed
    {
        return self::$localeInit;
    }

    public function initLocale(string $locale): mixed
    {
        $this->setLocale($locale);
        self::$localeInit = true;

        return self::$localeInit;
    }

    public function getLocale(): string
    {
        return self::$locale;
    }

    public function setLocale(string $locale)
    {
        self::$locale = $locale;
    }
}
