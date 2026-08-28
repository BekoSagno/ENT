-- ============================================================
-- CRICT — Module Projet de recherche (plan de rédaction)
-- Sous-menu de Projets & Tâches — option autonome
-- Fichier : migrations/migration_projet_recherche.sql
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `recherche_dossiers` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(40)  NOT NULL,
  `titre`           VARCHAR(255) NOT NULL,
  `statut`          ENUM('BROUILLON','EN_COURS','FINALISE') NOT NULL DEFAULT 'BROUILLON',
  `contenu`         MEDIUMTEXT   NULL COMMENT 'JSON des 8 étapes',
  `pct_complet`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `cree_par`        INT UNSIGNED NULL,
  `modifie_par`     INT UNSIGNED NULL,
  `cree_le`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recherche_code` (`code`),
  KEY `idx_recherche_statut` (`statut`),
  KEY `idx_recherche_auteur` (`cree_par`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
