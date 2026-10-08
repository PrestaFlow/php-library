<?php

namespace PrestaFlow\Library\Utils;

/**
 * Règle unique de l'URL du back-office, la même que App\Support\BackOfficeUrl::resolve()
 * dans l'app PrestaFlow. Sert à loadGlobals() (PRESTAFLOW_BO_URL) et aux suites
 * visuelles ($backOfficeUrl). Ne refuse rien : la validation reste à l'appelant.
 */
final class BackOfficeUrl
{
    /**
     * URL BO effective :
     * - absolue http(s) (casse indifférente) : gardée telle quelle ;
     * - relative au protocole (`//hôte/admin`) : schéma de l'URL FO ;
     * - relative (`admin123/`, `/admin123`) : URL FO sans requête ni fragment,
     *   chemin FO gardé, puis le chemin, avec un seul « / » entre les deux.
     * Toujours terminée par « / ».
     */
    public static function resolve(string $backOffice, string $frontOffice): string
    {
        $backOffice = trim($backOffice);
        $frontOffice = trim($frontOffice);
        if (str_starts_with($backOffice, '//')) {
            if (preg_match('#^(https?)://#i', $frontOffice, $m) === 1) {
                $backOffice = strtolower($m[1]).':'.$backOffice;
            }
        } elseif (preg_match('#^https?://#i', $backOffice) !== 1) {
            $frontOffice = (string) preg_replace('/[?#].*\z/s', '', $frontOffice);
            $backOffice = rtrim($frontOffice, '/').'/'.ltrim($backOffice, '/');
        }

        return str_ends_with($backOffice, '/') ? $backOffice : $backOffice.'/';
    }

    /** Vrai si l'URL BO dépend de l'URL FO : ni absolue http(s), ni `//hôte`, ni vide. */
    public static function isRelative(string $backOffice): bool
    {
        $backOffice = trim($backOffice);

        return $backOffice !== ''
            && !str_starts_with($backOffice, '//')
            && preg_match('#^https?://#i', $backOffice) !== 1;
    }
}
