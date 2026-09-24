<?php

namespace PrestaFlow\Library\Utils;

/**
 * Read process env vars in a way that survives PHP's variables_order setting.
 *
 * The library historically read env vars via $_ENV['KEY'], relying on phpdotenv
 * to populate that superglobal from .env / .env.local files. When PHP is built
 * with a variables_order that omits 'E' (the common CI case), plain process env
 * vars set by the shell — e.g. GitHub Actions' env: block, or `KEY=v php ...`
 * on a dev machine — never make it into $_ENV, so those callsites silently
 * fell back to defaults.
 *
 * Env::get() looks at $_ENV first (preserving the dotenv-populated,
 * normalized values that TestsSuite writes at load time) and falls back to
 * getenv() (which always sees the process environment).
 */
class Env
{
    /**
     * Read an env var: $_ENV first, then getenv(), then the default.
     *
     * Semantics match the previous `$_ENV[$key] ?? $default` idiom:
     *   - key present in $_ENV → returns that value verbatim (even empty string)
     *   - key absent from $_ENV but present in process env → returns that
     *   - key absent from both → returns $default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }

    /**
     * True if the key exists in $_ENV or in the process environment.
     */
    public static function has(string $key): bool
    {
        if (array_key_exists($key, $_ENV)) {
            return true;
        }
        return getenv($key) !== false;
    }
}
