-- ============================================================
-- migration_depenses_complet.sql
-- Module Subventions — « Exécuter une dépense » (script unique)
-- Fusion de :
--   • migration_depenses_executees.sql  (tables + permission EXECUTER)
--   • migration_depenses_brouillon.sql  (brouillon / reprise)
--   • migration_depenses_fiscal.sql     (TVA / Précompte)
-- Base : crict_ent
-- Migration idempotente (ré-exécutable sans effet de bord).
-- ============================================================

SET NAMES utf8mb4;

-- ════════════════════════════════════════════════════════════
-- PARTIE 1 — Tables + permission EXECUTER
-- ════════════════════════════════════════════════════════════

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `depenses_executees` (
  `id`                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `numero`            VARCHAR(30)       NOT NULL,
  `type_depense`      ENUM('biens_services','personnel') NOT NULL,
  `caisse_id`         INT UNSIGNED      NOT NULL,
  `categorie_id`      INT UNSIGNED      DEFAULT NULL,
  `libelle`           VARCHAR(300)      DEFAULT NULL,
  `montant`           DECIMAL(18,2)     NOT NULL,

  `fournisseur_id`    INT UNSIGNED      DEFAULT NULL,
  `fournisseur_nom`   VARCHAR(200)      DEFAULT NULL,

  `employe_id`        INT UNSIGNED      DEFAULT NULL,
  `employe_nom`       VARCHAR(200)      DEFAULT NULL,
  `compte_credite`    VARCHAR(120)      DEFAULT NULL,
  `motif`             TEXT              DEFAULT NULL,

  `periode_annee`     SMALLINT UNSIGNED NOT NULL,
  `periode_trimestre` TINYINT UNSIGNED  NOT NULL,

  `execute_par`       INT UNSIGNED      DEFAULT NULL,
  `date_execution`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_numero`        (`numero`),
  KEY `idx_de_type`             (`type_depense`),
  KEY `idx_de_caisse`           (`caisse_id`),
  KEY `idx_de_date`             (`date_execution`),

  CONSTRAINT `fk_de_caisse`      FOREIGN KEY (`caisse_id`)    REFERENCES `caisses_subvention`  (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_de_categorie`   FOREIGN KEY (`categorie_id`) REFERENCES `categories_depenses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_de_fournisseur` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs`      (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_de_employe`     FOREIGN KEY (`employe_id`)   REFERENCES `utilisateurs`        (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_de_execute`     FOREIGN KEY (`execute_par`)  REFERENCES `utilisateurs`        (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `depenses_documents` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `depense_id`     INT UNSIGNED NOT NULL,
  `type_document`  VARCHAR(40)  NOT NULL DEFAULT 'autre',
  `nom_original`   VARCHAR(255) NOT NULL,
  `nom_stockage`   VARCHAR(255) NOT NULL,
  `chemin_fichier` VARCHAR(500) NOT NULL,
  `type_mime`      VARCHAR(100) DEFAULT NULL,
  `taille_fichier` INT UNSIGNED DEFAULT NULL,
  `extension`      VARCHAR(15)  DEFAULT NULL,
  `depose_par`     INT UNSIGNED DEFAULT NULL,
  `date_upload`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_dd_depense` (`depense_id`),
  CONSTRAINT `fk_dd_depense` FOREIGN KEY (`depense_id`) REFERENCES `depenses_executees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dd_depose`  FOREIGN KEY (`depose_par`)  REFERENCES `utilisateurs`       (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sid = (SELECT id FROM modules WHERE code='SUBVENTIONS' LIMIT 1);

INSERT IGNORE INTO `permissions` (`module_id`,`action`,`libelle`) VALUES
(@sid,'EXECUTER','Exécuter des dépenses – Subventions');

INSERT IGNORE INTO `groupes_permissions` (`groupe_id`,`permission_id`,`accorde`)
SELECT g.id, p.id, 1
FROM groupes_utilisateurs g, permissions p
JOIN modules m ON m.id = p.module_id
WHERE g.code IN ('SUPER_ADMIN','DG','DAF')
  AND m.code = 'SUBVENTIONS'
  AND p.action = 'EXECUTER';

COMMIT;

-- ════════════════════════════════════════════════════════════
-- PARTIE 2 — Brouillon (enregistrer / reprendre)
-- ════════════════════════════════════════════════════════════

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='depenses_executees' AND COLUMN_NAME='statut');
SET @sql := IF(@col=0,
  "ALTER TABLE `depenses_executees` ADD COLUMN `statut` ENUM('brouillon','execute') NOT NULL DEFAULT 'execute' AFTER `numero`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='depenses_executees' AND COLUMN_NAME='cree_par');
SET @sql := IF(@col=0,
  "ALTER TABLE `depenses_executees` ADD COLUMN `cree_par` INT UNSIGNED DEFAULT NULL AFTER `execute_par`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='depenses_executees' AND COLUMN_NAME='date_creation');
SET @sql := IF(@col=0,
  "ALTER TABLE `depenses_executees` ADD COLUMN `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `date_execution`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE `depenses_executees`
  MODIFY `numero`            VARCHAR(30)       NULL,
  MODIFY `caisse_id`         INT UNSIGNED      NULL,
  MODIFY `montant`           DECIMAL(18,2)     NULL,
  MODIFY `periode_annee`     SMALLINT UNSIGNED NULL,
  MODIFY `periode_trimestre` TINYINT UNSIGNED  NULL,
  MODIFY `date_execution`    DATETIME          NULL DEFAULT NULL;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='depenses_executees' AND INDEX_NAME='idx_de_statut');
SET @sql := IF(@idx=0,
  "ALTER TABLE `depenses_executees` ADD KEY `idx_de_statut` (`statut`)",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ════════════════════════════════════════════════════════════
-- PARTIE 3 — Régime fiscal (TVA / Précompte)
-- ════════════════════════════════════════════════════════════

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='depenses_executees' AND COLUMN_NAME='montant_ht');
SET @sql := IF(@col=0,
  "ALTER TABLE `depenses_executees` ADD COLUMN `montant_ht` DECIMAL(18,2) DEFAULT NULL AFTER `montant`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='depenses_executees' AND COLUMN_NAME='montant_taxe');
SET @sql := IF(@col=0,
  "ALTER TABLE `depenses_executees` ADD COLUMN `montant_taxe` DECIMAL(18,2) DEFAULT NULL AFTER `montant_ht`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='depenses_executees' AND COLUMN_NAME='regime_fiscal');
SET @sql := IF(@col=0,
  "ALTER TABLE `depenses_executees` ADD COLUMN `regime_fiscal` VARCHAR(20) DEFAULT NULL AFTER `montant_taxe`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;