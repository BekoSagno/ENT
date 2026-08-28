# ENT — CRICT Système de Gestion Administrative

Application PHP/MySQL pour la gestion institutionnelle du **CRICT** (Centre de Recherche en Informatique, Culture et Technologie).

## Modules

- Évaluation institutionnelle (DGRS, auto-évaluation CIAQ/ANAQ)
- Personnel et congés
- Subventions et dépenses
- GED, courriers, instructions
- Projets de recherche, rendez-vous DG, fournisseurs, etc.

## Installation

1. Cloner le dépôt et placer les fichiers sur un serveur PHP 8+ avec MySQL/MariaDB.
2. Copier la configuration :
   ```bash
   cp config/config.example.php config/config.php
   ```
3. Renseigner base de données, URL et SMTP dans `config/config.php`.
4. Installer les dépendances :
   ```bash
   composer install
   ```
5. Exécuter les migrations SQL dans `migrations/` (ordre selon votre déploiement).
6. Créer le dossier `uploads/` avec droits d'écriture pour PHP.

## Développement local

```bash
php -S localhost:8000
```

Puis ouvrir `http://localhost:8000`.

## Licence

Usage institutionnel CRICT.
