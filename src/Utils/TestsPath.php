<?php

namespace PrestaFlow\Library\Utils;

/**
 * Dossier Tests/ du projet qui consomme la librairie : Selectors, Messages,
 * Translations, Urls et Themes y sont lus.
 *
 * Ordre : le global TESTS_PATH (posé par l'appli PrestaFlow pour le projet de
 * la suite), puis la variable PRESTAFLOW_TESTS_PATH (CI, ligne de commande),
 * puis le chemin historique, relatif à vendor/ : la racine du projet.
 */
final class TestsPath
{
    public const ENV_VAR = 'PRESTAFLOW_TESTS_PATH';

    public const GLOBAL_KEY = 'TESTS_PATH';

    public static function root(array $globals = []): string
    {
        $configured = $globals[self::GLOBAL_KEY] ?? null;
        if (!is_string($configured) || $configured === '') {
            $configured = Env::get(self::ENV_VAR);
        }
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/\\');
        }

        return self::defaultRoot();
    }

    /** <projet>/vendor/prestaflow/php-library/src/Utils/../../../../../Tests : le dossier d'avant. */
    public static function defaultRoot(): string
    {
        return __DIR__ . '/../../../../../Tests';
    }

    /** Dossier d'un type de fichier, avec « / » final. */
    public static function dir(string $kind, array $globals = []): string
    {
        return self::root($globals) . '/' . $kind . '/';
    }

    /** Catalogue JSON ; [] si le fichier manque ou n'est pas un objet JSON. */
    public static function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
