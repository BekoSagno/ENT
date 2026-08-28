-- ============================================================
-- CRICT — Module Mes demandes (congés, absences, ordres de mission)
-- Fichier : rh_demandes_unifie.sql
--
-- Prérequis : migration RH personnel déjà appliquée
--   (personnel, utilisateurs présents en base)
-- Ordre : 1) migration_rh_personnel.sql  2) ce fichier
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Types & soldes de congés ─────────────────────────────
CREATE TABLE IF NOT EXISTS `types_conges` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `libelle` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jours_par_an` DECIMAL(5,1) NOT NULL DEFAULT 0,
  `paye` TINYINT(1) NOT NULL DEFAULT 1,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_conge_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `soldes_conges` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` INT UNSIGNED NOT NULL,
  `type_id` INT UNSIGNED NOT NULL,
  `annee` SMALLINT UNSIGNED NOT NULL,
  `jours_acquis` DECIMAL(5,1) NOT NULL DEFAULT 0,
  `jours_pris` DECIMAL(5,1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_solde` (`personnel_id`,`type_id`,`annee`),
  KEY `fk_solde_type` (`type_id`),
  CONSTRAINT `fk_solde_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_solde_type`      FOREIGN KEY (`type_id`)      REFERENCES `types_conges` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Demandes unifiées (CONGE / ABSENCE / MISSION) ────────
CREATE TABLE IF NOT EXISTS `demandes_conges` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` INT UNSIGNED NOT NULL,
  `categorie` ENUM('CONGE','ABSENCE','MISSION') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CONGE',
  `reference` VARCHAR(30) DEFAULT NULL,
  `type_id` INT UNSIGNED NULL,
  `sous_type` VARCHAR(150) DEFAULT NULL COMMENT 'Libellé absence ou complément',
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL,
  `nombre_jours` DECIMAL(5,1) NOT NULL DEFAULT 0,
  `motif` TEXT COLLATE utf8mb4_unicode_ci,
  `statut` ENUM('EN_ATTENTE','EN_ATTENTE_DG','APPROUVE','REFUSE','ANNULE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EN_ATTENTE',
  `valide_par` INT UNSIGNED DEFAULT NULL,
  `valide_resp_par` INT UNSIGNED DEFAULT NULL,
  `valide_resp_le` DATETIME DEFAULT NULL,
  `valide_dg_par` INT UNSIGNED DEFAULT NULL,
  `valide_dg_le` DATETIME DEFAULT NULL,
  `commentaire_validation` TEXT COLLATE utf8mb4_unicode_ci,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_dc_personnel` (`personnel_id`),
  KEY `fk_dc_type` (`type_id`),
  KEY `fk_dc_validateur` (`valide_par`),
  KEY `idx_dc_statut` (`statut`),
  KEY `idx_dc_categorie` (`categorie`),
  CONSTRAINT `fk_dc_personnel`  FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dc_validateur` FOREIGN KEY (`valide_par`)   REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colonnes d'extension (idempotent si table déjà créée sans elles)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='demandes_conges' AND COLUMN_NAME='categorie');
SET @sql := IF(@col=0, "ALTER TABLE `demandes_conges` ADD COLUMN `categorie` ENUM('CONGE','ABSENCE','MISSION') NOT NULL DEFAULT 'CONGE' AFTER `personnel_id`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='demandes_conges' AND COLUMN_NAME='reference');
SET @sql := IF(@col=0, "ALTER TABLE `demandes_conges` ADD COLUMN `reference` VARCHAR(30) DEFAULT NULL AFTER `categorie`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='demandes_conges' AND COLUMN_NAME='sous_type');
SET @sql := IF(@col=0, "ALTER TABLE `demandes_conges` ADD COLUMN `sous_type` VARCHAR(150) DEFAULT NULL COMMENT 'Libellé absence ou complément' AFTER `type_id`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='demandes_conges' AND COLUMN_NAME='valide_resp_par');
SET @sql := IF(@col=0, "ALTER TABLE `demandes_conges` ADD COLUMN `valide_resp_par` INT UNSIGNED DEFAULT NULL AFTER `valide_par`, ADD COLUMN `valide_resp_le` DATETIME DEFAULT NULL AFTER `valide_resp_par`, ADD COLUMN `valide_dg_par` INT UNSIGNED DEFAULT NULL AFTER `valide_resp_le`, ADD COLUMN `valide_dg_le` DATETIME DEFAULT NULL AFTER `valide_dg_par`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE `demandes_conges` MODIFY COLUMN `type_id` INT UNSIGNED NULL;
ALTER TABLE `demandes_conges` MODIFY COLUMN `statut` ENUM('EN_ATTENTE','EN_ATTENTE_DG','APPROUVE','REFUSE','ANNULE') NOT NULL DEFAULT 'EN_ATTENTE';

-- ── 3. Ordres de mission ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `demandes_mission` (
  `demande_id` INT UNSIGNED NOT NULL,
  `destination` VARCHAR(255) NOT NULL,
  `objet_mission` TEXT NOT NULL,
  `moyen_transport` VARCHAR(150) DEFAULT NULL,
  `frais_estimes` VARCHAR(150) DEFAULT NULL,
  `prise_en_charge` TEXT DEFAULT NULL,
  `ordre_mission_numero` VARCHAR(80) DEFAULT NULL,
  PRIMARY KEY (`demande_id`),
  CONSTRAINT `fk_dm_demande` FOREIGN KEY (`demande_id`) REFERENCES `demandes_conges` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `demandes_mission_agents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `demande_id` INT UNSIGNED NOT NULL,
  `personnel_id` INT UNSIGNED NOT NULL,
  `ordre` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dma` (`demande_id`, `personnel_id`),
  KEY `idx_dma_pers` (`personnel_id`),
  CONSTRAINT `fk_dma_demande` FOREIGN KEY (`demande_id`) REFERENCES `demandes_conges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dma_pers` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `demandes_conges` SET `categorie` = 'CONGE' WHERE `categorie` IS NULL OR `categorie` = '';

-- ── 4. Référentiel types de congés ──────────────────────────
INSERT IGNORE INTO `types_conges` (`code`, `libelle`, `jours_par_an`, `paye`) VALUES
('ANNUEL', 'Congé annuel', 30, 1),
('MALADIE', 'Congé maladie', 0, 1),
('MATERNITE', 'Congé maternité', 14, 1),
('PATERNITE', 'Congé paternité', 3, 1),
('EXCEPTIONNEL', 'Congé exceptionnel', 5, 1),
('SANS_SOLDE', 'Congé sans solde', 0, 0);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Fin migration Mes demandes
-- ============================================================
