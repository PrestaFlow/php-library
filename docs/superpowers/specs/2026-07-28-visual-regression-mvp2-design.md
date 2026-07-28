# Visual regression — MVP2 (full loop)

**Date :** 2026-07-28
**Repos concernés :** `php-library`, `api`, `github-action`
**Scope :** premier cut end-to-end de la régression visuelle, du checkpoint côté test jusqu'au bouton d'approbation dans le rapport.

## Objectif

Fournir aux utilisateurs PrestaFlow une boucle complète de régression visuelle :

1. Un test capture un screenshot via `$page->visualCheckpoint('home')`.
2. Le CI compare contre une baseline stockée côté API PrestaFlow.
3. En cas de diff supérieur au seuil, le rapport de run affiche reference / actual / diff dans un viewer.
4. Un membre du projet promeut l'actual en nouvelle baseline via un bouton dans ce rapport.

## Décisions cadres

- **Autorité de la baseline :** API PrestaFlow (option B lors du brainstorming). Rationale : CI-agnostique, historique versionné, permissions natives, pas de dépendance à la rétention GHA.
- **Portée du MVP :** MVP2 (viewer + approve loop). Pas d'auto-promote au merge PR — le premier run sur la nouvelle branche récipiendaire se re-approuve manuellement.
- **Clé de baseline :** `(project, name, tag)` avec `tag` explicite ou `'auto'` (calculé depuis PS version + viewport + locale).
- **Scoping branche :** héritage type Percy/Chromatic. Une feature branch compare contre la baseline `main` si aucune baseline branch-scopée n'existe.
- **Site de la comparaison :** client-side dans `php-library` (Shape 1 du brainstorming). L'API est un store de baselines + journal de runs.
- **Comportement first-run :** auto-baseline en local (comportement existant de `visualCheckpoint()`), remontée en `missing_baseline` côté API pour approbation explicite.
- **Diff :** score `[0..1]` via `VisualComparator` existant, pass si `score >= threshold` (défaut 0.98). Inchangé.

## 1. Modèle de données (API)

### Table `visual_baselines`

| Colonne | Type | Notes |
|---|---|---|
| `id` | ulid | |
| `project_id` | fk projects | |
| `name` | string | Nom logique du checkpoint (`home`, `product-listing`…). |
| `tag` | string | Chaîne opaque résolue. `auto-v9-1280x720-fr` pour le mode auto, ou libre. |
| `branch` | string, nullable | `null` = baseline « main / défaut ». |
| `image_path` | string | Chemin storage du PNG (`storage/app/visual/baselines/<ulid>.png`). |
| `sha256` | char(64) | Empreinte du binaire (dedup / cache-key). |
| `width`, `height` | int | Métadonnées d'affichage. |
| `bytes` | int | Idem. |
| `promoted_from_run_id` | fk runs, nullable | Trace du run d'origine. |
| `promoted_by_user_id` | fk users, nullable | Audit. |
| `promoted_at` | timestamp | |
| `superseded_by_id` | fk visual_baselines, nullable | Historisation par chaînage. Baseline active = `superseded_by_id IS NULL`. |
| `created_at`, `updated_at` | timestamps | |

**Contrainte d'unicité :** partial unique index sur `(project_id, name, tag, branch)` WHERE `superseded_by_id IS NULL`.

### Table `visual_checkpoints`

| Colonne | Type | Notes |
|---|---|---|
| `id` | ulid | |
| `run_id` | fk runs | |
| `name` | string | |
| `tag` | string | |
| `branch` | string, nullable | Branche du run (source de vérité pour approve). |
| `status` | enum | `baseline`, `pass`, `fail`, `missing_baseline`. |
| `score` | float, nullable | Null pour `baseline` et `missing_baseline`. |
| `threshold` | float | Reprise du checkpoint. |
| `actual_path` | string, nullable | Chemin storage. Null pour `baseline` (l'actual EST la baseline). |
| `diff_path` | string, nullable | Null sauf pour `fail`. |
| `baseline_id_used` | fk visual_baselines, nullable | Quelle baseline a servi à la comparaison. |
| `created_at` | timestamp | |

### Résolution baseline → download

Pour chaque `(name, tag)` demandé par l'action, l'API renvoie en cascade :

1. `branch = <current-branch>` et `superseded_by_id IS NULL` → celle-ci.
2. Sinon `branch IS NULL` et `superseded_by_id IS NULL` → celle-ci.
3. Sinon rien → le lib fera auto-baseline en local, remontera en `missing_baseline`.

## 2. API surface

Toutes les routes ci-dessous sont ajoutées à `routes/ci.php`, protégées par `auth.project_token`.

```
GET  /ci/visual/baselines?branch=<b>          → manifeste JSON
GET  /ci/visual/baselines/{id}                → binaire PNG (streamé depuis storage)
POST /ci/visual/checkpoints/{id}/approve      → promeut l'actual → nouvelle baseline
```

**`GET /ci/visual/baselines`**

Réponse :
```json
{
  "baselines": [
    { "id": "01H...", "name": "home", "tag": "auto-v9-1280x720-fr", "sha256": "abcd...", "download_url": "/ci/visual/baselines/01H..." },
    { "id": "01H...", "name": "product-listing", "tag": "auto-v9-1280x720-fr", "sha256": "efgh...", "download_url": "/ci/visual/baselines/01H..." }
  ],
  "branch": "feature/x",
  "manifest_sha256": "..."
}
```

Le champ `manifest_sha256` est la clé de cache utilisée côté action.

**`POST /ci/visual/checkpoints/{id}/approve`**

- Auth : ability `visual:approve` sur le token (ou session utilisateur avec droit équivalent — cf. section Permissions).
- Transaction :
  1. Vérifier que le checkpoint est en `fail` ou `missing_baseline`.
  2. Nouvelle `VisualBaseline` créée depuis `actual_path`, avec `branch = checkpoint.branch`, `promoted_from_run_id = checkpoint.run_id`, `promoted_by_user_id = auth()->id()`.
  3. Ancienne baseline pour `(project, name, tag, branch)` — si existe — voit son `superseded_by_id` set à la nouvelle.
- Retour : la nouvelle `VisualBaseline` sérialisée.

### Ingest existant (`POST /ci/github-action/`) — étendu

Rétro-compat total. Deux nouvelles catégories via le champ `file[]` (nom conventionné) :

- `visual/actual/<name>--<tag>.png` → `VisualCheckpoint.actual_path`
- `visual/diff/<name>--<tag>.png` → `VisualCheckpoint.diff_path`

Le mapping `(name, tag) → checkpoint row` est fait via le nouveau bloc `visual: [...]` dans `results.json` (cf. section lib).

## 3. Changements `php-library`

### Extension `visualCheckpoint()`

Signature actuelle :
```php
public function visualCheckpoint(string $name, ?string $selector = null, float $threshold = 0.98, bool $fullPage = true): void
```

Nouvelle :
```php
public function visualCheckpoint(
    string $name,
    ?string $selector = null,
    float $threshold = 0.98,
    bool $fullPage = true,
    string $tag = 'auto'
): void
```

- `tag = 'auto'` → résolution automatique dans `VisualTag::resolve()` :
  - Concatène `v<majorVersion>-<width>x<height>-<locale>`.
  - Ex : `auto-v9-1280x720-fr`.
- `tag = '<libre>'` → utilisé tel quel, sans préfixe.

### Nommage fichier

Nouveaux noms dans `screens/actual/`, `screens/diff/`, `visual-baseline/` :
```
<name>--<tag>.png
```
Le double-tiret sépare le nom du tag. Le tag est filename-safe sur les trois OS majeurs : `^[a-z0-9._-]+$`. Les caractères `:` et `/` (interdits sous Windows / significatifs POSIX) sont remplacés par `-` et `_` respectivement dans les tags auto-dérivés, et rejetés (erreur explicite) dans les tags libres.

### `results.json` — nouveau bloc

Sous chaque `it()` :
```json
"visual": [
  {
    "name": "home",
    "tag": "auto-v9-1280x720-fr",
    "status": "fail",
    "score": 0.72,
    "threshold": 0.98,
    "actual_relpath": "prestaflow/screens/actual/home--auto-v9-1280x720-fr.png",
    "diff_relpath": "prestaflow/screens/diff/home--auto-v9-1280x720-fr.png"
  }
]
```

Déjà quasi produit par `TestsSuite::recordVisualResult()` — à formaliser en tableau typé et à s'assurer qu'il est sérialisé dans le JSON final.

### `visual-baseline/` (inchangé structurellement)

Toujours hors `baseDir()`, non purgé par `ExecuteSuite::handleDir()`. Fichiers pré-remplis par l'action depuis le manifeste API.

## 4. Changements `github-action`

### Nouvel input

```yaml
visual:
  description: "Enable visual regression round-trip with the API (download baselines before run, upload actual/diff after)."
  required: false
  default: 'true'
```

### Nouveau step (avant `runComposer`)

`src/visual/prepare.ts` :

1. Résoudre `branch = process.env.GITHUB_REF_NAME`.
2. `GET https://api.prestaflow.io/ci/visual/baselines?branch=<b>` avec `X-Api-Token`.
3. Restore GHA cache keyed sur `visual-baseline-<projectId>-<manifest_sha256>`. Hit → skip downloads.
4. Miss → pour chaque entrée, `GET download_url`, écrit `visual-baseline/<name>--<tag>.png`.
5. Save GHA cache.

Ce step est skippé si `visual: false` ou si l'API renvoie 404 / 501 (pas encore déployée — permet un rollout progressif côté server).

### Étendre le glob d'upload

Dans `src/upload/api.ts` :

```ts
const patterns = [
  '**/prestaflow/results.json',
  '**/prestaflow/screens/errors/*.png',
  '**/prestaflow/screens/actual/*.png',   // nouveau
  '**/prestaflow/screens/diff/*.png',      // nouveau
];
```

Nommage envoyé au form :
- `errors/foo.png` inchangé
- `actual/foo.png` → `visual/actual/foo.png`
- `diff/foo.png` → `visual/diff/foo.png`

Le controller Laravel range chaque fichier en fonction de son préfixe.

## 5. UI — rapport de run & viewer

### Section « Visual » dans la page de run

Sous la liste des tests, nouvelle section si `visual_checkpoints.exists()` pour ce run :

```
Visual checkpoints (3)
  [thumb] home                   auto-v9-…    ✓ Passed         0.99 / 0.98
  [thumb] product-listing        auto-v9-…    ✗ Failed         0.72 / 0.98    [Approve]
  [thumb] checkout               auto-v9-…    ⚠ Missing        — / 0.98       [Approve]
```

Clic sur une ligne → modal viewer.

### Modal viewer

Trois onglets internes :

- **Side-by-side** — trois colonnes : reference | actual | diff. Défaut. Reference grisée avec placeholder si `Missing baseline`.
- **Overlay** — reference et actual superposés, slider d'opacité 0..1.
- **Swipe** — barre verticale déplaçable ; à gauche reference, à droite actual.

Un seul composant Livewire (`ProjectVisualCheckpointModal`), 3 layouts CSS commutés en Alpine.

**Métadonnées affichées** : `name`, `tag`, `branch`, dimensions, score, threshold, sha256, `Baseline promoted by X on <date> from run #Y` si baseline non-null.

**Bouton Approve** (dans le modal aussi) : présent uniquement pour `fail` et `missing_baseline`, et uniquement si l'utilisateur courant a le droit d'approuver (cf. Permissions).

### Flow d'approbation

1. Clic **Approve** → confirmation modale : « Promouvoir cet actual en nouvelle baseline pour `(name, tag, branche=<b>)` ? Affectera les prochains runs de la branche `<b>`. »
2. Confirm → `POST /ci/visual/checkpoints/{id}/approve` (session Laravel, CSRF).
3. Réponse OK → pill du checkpoint passe à `Approved (new baseline)`, toast « Baseline updated », lien « Undo (7d) » en toast persistant.
4. Undo dans les 7 jours = revert `superseded_by_id` de l'ancienne baseline, delete de la nouvelle. Après 7 jours, plus d'undo (mais historique consultable).

### Page historique

`/projects/{id}/visual/baselines` — read-only :

- Liste des baselines actives (`superseded_by_id IS NULL`), filtre par branche.
- Clic → chaîne historique de la baseline (`v3 → v2 → v1`), qui a promu quand depuis quel run.

## 6. Permissions

- **`visual:read`** — nouvelle ability. Migration one-shot : ajoutée à tous les `project_tokens` existants avec `runs:write`. Autorise `GET /ci/visual/baselines*`.
- **`visual:approve`** — nouvelle ability, non-cochée par défaut. Doit être ajoutée explicitement lors de la création/édition d'un ProjectToken côté dashboard. Autorise `POST /ci/visual/checkpoints/{id}/approve` via token.
- **Session utilisateur** — le bouton **Approve** dans le rapport passe par la session Laravel, pas par un ProjectToken. Le droit d'approuver est porté par la relation `project_user` :
  - **Point à clarifier en début de plan :** les ProjectTokens sont-ils user-scopés ou project-scopés ? Si project-scopés, ajouter une colonne `can_approve_visual` sur la pivot `project_user`. Si user-scopés, l'ability du token est suffisante. À vérifier au moment d'écrire le plan.
- Le token utilisé côté CI (upload de runs) n'a que `runs:write` + `visual:read`. Il ne peut donc pas approuver — approbation manuelle exclusivement côté humain.

## 7. Édge cases & non-goals

**Édge cases traités**

- **Baseline supprimée** (via un futur endpoint / migration) : au prochain run, la lib retombe en auto-baseline local, checkpoint = `missing_baseline`, réapprobation requise.
- **Test renommé** : nouveau `(name, tag)` = nouveau `missing_baseline`. L'ancienne baseline reste stockée jusqu'à cleanup manuel (out of scope).
- **PS version change** (mode `auto`) : tag change → nouvelle clé → nouveau `missing_baseline`. Comportement voulu : un changement de version PS *doit* forcer une re-validation visuelle.
- **Rollout progressif** : action tolère 404/501 sur les endpoints `/ci/visual/*` (fallback = pas de round-trip). Permet de déployer l'action avant que l'API expose les routes.

**Hors-scope MVP2** (documentés pour éviter la scope creep)

- Ignore-regions (masques de zones dynamiques type dates, prix aléatoires).
- Reviewer distinct du promoteur (2-person approval).
- Webhook GitHub sur merge de PR pour auto-promouvoir les baselines branch-scopées vers `main`.
- Compression / dedup côté storage (deux baselines avec même sha256 → même blob).
- CLI `prestaflow visual approve <run-id> <checkpoint>` en local.
- Approbation en masse (« approve all failures in this run »).
- Rétention / purge automatique des baselines superseded.

## 8. Ordre d'implémentation (recommandé)

1. **API** — migrations + modèles + endpoint `POST /ci/github-action/` étendu (ingest visual/actual + visual/diff, écriture `visual_checkpoints`). Aucun changement UI.
2. **API** — endpoints `GET /ci/visual/baselines*`. Toujours pas d'UI.
3. **`php-library`** — tag support + bloc `visual` dans results.json. Peut être testé localement avec un mock d'API ou en no-op.
4. **`github-action`** — prepare step (download) + upload étendu. Testable via `github-action-smoke` en ajoutant un `visualCheckpoint()` au `BrowserSmoke`.
5. **API** — section « Visual » dans le rapport de run, viewer 3-modes, endpoint approve + permissions. Le vrai gros du travail UI.
6. **API** — page historique.

Cet ordre découpe naturellement en 6 lots livrables indépendamment ; chacun apporte de la valeur (le lot 1 permet déjà de voir les images dans le storage) et rien ne casse la retro-compat de l'action.
