<?php

namespace PrestaFlow\Library\Utils;

final class Screenshots
{
    public const ERRORS_SUBPATH = 'screens/errors';
    public const RELATIVE_DIR = 'prestaflow/screens/errors';

    public const REFERENCES_SUBPATH = 'screens/references';
    public const ACTUAL_SUBPATH = 'screens/actual';
    public const DIFF_SUBPATH = 'screens/diff';

    private static function baseDir(): string
    {
        if (function_exists('storage_path')) {
            return rtrim(storage_path(), '/');
        }

        return getcwd() . '/prestaflow';
    }

    public static function errorsDir(bool $create = false): string
    {
        $dir = self::baseDir() . '/' . self::ERRORS_SUBPATH;

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

    public static function referencePath(string $fileName, bool $create = false): string
    {
        return self::visualPath(self::REFERENCES_SUBPATH, $fileName, $create);
    }

    public static function actualPath(string $fileName, bool $create = false): string
    {
        return self::visualPath(self::ACTUAL_SUBPATH, $fileName, $create);
    }

    public static function diffPath(string $fileName, bool $create = false): string
    {
        return self::visualPath(self::DIFF_SUBPATH, $fileName, $create);
    }

    public static function relativeVisualPath(string $kind, string $fileName): string
    {
        return 'prestaflow/screens/' . $kind . '/' . $fileName;
    }

    private static function visualPath(string $subpath, string $fileName, bool $create): string
    {
        $full = self::baseDir() . '/' . $subpath . '/' . $fileName;

        if ($create && !is_dir(dirname($full))) {
            mkdir(dirname($full), 0777, true);
        }

        return $full;
    }

    public static function captureDelay(): int
    {
        $delay = (int) ($_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] ?? 3);

        return $delay < 0 ? 0 : $delay;
    }
}
