# Sélecteur visuel : environnement, connexion au plus juste, nettoyage : design

Date : 2026-10-07
Statut : validé, à implémenter

Suite de `2026-10-06-bo-picker-snapshot-design.md` (PR #119). La partie app est décrite
dans `app/docs/superpowers/specs/2026-10-07-visual-picker-async-design.md`.

## Problèmes

1. **Environnement absent du sélecteur.** Le run applique dans `TestsSuite::before()` la
   Basic Auth (`PRESTAFLOW_BASIC_USER` / `PRESTAFLOW_BASIC_PASS`), les en-têtes
   `PRESTAFLOW_EXTRA_HEADERS` et les cookies `PRESTAFLOW_COOKIES`. Le sélecteur ne passe
   pas par `before()` :
   - `PageSnapshot::take()` crée un navigateur dédié sans rien appliquer ;
   - la capture BO de l'app lance le navigateur via `getBrowser(force: true)`.

   Une boutique de préproduction protégée ne peut donc pas être capturée.
2. **`TestsSuite::$extraHttpHeaders` n'est jamais vidé.** Dans un worker persistant, les
   en-têtes d'un job restent posés pour le suivant.
3. **Plafond de connexion trop prudent.** `openBackOfficeCheckpoint()` réserve 10 s fixes
   (`PAGE_RELOAD_MS`) pour le rechargement qui suit l'envoi du formulaire, même quand il
   dure 1 s. Un premier tableau de bord lent finit en délai alors qu'il restait du budget.
4. **Nettoyage.**
   - `redactUrls()` ne masque pas les jetons doublement encodés (`%2526token%253D…`).
   - Un docblock orphelin précède `DEFAULT_MAX_DIFF_PIXELS` au lieu de `UNSTABLE_WARNING`.
   - Le README ne documente pas l'API du sélecteur.

## 1. `TestsSuite::applyEnvironment()` et `clearEnvironment()`

- Nouvelle méthode publique statique
  `applyEnvironment(object $browser, object $page, ?string $defaultUrl = null): void`
  (typée `object` pour les doubles de test ; l'app passe un vrai `Browser` et une vraie `Page`).
  Elle regroupe, dans le même ordre et avec le même effet, ce que font aujourd'hui
  `presetBasicAuth()`, `presetExtraHeadersFromEnv()` et `presetEnvCookies()` :
  1. Basic Auth → `TestsSuite::$extraHttpHeaders['Authorization']` ;
  2. `PRESTAFLOW_EXTRA_HEADERS` fusionnés par-dessus ;
  3. en-têtes posés sur la connexion (`setConnectionHttpHeaders`) et sur la page
     (`Network.enable` puis `setExtraHTTPHeaders`) ;
  4. cookies `PRESTAFLOW_COOKIES` posés sur la page.
- Cookie sans `domain` : posé avant toute navigation, la page est sur `about:blank`, dont
  chrome-php tirerait un domaine nul (CDP refuse le cookie, perdu sans bruit). Le domaine
  vient alors du host de son `url`, sinon de `$defaultUrl` ; une `url` inexploitable (sans
  schéma, malformée, non chaîne) passe à `$defaultUrl`. Un `domain` explicite est gardé.
  Poser `domain` fait un cookie de domaine (sous-domaines compris), non host-only : chrome-php
  l'impose de toute façon.
- Côté run, `before()` ne passe pas de `$defaultUrl` ; seule la déduction depuis l'`url` du
  cookie s'y ajoute (effet voulu : un cookie `{name, value, url}` n'est plus perdu).
- Les trois méthodes protégées restent, en délégant, pour ne casser aucune suite qui les
  surcharge. `before()` garde exactement le même comportement : les tests existants du run
  le prouvent.
- Nouvelle méthode publique statique `clearEnvironment(): void` : vide
  `TestsSuite::$extraHttpHeaders`. Elle n'est pas appelée par le run (aucun changement de
  comportement) ; l'app l'appelle en libérant le navigateur du sélecteur.
- Les valeurs viennent toujours de l'environnement (`Env::get`), jamais d'un paramètre.
  Elles ne sont jamais écrites dans un message ni un journal.

## 2. `PageSnapshot::take()` applique l'environnement

- `take()` appelle `TestsSuite::applyEnvironment($browser, $page, $url)` entre `createPage()`
  et `navigate()` : un cookie sans domaine prend le host de l'URL capturée.
- `take()` part d'en-têtes vides : il sauvegarde `TestsSuite::$extraHttpHeaders`, le vide
  avant `createPage()` et le rétablit dans son `finally`, même en exception. Une capture
  n'hérite donc pas des en-têtes d'un run en cours dans le même worker, et n'y laisse ni
  `Authorization` ni en-têtes extra.
- Sans variable d'environnement, rien n'est posé : le résultat est identique à aujourd'hui.
- `captureCurrent()` ne change pas : la page appartient à l'appelant.

## 3. Échéance de l'issue de connexion

- Nouvelle propriété `Login\Page::$loginOutcomeDeadline` (`?int`, ms monotones comme
  `hrtime`, `null` par défaut).
- Dans `login()`, après `waitForPageReload()` : si l'échéance est posée, le plafond de
  `waitForLoginOutcome()` vaut `max(1000, échéance − maintenant)` ; sinon
  `$loginOutcomeTimeout`, comme aujourd'hui. Le run ne pose jamais d'échéance.
- L'horloge de `Login\Page` est une méthode protégée `nowMs()`, surchargeable par les tests.
- `openBackOfficeCheckpoint()` :
  - pose `loginOutcomeDeadline = min(début + $loginTimeoutMs, échéance globale) − LOGIN_CHECK_MS`
    juste avant l'envoi (closure existante `$boBeforeLoginSubmit`) ;
  - n'exige plus que `LOGIN_CHECK_MS + MIN_STEP_MS` restants pour envoyer le formulaire
    (au lieu de `PAGE_RELOAD_MS + …`) ;
  - remet `loginOutcomeDeadline` à sa valeur d'avant dans `capBackOfficeWaits()`.
- `PAGE_RELOAD_MS` reste documenté comme le plafond fixe du rechargement ; il ne sert plus
  au calcul. Le docblock du budget et le pire cas sont mis à jour.

## 4. Nettoyage

- `redactUrls()` : les formes doublement encodées sont masquées
  (`%2526token%253D…`, `%253Ftoken%253D…`, `_token` compris). Les cas existants ne changent
  pas.
- Le docblock « Avertissement posé sur le test quand waitForStable() expire… » est replacé
  au-dessus de `UNSTABLE_WARNING`.
- README, nouvelle section anglaise **« Visual picker API »** sous « Visual regression runs » :
  - séquence `openBackOfficeCheckpoint()` → `PageSnapshot::captureCurrent(TestsSuite::getPage())`
    → `closeBackOfficeSession()` → `resetBrowser()` ;
  - `take()` pour une page FO, avec l'environnement appliqué ;
  - exceptions : `BackOfficeTimeoutException` (à attraper avant `\RuntimeException`),
    `\LogicException`, `\InvalidArgumentException` ;
  - `getPrevious()` peut contenir l'URL brute : ne jamais l'afficher ;
  - `applyEnvironment()` / `clearEnvironment()`.

## Tests

Aucun test ne lance Chrome.

- `applyEnvironment` : avec des doubles de navigateur et de page, ordre et contenu des
  en-têtes (Basic Auth puis en-têtes extra), cookies posés, rien sans variable ; `before()`
  passe par elle.
- `clearEnvironment` vide `$extraHttpHeaders`.
- `take()` appelle `applyEnvironment` avant `navigate` (doubles de `PageSnapshotTest`).
- `login()` : avec échéance, le plafond reçu dépend du temps restant après le rechargement
  (horloge factice) ; sans échéance, 60 000 comme aujourd'hui.
- `openBackOfficeCheckpoint` : un rechargement court laisse le reste du budget à l'issue ;
  le formulaire est envoyé dès que `LOGIN_CHECK_MS + MIN_STEP_MS` restent ; l'échéance est
  rétablie après l'appel, même en erreur.
- `redactUrls` : formes doublement encodées masquées, cas existants inchangés.

## Livraison

- PR vers `dev`, merge en commit de merge ; release `dev` → `main` en commit de merge.
- Bump dans l'app : seul `composer.lock` change.
