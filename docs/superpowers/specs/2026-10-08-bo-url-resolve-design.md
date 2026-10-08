# Une seule règle de résolution des URL du back-office : design

Date : 2026-10-08
Statut : validé, à implémenter

Fait suite à `2026-10-07-suite-urls-design.md` (PR #123, release #124).

## Problème

La lib complète une URL du back-office relative de deux façons :

- **`TestsSuite::loadGlobals()`** (toutes les suites, CLI et CI) colle `PRESTAFLOW_BO_URL`, ou le défaut `admin-dev/`, derrière `PRESTAFLOW_FO_URL`. Toute valeur qui ne commence pas par `http://` ou `https://` en minuscules est prise pour un chemin.
- **`VisualTestsSuite::resolveBackOfficeUrl()`** (propriété `$backOfficeUrl`, puis `$shopUrl` seule) applique la règle de l'app, `App\Support\BackOfficeUrl::resolve()`.

Le même `PRESTAFLOW_BO_URL` peut donc viser un autre back-office selon le chemin :

| Valeur | `loadGlobals()` aujourd'hui | Règle de l'app |
|---|---|---|
| `/admin` (FO `https://x/`) | `https://x//admin/` | `https://x/admin/` |
| `admin-dev/` (FO `https://x/?lang=2`) | `https://x/?lang=2/admin-dev/` | `https://x/admin-dev/` |
| `//autre/admin` | `https://x///autre/admin/` | `https://autre/admin/` |
| `HTTPS://x/admin` | `https://x/HTTPS://x/admin/` | `HTTPS://x/admin/` |

## Décisions

| Sujet | Décision |
|---|---|
| Portée | Une seule règle de **résolution** pour `loadGlobals()` et les suites visuelles. |
| Refus | Aucun nouveau refus pour les variables d'environnement : une valeur exotique, même avec des identifiants, est résolue sans erreur. Les refus restent propres aux propriétés `$shopUrl` / `$backOfficeUrl` du fichier de suite. |
| Emplacement | Nouvel utilitaire `PrestaFlow\Library\Utils\BackOfficeUrl`, à côté de `Env`. |
| Compatibilité | `VisualTestsSuite::resolveBackOfficeUrl()` reste publique, avec la même signature, et délègue à l'utilitaire. |

## Design

### `PrestaFlow\Library\Utils\BackOfficeUrl`

```php
final class BackOfficeUrl
{
    public static function resolve(string $backOffice, string $frontOffice): string;
    public static function isRelative(string $backOffice): bool;
}
```

- `resolve()` reprend sans changement le corps actuel de `VisualTestsSuite::resolveBackOfficeUrl()` :
  - les deux valeurs sont nettoyées des espaces en bordure ;
  - une URL absolue `http(s)`, schéma insensible à la casse, est gardée telle quelle ;
  - une URL `//hôte/…` prend le schéma de l'URL FO ;
  - un autre chemin s'ajoute à l'URL FO, privée de sa requête et de son fragment, en gardant son chemin, avec un seul `/` entre les deux ;
  - le résultat se termine toujours par `/`.
- `isRelative()` renvoie vrai si la valeur dépend de l'URL FO, autrement dit si elle n'est ni absolue `http(s)` ni `//hôte`. Une valeur vide après nettoyage n'est pas relative.

### `TestsSuite::loadGlobals()`

- `PRESTAFLOW_FO_URL` ne change pas : il reçoit seulement un `/` final.
- L'URL BO vaut `PRESTAFLOW_BO_URL`, ou `admin-dev/` si la variable est absente. Elle passe par `BackOfficeUrl::resolve($bo, $frontOfficeUrl)`.
- `$backOfficeRelative` vaut la valeur brute nettoyée quand `BackOfficeUrl::isRelative()` est vrai, sinon `null`. Avec `$shopUrl` seule, un `//hôte` ou une URL absolue ne suit donc plus l'URL FO du fichier. C'est cohérent : leur hôte ne dépend pas de la FO.
- `PRESTAFLOW_BO_URL` défini mais vide : l'URL FO elle-même, comme aujourd'hui ; `$backOfficeRelative` vaut `''`, pour que la BO suive une `$shopUrl` du fichier (comportement de `dev` conservé).

### `VisualTestsSuite`

- `resolveBackOfficeUrl()` devient `return BackOfficeUrl::resolve($backOffice, $frontOffice);`, avec un docblock qui renvoie à l'utilitaire.
- `applySuiteUrls()`, `assertSuiteUrl()` et `isSafeRelativePath()` ne changent pas.

## Tests

- **Unitaires `BackOfficeUrl`** :
  - le jeu de cas de `resolve()` aujourd'hui dans `VisualTestsSuiteUrlsTest` (13 cas repris de `BackOfficeUrlTest` de l'app) est déplacé dans `tests/Unit/Utils/BackOfficeUrlTest.php` ;
  - `isRelative()` est vrai pour `admin/`, `/admin` et `admin-dev/`, faux pour `http://x/a`, `HTTPS://x/a`, `//x/a` et `''`.
- **`loadGlobals()`**, via des variables d'environnement, sans Chrome : chaque ligne du tableau « Problème », le défaut `admin-dev/`, une URL absolue gardée, et `$backOfficeRelative` (relatif, `//hôte`, absolu, défaut).
- `VisualTestsSuiteUrlsTest` reste vert : `resolveBackOfficeUrl()` délègue, et le comportement de `$shopUrl` / `$backOfficeUrl` ne change pas.
- Suite complète verte en PHP 8.4 et 8.1.

## Documentation

README : ajouter sous la ligne qui mentionne `PRESTAFLOW_BO_URL` une phrase qui renvoie à la règle commune. Exemple : « A relative `PRESTAFLOW_BO_URL` follows the same rule as `$backOfficeUrl` below. » La règle elle-même reste décrite une seule fois, dans la section « Shop and back-office URLs in the suite file ».

## Livraison

1. PR vers `dev`, puis release `dev` → `main`, en commits de merge, sur accord de l'utilisateur.
2. App : bump de `prestaflow/php-library`, `composer.lock` seul. Aucun autre changement dans l'app.

## Hors périmètre

- Valider ou refuser les variables d'environnement.
- `PRESTAFLOW_FO_URL` : requête, fragment ou schéma.
- `App\Support\BackOfficeUrl` côté app, qui reste la référence de la règle.
