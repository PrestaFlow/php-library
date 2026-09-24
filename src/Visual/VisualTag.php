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
 * - `$tag === 'auto'` : dérivé de la version PS majeure, des dimensions du
 *   viewport et de la locale. Chaque segment manquant est représenté par un
 *   `?` littéral (jamais omis), pour garder un nombre de segments stable.
 */
final class VisualTag
{
    private const SAFE_PATTERN = '/^[a-z0-9._-]+$/i';

    public static function resolve(
        string $tag,
        ?int $majorVersion,
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

        $major = $majorVersion !== null ? (string) $majorVersion : '?';
        $width = $viewportWidth !== null ? (string) $viewportWidth : '?';
        $height = $viewportHeight !== null ? (string) $viewportHeight : '?';
        $localePart = $locale !== null && $locale !== '' ? $locale : '?';

        return "auto-v{$major}-{$width}x{$height}-{$localePart}";
    }
}
