# URL de la boutique et du back-office dans le fichier de suite visuelle : design (lib)

Date : 2026-10-07
Statut : validé, à implémenter

Partie lib de la spec de l'app (`docs/superpowers/specs/2026-10-07-visual-suite-urls-design.md`
du dépôt `PrestaFlow/app`), recopiée ici au départ de la branche `feat/suite-urls`.

## Problème

L'URL de la boutique et l'URL du back-office d'une suite visuelle viennent des globals
(`PRESTAFLOW_FO_URL` / `PRESTAFLOW_BO_URL` en CLI, ou celles que l'app passe au lancement).
L'éditeur de l'app permet de les choisir par suite, mais le fichier PHP n'en garde pas trace :
un run en CI avec la lib seule ignore l'URL choisie.

## Décisions

- `VisualTestsSuite` gagne :
  - `protected ?string $shopUrl = null;`
  - `protected ?string $backOfficeUrl = null;`
- Au lancement de la suite (avant le premier checkpoint, et avant l'import des pages, qui copient
  les globals), une propriété chaîne non vide remplace l'URL correspondante des globals
  (`FO.URL`, `BO.URL`). `null`, vide ou blanche : URL reçue.
- URL BO relative (ex. `admin123/`, sans schéma) : complétée par l'URL FO effective (propriété,
  sinon globals) avec la règle de l'app (`App\Support\BackOfficeUrl::resolve`) : URL FO sans
  requête ni fragment, puis le chemin relatif ; `//hôte/admin` prend le schéma de l'URL FO ;
  toujours terminée par « / ». Même règle qu'un `PRESTAFLOW_BO_URL` relatif.
- Refusées au lancement (`InvalidArgumentException`), avec un message qui nomme la propriété mais
  jamais la valeur :
  - identifiants dans l'URL (`scheme://user:pass@…`, `//user@…`) : l'authentification HTTP passe
    par `PRESTAFLOW_BASIC_USER` / `PRESTAFLOW_BASIC_PASS` ;
  - schéma autre que `http` / `https` (FO sans schéma compris) ;
  - BO relative sans URL FO pour la compléter.
- Les suites qui ne déclarent pas ces propriétés gardent exactement leur comportement.

## API

- `VisualTestsSuite::applySuiteUrls(): void` : publique, idempotente ; appelée par `init()` et
  `openBackOfficeCheckpoint()` (sélecteur visuel de l'app).
- `VisualTestsSuite::resolveBackOfficeUrl(string $backOffice, string $frontOffice): string` :
  statique publique.

## Tests

`tests/Unit/Visual/VisualTestsSuiteUrlsTest.php`, sans Chrome :

- suite sans propriété, propriétés vides : globals inchangées ;
- `$shopUrl` prioritaire, visible des pages à leur import, `resolveUrl()` la suit ;
- `$backOfficeUrl` absolue prioritaire ; relative complétée par l'URL FO du fichier, sinon des
  globals ; relative au protocole ;
- BO relative sans aucune URL FO refusée ;
- idempotence ;
- `user:pass@` et schéma non `http(s)` refusés sans afficher la valeur ;
- `openBackOfficeCheckpoint()` applique les URL avant d'importer les pages.

## Livraison

PR vers `dev`, puis release `dev` → `main` en commits de merge, sur accord. L'app fait ensuite
le bump (`composer.lock` seul).
