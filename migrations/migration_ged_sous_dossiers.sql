

SET NAMES utf8mb4;

-- ── 1. Dossiers d'un dépôt (avec parent_id pour l'arborescence) ──
CREATE TABLE IF NOT EXISTS `ged_dossiers` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `depot_id`  INT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = racine du dépôt ; sinon = sous-dossier',
  `nom`       VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cree_par`  INT UNSIGNED DEFAULT NULL,
  `cree_le`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gd_depot`  (`depot_id`),
  KEY `idx_gd_parent` (`parent_id`),
  CONSTRAINT `fk_gd_depot` FOREIGN KEY (`depot_id`)
      REFERENCES `ged_depots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gd_cree_par` FOREIGN KEY (`cree_par`)
      REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gd_parent` FOREIGN KEY (`parent_id`)
      REFERENCES `ged_dossiers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Emplacement d'un document ───────────────────────────
--    1 document = au plus 1 dossier. Absence de ligne = racine du dépôt.
CREATE TABLE IF NOT EXISTS `ged_document_emplacement` (
  `document_id` INT UNSIGNED NOT NULL,
  `dossier_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`document_id`),
  KEY `idx_ge_dossier` (`dossier_id`),
  CONSTRAINT `fk_ge_document` FOREIGN KEY (`document_id`)
      REFERENCES `ged_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ge_dossier` FOREIGN KEY (`dossier_id`)
      REFERENCES `ged_dossiers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Mise à niveau si ged_dossiers existe SANS parent_id ───
--    (cas : migration_ged_dossiers.sql exécutée seule en local)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ged_dossiers' AND COLUMN_NAME='parent_id');
SET @sql := IF(@col=0,
  "ALTER TABLE `ged_dossiers` ADD COLUMN `parent_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = racine' AFTER `depot_id`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ged_dossiers' AND INDEX_NAME='idx_gd_parent');
SET @sql := IF(@idx=0,
  "ALTER TABLE `ged_dossiers` ADD KEY `idx_gd_parent` (`parent_id`)",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ged_dossiers' AND CONSTRAINT_NAME='fk_gd_parent');
SET @sql := IF(@fk=0,
  "ALTER TABLE `ged_dossiers` ADD CONSTRAINT `fk_gd_parent` FOREIGN KEY (`parent_id`) REFERENCES `ged_dossiers` (`id`) ON DELETE CASCADE",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;