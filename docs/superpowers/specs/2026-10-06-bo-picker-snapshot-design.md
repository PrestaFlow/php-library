# Capture du sélecteur visuel en back-office (lib) : design

Date : 2026-10-06
Statut : validé, à implémenter

Partie lib du sélecteur visuel BO de l'app PrestaFlow. La spec complète, côté
app, est `docs/superpowers/specs/2026-10-06-visual-bo-picker-design.md` du dépôt
PrestaFlow/app.

## Contexte

L'app veut ouvrir, pour son sélecteur visuel, la même page BO qu'un run :
connexion employé, tableau de bord, entrée du menu, ou page de connexion
déconnectée. Elle veut ensuite capturer cette page (carte des éléments et image)
avec `PageSnapshot`.

Aujourd'hui, `PageSnapshot::take()` ne sait que naviguer vers une URL. La
connexion et le menu n'existent qu'à l'intérieur du run de `VisualTestsSuite`
(`ensureBackOfficeLogin`, `goToMenu`).

## Design

### `PageSnapshot::captureCurrent()`

- Nouvelle méthode publique :
  `captureCurrent(\HeadlessChromium\Page $page, int $timeoutMs = 15000): SnapshotResult`.
- Elle contient tout ce que `take()` fait après la navigation :
  - attente de stabilité ;
  - `PageScripts::SETTLE_ANIMATIONS` ;
  - statut HTTP ;
  - dimensions du document (hauteur plafonnée) ;
  - `PageScripts::ELEMENT_MAP` ;
  - capture pleine page en JPEG.
- `take()` crée son navigateur, navigue, puis appelle `captureCurrent()`. Son
  comportement et son résultat ne changent pas : les tests existants le prouvent.

### `VisualTestsSuite::openBackOfficeCheckpoint()` et `closeBackOfficeSession()`

- `openBackOfficeCheckpoint(array $checkpoint, int $loginTimeoutMs = 25000, int $menuTimeoutMs = 15000): void`, publique.
  - Elle ouvre, dans le navigateur courant de la suite (`TestsSuite::getPage()`),
    la page d'un checkpoint BO, **avec le même code que le run** :
    - le checkpoint est normalisé par `normalize()` ;
    - `auth: false` : racine du BO, et l'erreur « session déjà ouverte » si le
      formulaire de connexion est absent ;
    - sinon : `ensureBackOfficeLogin()`, puis `goToPage('index')` et, si `menu` est
      défini, `goToMenu($menu)`.
  - Les plafonds bornent l'attente de l'issue de la connexion (`waitForLoginOutcome`)
    et la navigation du menu. Un dépassement lève une exception de délai dédiée,
    au message lisible.
  - Les erreurs sont celles du run (identifiants refusés avec le message du
    formulaire, page inattendue avec son chemin, entrée de menu introuvable). Elles
    sont relevées comme `\RuntimeException`, avec le message déjà formulé par le
    run.
  - Elle exige `area === 'bo'`, sinon `\LogicException`.
- `closeBackOfficeSession(): void`, publique : déconnexion par le lien
  `#header_logout` de la page courante (`BackOffice\Login\Page::logout()`) si une
  session est ouverte. Elle ne lève jamais : un échec de déconnexion ne doit pas
  faire échouer la capture.
- Aucune autre méthode du run ne change. `ensureBackOfficeLogin` et `goToMenu`
  restent la seule implémentation de la connexion et du menu.

### Tests de la lib

- `captureCurrent` : `take()` donne le même `SnapshotResult` qu'avant. On le vérifie
  avec les doubles de `PageSnapshotTest`, en appelant `captureCurrent` sur une page
  factice.
- `openBackOfficeCheckpoint`, avec des pages factices (comme
  `VisualTestsSuiteBackOfficeTest`) :
  - ordre connexion → tableau de bord → menu ;
  - checkpoint `auth: false` (pas de connexion) ;
  - identifiants refusés ;
  - menu introuvable ;
  - délai dépassé ;
  - refus hors BO.
- `closeBackOfficeSession` : appelle `logout()` quand une session est ouverte, ne
  lève jamais.

### Livraison de la lib

- PR vers `dev`, merge en commit de merge.
- Release `dev` → `main` en commit de merge.
- Bump dans l'app : seul `composer.lock` change. Voir le flux de release de la lib.

