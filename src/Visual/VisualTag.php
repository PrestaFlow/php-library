<?php

namespace PrestaFlow\Library\Visual;

/**
 * Résolution du tag de checkpoint visuel utilisé dans le nommage des fichiers
 * (`<name>--<tag>.png`) et dans la clé de baseline `(project, name, tag)`
 * côté API.
 *
 * - `$tag !== 'auto'` : utilisé tel quel s'il est filename-safe
 *   (`^[a-z0-9._-]+$`, insensible à la casse). Sinon, exception explicite —
 *   les tags libres ne sont jamais réécrits silencieusement.
 * - `$tag === 'auto'` : `auto-v{major}-{w}x{h}-{locale}`, dérivé de la version
 *   PS majeure (`8`, `9`, `1.7`…), des dimensions du viewport et de la locale.
 *   Chaque segment manquant est remplacé par un jeton filename-safe (jamais
 *   omis, nombre de segments stable) : major `x` (→ `vx`), dimension `0`,
 *   locale `xx`. Le résultat matche toujours SAFE_PATTERN (jamais de `?`).
 */
final class VisualTag
{
    private const SAFE_PATTERN = '/^[a-z0-9._-]+$/i';

    public const UNKNOWN_MAJOR = 'x';
    public const UNKNOWN_DIMENSION = '0';
    public const UNKNOWN_LOCALE = 'xx';

    /**
     * Version majeure PrestaShop depuis une version complète ou partielle :
     * `8.1.0` → `8`, `1.7.8.11` → `1.7`, `1.6` → `1.6`. `null` si inexploitable.
     */
    public static function majorFromVersion(?string $version): ?string
    {
        $version = trim((string) $version);
        if (preg_match('/^1\.(\d+)(\.|$)/', $version, $m) === 1) {
            return '1.'.$m[1];
        }
        if (preg_match('/^(\d+)(\.|$)/', $version, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    public static function resolve(
        string $tag,
        int|string|null $majorVersion,
        ?int $viewportWidth,
        ?int $viewportHeight,
        ?string $locale
    ): string {
        if ($tag !== 'auto') {
            if (preg_match(self::SAFE_PATTERN, $tag) !== 1) {
                throw new \InvalidArgumentException('visual tag must match [a-z0-9._-]+');
            }

            return $tag;
        }

        $major = $majorVersion !== null && preg_match('/^\d+(\.\d+)?$/', (string) $majorVersion) === 1
            ? (string) $majorVersion
            : self::UNKNOWN_MAJOR;
        $width = $viewportWidth !== null ? (string) $viewportWidth : self::UNKNOWN_DIMENSION;
        $height = $viewportHeight !== null ? (string) $viewportHeight : self::UNKNOWN_DIMENSION;
        $localePart = $locale !== null && preg_match(self::SAFE_PATTERN, $locale) === 1 ? $locale : self::UNKNOWN_LOCALE;

        return "auto-v{$major}-{$width}x{$height}-{$localePart}";
    }
}
