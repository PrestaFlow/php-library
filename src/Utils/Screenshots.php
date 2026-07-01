<?php

namespace PrestaFlow\Library\Utils;

final class Screenshots
{
    public const ERRORS_SUBPATH = 'screens/errors';
    public const RELATIVE_DIR = 'prestaflow/screens/errors';

    public static function errorsDir(bool $create = false): string
    {
        if (function_exists('storage_path')) {
            $dir = rtrim(storage_path(), '/') . '/' . self::ERRORS_SUBPATH;
        } else {
            $dir = getcwd() . '/' . self::RELATIVE_DIR;
        }

        if ($create && !is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public static function errorPath(string $filename, bool $create = false): string
    {
        return self::errorsDir($create) . '/' . $filename;
    }

    public static function relativeErrorPath(string $filename): string
    {
        return self::RELATIVE_DIR . '/' . $filename;
    }

    public static function captureDelay(): int
    {
        $delay = (int) ($_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] ?? 3);

        return $delay < 0 ? 0 : $delay;
    }
}
