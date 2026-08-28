# Module GED — Gestion Électronique de Documents (CRICT)

Module conforme au template des autres modules (modèle dans `models/`, pages dans
`modules/<module>/`, layout `views/layout/header.php` + `footer.php`, contrôle d'accès
via `auth.php`, design via les variables CSS `--blue-*`, `.btn`, `.card`, `.modal`, etc.).

## Fonctionnalités

- **Dépôts** créés par chaque utilisateur, avec 3 niveaux de visibilité :
  - **Privé** — accessible au seul propriétaire.
  - **Partagé** — accessible aux utilisateurs sélectionnés (rôle *Lecture* ou *Contribution*).
  - **Public** — accessible à tous les utilisateurs connectés.
- **Documents** déposés dans un dépôt, avec deux portées :
  - **Hérité** — suit la visibilité du dépôt.
  - **Restreint** — accès limité à des **utilisateurs** et/ou des **groupes** explicitement choisis
    (en plus du propriétaire et du déposant).
- **Téléchargement sécurisé** : les fichiers ne sont jamais servis en direct ; `download.php`
  vérifie les droits avant chaque accès (aperçu inline ou téléchargement forcé via `&dl=1`).
- Journalisation via `log_activite()` (module `GED`) à chaque création, dépôt, partage,
  téléchargement et suppression.

## Arborescence à déployer

```
models/ged.php
modules/ged/index.php       (liste des dépôts)
modules/ged/depot.php       (détail d'un dépôt : documents + partage)
modules/ged/actions.php     (handler POST)
modules/ged/download.php    (service sécurisé des fichiers)
uploads/ged/                (créé automatiquement, doit être inscriptible)
```

## Installation

1. **Base de données** : exécuter `sql/migration_ged.sql` (phpMyAdmin ou CLI).
   Crée les 5 tables `ged_*`, enregistre le module `GED` et ses permissions
   (`VOIR`, `CREER`, `MODIFIER`, `SUPPRIMER`), et accorde `VOIR`+`CREER` à tous les
   groupes actifs (chacun gère ses propres dépôts).

2. **Fichiers** : copier `models/ged.php` dans `models/`, et le dossier `modules/ged/`
   dans `modules/`.

3. **Dossier d'upload** : s'assurer que `uploads/` est inscriptible par PHP.
   Le sous-dossier `uploads/ged/<id_depot>/` est créé automatiquement.

4. **Menu** : insérer le contenu de `sidebar_snippet.php` dans `views/layout/header.php`
   (dans la `<nav class="sidebar-nav">`, par ex. après « Projets & Tâches »).

## Modèle d'autorisation (résumé)

| Rôle / cas                    | Voir dépôt | Déposer | Gérer dépôt | Voir doc restreint |
|-------------------------------|:----------:|:-------:|:-----------:|:------------------:|
| Propriétaire                  | ✅ | ✅ | ✅ | ✅ |
| SUPER_ADMIN / DG (admin GED)  | ✅ | ✅ | ✅ | ✅ |
| Membre *Contribution*         | ✅ | ✅ | ❌ | si autorisé |
| Membre *Lecture*              | ✅ | ❌ | ❌ | si autorisé |
| Dépôt public (autre user)     | ✅ | ❌ | ❌ | si autorisé |
| Utilisateur/Groupe autorisé sur un doc restreint | — | — | ❌ | ✅ |

Le contrôle fin « ses propres dépôts » est géré dans le modèle (`ged_depot_droits`),
pas uniquement par les permissions de groupe : un simple `CHERCHEUR` peut créer et
administrer **ses** dépôts sans avoir la permission globale `GED/MODIFIER`.
