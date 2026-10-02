# Workflow « Visual » sur PrestaShop 1.7.8 et 8.2 : design

Date : 2026-10-01
Statut : implémenté (PR #101, release #102 ; suites #103 à #106)

## Objectif

Le workflow `.github/workflows/visual.yml` lance aujourd'hui les suites
visuelles `FrontOfficeClassic` et `FrontOfficeHummingbird` uniquement sur
la boutique Flashlight PrestaShop 9.2.0 :

- Hummingbird sur le port 8092 ;
- Classic sur le port 8093.

Chaque job couvre EN et FR, en desktop et en mobile.

La lib prétend supporter aussi PrestaShop 1.7.8 et 8.2, que les smoke tests
lancent déjà (`ps17`, port 8017 ; `ps82`, port 8082). L'objectif est d'y
vérifier aussi l'affichage, avec la même couverture : EN et FR, desktop et
mobile.

## Décisions

| Sujet | Décision |
|---|---|
| Versions ajoutées | 1.7.8.11 et 8.2.8, thème Classic (Hummingbird n'existe pas sur ces versions). |
| Couverture | Complète : EN + FR × desktop + mobile, comme en 9.2. |
| Suites | Une seule suite `FrontOfficeClassic`, réutilisée sur toutes les versions. |

## Constats (exploration du 2026-10-01)

- **Sélecteurs.** Le thème Classic de la boutique 1.7.8.11 locale contient
  les mêmes sélecteurs qu'en 9.2 : `#header`, `#footer`,
  `.cart-products-count`, `#carousel`. La 8.2 n'a pas été vérifiée en local,
  mais elle utilise le même thème Classic.
- **Pages.** En 1.7, `login`, `contact-us` et `search?s=test` répondent 200,
  et une page inconnue répond 404.
- **Panier.** En 1.7, `/cart` avec un panier vide **redirige vers
  l'accueil**. `cart?action=show` affiche la page panier en 1.7.8.11 comme en
  9.2 (titre « Cart »).
- **Pack FR.** Il est disponible pour 1.7.8.11, 8.2.8 et 9.2.0. La 1.7 le
  télécharge sur `i18n.prestashop.com`, qui répond 200.
- **Script d'installation.** L'API de la 1.7
  (`Language::downloadAndInstallLanguagePack`) est la même. Le script choisit
  déjà `AppKernel` (1.7, 8) ou `AdminKernel` (9).
- **Nom des références.** Le nom contient la version majeure de PrestaShop
  (`…--auto-v9-1920x1080-en.png`). Les versions ne se mélangent donc pas,
  même dans un même dossier.

## 1. Suite `FrontOfficeClassic`

- **Checkpoint `cart-empty`.** Le chemin devient `cart?action=show` (EN) et
  `panier?action=show` (FR, via `paths`). Il est le même sur toutes les
  versions.
- **Reste de la suite.** Rien ne change : checkpoints, masques
  (`.cart-products-count` sur le header, `#carousel` sur l'accueil) et
  `scrollBelow` `#header`.
- **`FrontOfficeHummingbird`.** Son chemin de panier est aligné de la même
  façon, par cohérence. Cette suite ne tourne qu'en 9.2.

## 2. Workflow `visual.yml`

- **Matrice.** Chaque ligne porte sa version de PrestaShop (`ps`) :

  | service | port | theme | suite | ps |
  |---|---|---|---|---|
  | ps17 | 8017 | classic | FrontOfficeClassic | 1.7.8.11 |
  | ps82 | 8082 | classic | FrontOfficeClassic | 8.2.8 |
  | ps92 | 8092 | hummingbird | FrontOfficeHummingbird | 9.2.0 |
  | ps92 | 8093 | classic | FrontOfficeClassic | 9.2.0 |

- **`matrix.ps` remplace le `9.2.0` codé en dur** dans :
  - le nom du job (`Visual (PrestaShop <ps>, <theme>)`) ;
  - `PRESTAFLOW_PS_VERSION` ;
  - le nom de l'artefact (`visual-<ps>-<theme>`).
- **Cache des références.**
  - Restauration avec la clé `visual-refs-v3-<ps>-<theme>-<run_id>` et
    `restore-keys: visual-refs-v3-<ps>-<theme>-`.
  - Sauvegarde, sur push vers `main` uniquement, avec
    `visual-refs-v3-<ps>-<theme>-<run_id>-<run_attempt>`.
  - Passer en `v3` repart de références neuves, car l'URL du panier change.
- **Étapes inchangées :**
  - démarrage du service et attente de `/admin-dev/` ;
  - installation du FR ;
  - vérification de `/en/` et `/fr/` (200) ;
  - 4 passages par job ;
  - artefact.
- **En-tête du workflow.** Il est mis à jour : versions couvertes, raison du
  chemin de panier, et cache en `v3`.

## 3. Script `.github/visual/install-french.sh`

- **Pas de changement prévu.** Le réglage `PS_DEFAULT_LANGUAGE_URL_PREFIX`
  n'existe qu'à partir de 9. En 1.7 et 8, une boutique à deux langues
  préfixe déjà chaque langue (`/en/`, `/fr/`). L'écriture de cette clé de
  configuration inconnue y est sans effet.
- **Si le premier run CI montre que `/en/` ne répond pas en 1.7 ou 8**, le
  script ou la vérification sont adaptés dans la même PR.

## 4. Vérification

1. **En local, sur la boutique 1.7 (port 8017).**
   - Elle n'a qu'une langue en local, donc on vérifie en EN, sans préfixe.
   - On lance `FrontOfficeClassic` en desktop et en mobile, deux fois. Le
     deuxième passage doit comparer et passer.
   - Les références et la sortie locales sont supprimées ensuite.
2. **En CI sur la PR.** Les 4 jobs doivent être verts, dont les 2 nouveaux.
   Le log doit montrer l'installation du FR et `/en/` `/fr/` à 200. On
   regarde quelques captures FR des artefacts 1.7 et 8.2.
3. **Après la release vers `main`.**
   - Le push sauvegarde les références `v3`.
   - Ensuite, 2 ou 3 runs manuels (`workflow_dispatch`) doivent comparer
     sans écart : on vérifie la présence des images de diff dans un
     artefact.

## 5. Protection de `main`

- **Deux nouveaux checks** apparaissent : « Visual (PrestaShop 1.7.8.11,
  classic) » et « Visual (PrestaShop 8.2.8, classic) ».
- **Les deux checks 9.2 gardent leur nom**, donc la règle actuelle reste
  valide pendant la transition.
- **Après la release**, l'utilisateur ajoute les deux nouveaux checks à la
  protection de `main`. La commande `gh api` lui est fournie.

## Livraison

- Une PR vers `dev`, puis la release `dev` → `main`.
- Aucun changement dans l'app.

## Notes d'implémentation

Écarts et suites relevés après la livraison :

- **Nom des références en 1.7** : le segment de version est `v1.7` (et non
  `v1`), par exemple `…cart-empty--auto-v1.7-1920x1080-en.png`.
- **Vérification après release** : 3 runs manuels sur `main`. 11 jobs sur 12
  verts ; le job 8.2.8 d'un run a échoué une fois sur la première capture
  (`header`, desktop EN) : « Operation timed out after 5s ». Aucune image en
  écart.
- **Réchauffage (#103, retiré par #105)** : charger chaque page des
  checkpoints avant les passages n'a rien changé, les pages répondaient déjà
  en 0 à 1 s. La lenteur venait de Chrome tout juste lancé, pas de la
  boutique.
- **Cause et correctif (#105)** : chrome-php attend 5 s par défaut dans
  `saveToFile()` et pour chaque appel synchrone (`sendSyncDefaultTimeout`),
  que la lib ne réglait pas.
  - Les captures visuelles s'écrivent avec 30 s
    (`CommonPage::SCREENSHOT_TIMEOUT_MS`).
  - `PRESTAFLOW_CDP_TIMEOUT` (ms, défaut 5000) règle les appels synchrones,
    au lancement comme à la reconnexion au navigateur partagé.
  - Le workflow Visual la met à 15 s.
  - Après la release #106, 3 runs manuels : 12 jobs sur 12 verts.
- **Protection de `main`** : les deux nouveaux checks Visual sont requis
  (13 checks au total).

## Hors périmètre

- Hummingbird sur 8.x (thème optionnel, non installé par Flashlight).
- Les boutiques LCDN.
- Les suites visuelles back-office.
