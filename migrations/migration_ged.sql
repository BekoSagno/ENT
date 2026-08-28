-- ============================================================
--  Migration : Module GED (Gestion Électronique de Documents)
--  Base : crict_ent
--  À exécuter une seule fois (idempotent autant que possible)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. DÉPÔTS
--    Un dépôt appartient à un utilisateur (proprietaire_id).
--    visibilite :
--      PRIVE      → accessible au seul propriétaire (+ membres partagés)
--      PARTAGE    → accessible aux membres listés dans ged_depot_membres
--      PUBLIC     → accessible à tous les utilisateurs connectés
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `ged_depots`;
CREATE TABLE IF NOT EXISTS `ged_depots` (
  `id`             int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`            varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description`    text COLLATE utf8mb4_unicode_ci,
  `couleur`        varchar(7)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1a5ccc',
  `icone`          varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa-folder',
  `visibilite`     enum('PRIVE','PARTAGE','PUBLIC') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRIVE',
  `proprietaire_id` int UNSIGNED NOT NULL,
  `cree_le`        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proprietaire` (`proprietaire_id`),
  KEY `idx_visibilite` (`visibilite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. MEMBRES D'UN DÉPÔT PARTAGÉ
--    Liste des utilisateurs autorisés sur un dépôt PARTAGE.
--    role : LECTEUR (consulter/télécharger) | CONTRIBUTEUR (+ déposer)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `ged_depot_membres`;
CREATE TABLE IF NOT EXISTS `ged_depot_membres` (
  `id`             int UNSIGNED NOT NULL AUTO_INCREMENT,
  `depot_id`       int UNSIGNED NOT NULL,
  `utilisateur_id` int UNSIGNED NOT NULL,
  `role`           enum('LECTEUR','CONTRIBUTEUR') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LECTEUR',
  `ajoute_le`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_depot_user` (`depot_id`,`utilisateur_id`),
  KEY `idx_user` (`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. DOCUMENTS
--    portee :
--      HERITE     → suit la visibilité du dépôt (par défaut)
--      RESTREINT  → accès limité aux utilisateurs / groupes explicitement listés
--                   (en plus du propriétaire)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `ged_documents`;
CREATE TABLE IF NOT EXISTS `ged_documents` (
  `id`            int UNSIGNED NOT NULL AUTO_INCREMENT,
  `depot_id`      int UNSIGNED NOT NULL,
  `titre`         varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description`   text COLLATE utf8mb4_unicode_ci,
  `nom_original`  varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_stockage`  varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin`        varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_mime`     varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `taille`        int UNSIGNED NOT NULL,
  `extension`     varchar(15)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `portee`        enum('HERITE','RESTREINT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'HERITE',
  `depose_par`    int UNSIGNED DEFAULT NULL,
  `cree_le`       datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_depot` (`depot_id`),
  KEY `idx_portee` (`portee`),
  KEY `fk_doc_dep` (`depose_par`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. ACCÈS RESTREINT PAR UTILISATEUR (document)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `ged_document_acces`;
CREATE TABLE IF NOT EXISTS `ged_document_acces` (
  `id`             int UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`    int UNSIGNED NOT NULL,
  `utilisateur_id` int UNSIGNED NOT NULL,
  `accorde_le`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_user` (`document_id`,`utilisateur_id`),
  KEY `idx_user` (`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. ACCÈS RESTREINT PAR GROUPE (document)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `ged_document_groupes`;
CREATE TABLE IF NOT EXISTS `ged_document_groupes` (
  `id`          int UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` int UNSIGNED NOT NULL,
  `groupe_id`   int UNSIGNED NOT NULL,
  `accorde_le`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_groupe` (`document_id`,`groupe_id`),
  KEY `idx_groupe` (`groupe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Contraintes
-- ------------------------------------------------------------
ALTER TABLE `ged_depots`
  ADD CONSTRAINT `fk_depot_proprietaire` FOREIGN KEY (`proprietaire_id`)
      REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ged_depot_membres`
  ADD CONSTRAINT `fk_dm_depot` FOREIGN KEY (`depot_id`)
      REFERENCES `ged_depots` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dm_user` FOREIGN KEY (`utilisateur_id`)
      REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

ALTER TABLE `ged_documents`
  ADD CONSTRAINT `fk_doc_depot` FOREIGN KEY (`depot_id`)
      REFERENCES `ged_depots` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_doc_dep` FOREIGN KEY (`depose_par`)
      REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

ALTER TABLE `ged_document_acces`
  ADD CONSTRAINT `fk_da_doc` FOREIGN KEY (`document_id`)
      REFERENCES `ged_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_da_user` FOREIGN KEY (`utilisateur_id`)
      REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

ALTER TABLE `ged_document_groupes`
  ADD CONSTRAINT `fk_dg_doc` FOREIGN KEY (`document_id`)
      REFERENCES `ged_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dg_groupe` FOREIGN KEY (`groupe_id`)
      REFERENCES `groupes_utilisateurs` (`id`) ON DELETE CASCADE;

-- ------------------------------------------------------------
-- 6. ENREGISTREMENT DU MODULE + PERMISSIONS
-- ------------------------------------------------------------
INSERT INTO `modules` (`code`, `libelle`, `icone`, `url_base`, `ordre`, `actif`)
SELECT 'GED', 'Gestion Électronique de Documents', 'fa-folder-tree', '/modules/ged/', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'GED');

SET @mid := (SELECT id FROM `modules` WHERE code = 'GED' LIMIT 1);

INSERT INTO `permissions` (`module_id`, `action`, `libelle`)
SELECT @mid, a.action, a.libelle FROM (
    SELECT 'VOIR'      AS action, 'Consulter – GED'                  AS libelle
    UNION ALL SELECT 'CREER',     'Créer un dépôt / déposer – GED'
    UNION ALL SELECT 'MODIFIER',  'Modifier dépôts & documents – GED'
    UNION ALL SELECT 'SUPPRIMER', 'Supprimer dépôts & documents – GED'
) a
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` p WHERE p.module_id = @mid AND p.action = a.action
);

-- Accorder VOIR + CREER à tous les groupes actifs (chacun gère ses propres dépôts).
-- MODIFIER / SUPPRIMER au niveau global réservés à SUPER_ADMIN/DG
-- (le contrôle fin sur "ses propres" dépôts est géré dans le modèle).
INSERT INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`)
SELECT g.id, p.id, 1
FROM `groupes_utilisateurs` g
JOIN `permissions` p ON p.module_id = @mid AND p.action IN ('VOIR','CREER')
WHERE g.actif = 1
  AND NOT EXISTS (
      SELECT 1 FROM `groupes_permissions` gp
      WHERE gp.groupe_id = g.id AND gp.permission_id = p.id
  );

INSERT INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`)
SELECT g.id, p.id, 1
FROM `groupes_utilisateurs` g
JOIN `permissions` p ON p.module_id = @mid AND p.action IN ('MODIFIER','SUPPRIMER')
WHERE g.code IN ('SUPER_ADMIN','DG')
  AND NOT EXISTS (
      SELECT 1 FROM `groupes_permissions` gp
      WHERE gp.groupe_id = g.id AND gp.permission_id = p.id
  );

SET FOREIGN_KEY_CHECKS = 1;
