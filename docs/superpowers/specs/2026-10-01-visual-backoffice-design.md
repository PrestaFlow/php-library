# Régression visuelle du back-office : design

Date : 2026-10-01
Statut : implémenté (branche feat/visual-backoffice)

## Objectif

Le workflow `visual.yml` ne couvre que le front-office. L'objectif est de
capturer aussi le back-office de PrestaShop, sur les trois versions déjà
lancées en CI.

## Décisions

| Sujet | Décision |
|---|---|
| Pages | Connexion, tableau de bord, produits, commandes, clients, modules. |
| Versions | 1.7.8.11, 8.2.8 et 9.2.0. Le back-office ne dépend pas du thème : une seule boutique par version. |
| Langue, appareil | Anglais, desktop seulement. |
| Livraison | Lib seule : `VisualTestsSuite`, une suite `BackOffice`, le workflow. L'app n'est pas modifiée. |

## Constats (relevé du 2026-10-01 sur 1.7.8.11 et 9.2.0 locales)

- **Jetons.** Toute URL du back-office porte un jeton (`token=` ou
  `_token=`). Sans lui, les deux versions renvoient vers « Invalid token »
  (`security/compromised`). Un checkpoint ne peut donc pas viser un `path`
  fixe.
- **Menu latéral.** Le lien d'une entrée `#subtab-*` contient le jeton :
  - `document.querySelector(sel+' a') || document.querySelector(sel)`
    donne une URL valide sur les deux versions ;
  - c'est ce que fait déjà `BackOfficePage::goToSubMenu()`.
- **Sélecteurs** (identiques en 1.7 et 9.2, sauf le tableau de bord) :

  | Page | Sélecteur |
  |---|---|
  | Tableau de bord | `#subtab-AdminDashboard, #tab-AdminDashboard` (`#tab-…` en 1.7, `#subtab-…` en 9.2) |
  | Produits | `#subtab-AdminProducts` |
  | Commandes | `#subtab-AdminOrders` |
  | Clients | `#subtab-AdminCustomers` |
  | Modules | `#subtab-AdminModulesSf` |

  Les entrées parentes (`#subtab-AdminCatalog`…) n'ont qu'une ancre
  `#collapse-N` : elles ne servent pas.
- **Session.** Elle tient d'une navigation à l'autre par URL à jeton.
- **Connexion.** `admin@prestashop.com` / `prestashop` (identifiants
  Flashlight déjà utilisés par les smoke tests).
- **Stabilité.** Deux captures à 5 s d'écart sont identiques, sauf :
  - le tableau de bord (graphiques nvd3 redessinés, iframes distantes,
    popup d'onboarding animée en 1.7) ;
  - en 9.2, les captures **pleine page** : `captureBeyondViewport` envoie
    des événements `resize`, la mise en page du menu latéral démarre une
    transition CSS, et la capture tombe au milieu (contenu décalé de 6 à
    7 px). Injecter `transition: none; animation: none` avant la capture
    donne 6 captures identiques sur 6.
- **Hauteurs.** Modules en 9.2 : 8 833 px ; clients en 9.2 : 4 470 px. Les
  captures se limitent donc à la fenêtre (zone `viewport`, défaut).
- **Durées.** Le tableau de bord 9.2 attend des ressources distantes : son
  état calme a pris jusqu'à 10 s.

## 1. `VisualTestsSuite` : zone back-office

### Propriété de suite

`protected string $area = 'fo';`. Avec `'bo'` :

- la suite importe la page `BackOffice` et la page de connexion
  `BackOffice\Login` (au lieu de `FrontOffice`) ;
- les checkpoints se résolvent par le menu (ci-dessous) ;
- le gel des transitions est actif par défaut (§ 3).

### Nouvelles clés de checkpoint

| Clé | Défaut | Rôle |
|---|---|---|
| `menu` | `null` | Sélecteur (ou liste séparée par des virgules) de l'entrée du menu latéral. La suite lit son lien et y navigue. `null` = racine du back-office. |
| `auth` | `true` | `false` : capturé sans être connecté (page de connexion). |
| `hide` | `[]` | Sélecteurs mis en `display: none` pendant la capture (popups, fonds de modale). Contrairement aux masques, la place libérée n'est pas grisée. |

`normalize()` les ajoute avec ces défauts. `menu` et `auth` ne sont lus
qu'en zone `bo` ; `hide` vaut pour les deux zones.

### Déroulé d'une suite `bo`

1. Les checkpoints `auth => false` passent en premier (tri stable, l'ordre
   déclaré est gardé dans chaque groupe). Ils visitent la racine du
   back-office **sans** session : la page de connexion.
2. Avant le premier checkpoint `auth => true`, la suite se connecte avec la
   page `BackOffice\Login` (identifiants `PRESTAFLOW_BO_EMAIL` /
   `PRESTAFLOW_BO_PASSWD`). Un échec de connexion fait échouer chacun des
   checkpoints connectés, avec le message de la connexion.
3. Pour chaque checkpoint connecté :
   - aller à la racine du back-office (le tableau de bord) ;
   - si `menu` est posé, lire le lien de la première entrée trouvée dans la
     liste, puis y naviguer ; une entrée introuvable fait échouer le
     checkpoint avec le sélecteur dans le message ;
   - puis le reste comme en front-office : attente de stabilité, `waitFor`,
     masques, capture.
4. Le raccourci « même URL que le checkpoint précédent » compare le couple
   (`menu`, `auth`), car les jetons changent les URL.

### Hors zone `bo`

Rien ne change pour les suites `fo` existantes, ni pour leurs références.

## 2. Suite `BackOffice`

`src/Tests/Suites/Visual/BackOffice.php` :

```php
protected string $area = 'bo';
protected array $devices = ['desktop'];
protected array $locales = ['en'];

// Communs à toutes les pages connectées.
private const CHROME = ['#total_notif_number_wrapper', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar'];

protected array $checkpoints = [
  ['name' => 'login', 'auth' => false, 'masks' => ['#login-header .text-center']],
  ['name' => 'dashboard', 'menu' => '#subtab-AdminDashboard, #tab-AdminDashboard',
   'masks' => [...CHROME, '#hookDashboardZoneOne', '#hookDashboardZoneTwo', '#hookDashboardZoneThree', '#calendar_form'],
   'hide' => ['.onboarding-popup', '.modal-backdrop']],
  ['name' => 'products', 'menu' => '#subtab-AdminProducts', 'masks' => CHROME],
  ['name' => 'orders', 'menu' => '#subtab-AdminOrders', 'masks' => [...CHROME, '.kpi-container', 'td.column-date_add']],
  ['name' => 'customers', 'menu' => '#subtab-AdminCustomers', 'masks' => [...CHROME, '.kpi-container', 'td.column-date_add', 'td.column-connect', '#customersShowcaseCard']],
  ['name' => 'modules', 'menu' => '#subtab-AdminModulesSf', 'masks' => [...CHROME, '.notification-counter']],
];
```

(Les constantes de classe ne s'étalent pas dans une propriété : le code
réel répète les listes ou les construit dans le constructeur.)

- **Masques absents d'une version** : un sélecteur qui ne trouve rien est
  sans effet, donc une même liste couvre 1.7, 8.2 et 9.2.
- **Références** : leur nom contient la version (`…--auto-v1.7-…`,
  `…-v8-…`, `…-v9-…`), comme en front-office.

## 3. Gel des transitions

- `CommonPage::visualCheckpoint()` injecte, avant la capture,
  `*, *::before, *::after { transition: none !important; animation: none !important; }`,
  puis le retire après, comme les masques.
- **Activé** pour les suites `bo` ; **désactivé** par défaut pour les suites
  `fo`, dont les références existantes ne doivent pas bouger.
  Propriété de suite : `protected ?bool $freezeTransitions = null`
  (`null` = selon la zone).

## 4. Workflow `visual.yml`

- **Matrice.** Une colonne `backoffice: true` sur une ligne par version :
  1.7.8.11 (8017), 8.2.8 (8082), et 9.2.0 classic (8093).
- **Étape** « Run the back-office visual suite », après la suite
  front-office, seulement sur ces lignes :
  - `PRESTAFLOW_DEVICE=desktop`, `PRESTAFLOW_LOCALE=en` ;
  - `PRESTAFLOW_BO_URL`, `PRESTAFLOW_BO_EMAIL=admin@prestashop.com`,
    `PRESTAFLOW_BO_PASSWD=prestashop` ;
  - sortie copiée dans `visual-output/backoffice/`, statut propre à
    l'étape (le job échoue si l'une des deux suites échoue).
- **Cache** : les références du back-office vivent dans le même dossier
  `visual-baseline/` et le même cache par version. Pas de changement de
  clé : au premier run, les références BO sont absentes et deviennent des
  premières captures.
- **Noms des checks inchangés**, donc la protection de `main` reste valide.

## 5. Vérification

1. **Tests unitaires** (`tests/Unit/Visual/`) :
   - `normalize()` : défauts de `menu`, `auth`, `hide` ;
   - ordre des checkpoints `bo` (non connectés d'abord, ordre déclaré
     gardé) ;
   - résolution d'une entrée de menu à partir d'une liste de sélecteurs
     (page factice) et message d'échec ;
   - `hide` et gel des transitions injectés puis retirés ; gel absent en
     `fo` par défaut ;
   - la suite `BackOffice` : 6 checkpoints, `login` sans connexion.
2. **En local**, sur 1.7 (8017) et 9.2 (8092) : deux passages de la suite ;
   le second doit comparer et passer. Références et sorties locales
   supprimées ensuite.
3. **En CI sur la PR** : les 4 jobs verts ; dans l'artefact, les captures BO
   des 3 versions sont bien des pages connectées (pas la page de
   connexion ni « Invalid token »).
4. **Après la release vers `main`** : 2 ou 3 runs manuels comparent sans
   écart, BO compris.

## Notes d'implémentation

Écarts avec ce qui précède, constatés à l'implémentation (commits 4a3962e,
d49c9be, 0ba7d8e) :

- **Masques** : `#notifications-total` ajouté (pastille de notifications du
  nouveau thème ; `#total_notif_number_wrapper` n'existe que dans le thème
  legacy).
- **`hide`** : `#ajax_running` (1.7 legacy) et `#header_infos .ajax-spinner`
  (9.2) sur tous les points de contrôle connectés. Sur commandes et clients,
  `.kpi-container` passe de `masks` à `hide` (sa hauteur varie avec le
  chargement ajax : un masque garde sa place et décale la page), et
  `.kpi-refresh` est masqué.
- **Non exercé** : le `hide` de `.onboarding-popup` ne l'est pas en local (la
  boutique 1.7 locale a `ONBOARDINGV2_SHUT_DOWN=1`) ; le masque
  `#login-header .text-center` de la connexion ne correspond à rien sur
  1.7.8.11 (sans effet).
- **Comportements ajoutés en relecture** : une session BO encore ouverte
  fait sauter la connexion ; un point de contrôle déconnecté échoue si une
  session est ouverte (« Session back-office déjà ouverte ») ; le message
  d'un refus de connexion inclut l'erreur du formulaire ; `auth => false`
  avec un `menu` est rejeté ; les méthodes auxiliaires sont `protected` (les
  étapes s'exécutent liées à la sous-classe concrète).
- **Vérification locale** : 1.7.8.11 et 9.2.0, 1re passe = 6 références,
  puis 3 passes supplémentaires à 6/6 PASS chacune.

## Hors périmètre

- Le back-office en français et sur mobile.
- Les fiches (produit, commande).
- L'édition des suites `bo` dans l'éditeur visuel de l'app : ses clés
  `menu`, `auth` et `hide` n'y sont pas gérées.
- La désactivation de l'onboarding 1.7 dans le script d'installation (la
  popup est masquée par `hide`).
