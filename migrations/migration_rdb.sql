-- ============================================================
-- migration_rdb.sql
-- Module Subventions — Rapport d'Exécution Budgétaire (RDB)
-- Remplace l'ancien rapport DGPEIP (rapports_dgpeip).
-- Base : crict_ent — idempotent.
-- ============================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- ── 1. Rapport trimestriel ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `rapports_rdb` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `annee`                 SMALLINT UNSIGNED NOT NULL,
  `trimestre`             TINYINT UNSIGNED NOT NULL COMMENT '1 à 4',
  `statut`                ENUM('brouillon','finalise') NOT NULL DEFAULT 'brouillon',

  -- Section 1 — Présentation sommaire EPA
  `acte_creation`         TEXT DEFAULT NULL,
  `missions`              TEXT DEFAULT NULL,
  `organisation`          TEXT DEFAULT NULL,

  -- Section 3 — Commentaire global personnel (tableau 5)
  `commentaire_personnel` TEXT DEFAULT NULL,

  -- Sections 4, 5, 6
  `suggestions`           TEXT DEFAULT NULL,
  `recommandations`       TEXT DEFAULT NULL,
  `conclusion`            TEXT DEFAULT NULL,

  `cree_par`              INT UNSIGNED DEFAULT NULL,
  `date_creation`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_maj`              DATETIME DEFAULT NULL,
  `finalise_par`          INT UNSIGNED DEFAULT NULL,
  `date_finalisation`     DATETIME DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rdb_periode` (`annee`, `trimestre`),
  KEY `idx_rdb_statut` (`statut`),
  CONSTRAINT `fk_rdb_cree`   FOREIGN KEY (`cree_par`)     REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rdb_final`  FOREIGN KEY (`finalise_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Lignes des 9 tableaux ───────────────────────────────
CREATE TABLE IF NOT EXISTS `rdb_lignes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rapport_id`    INT UNSIGNED NOT NULL,
  `tableau_no`    TINYINT UNSIGNED NOT NULL COMMENT '1 à 9',
  `code_ligne`    VARCHAR(30)  DEFAULT NULL,
  `libelle`       VARCHAR(300) NOT NULL DEFAULT '',
  `ordre`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `donnees`       JSON DEFAULT NULL,
  `commentaire`   TEXT DEFAULT NULL,
  `justification` VARCHAR(500) DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_rl_rapport` (`rapport_id`, `tableau_no`),
  CONSTRAINT `fk_rl_rapport` FOREIGN KEY (`rapport_id`) REFERENCES `rapports_rdb` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Documents joints (optionnel) ─────────────────────────
CREATE TABLE IF NOT EXISTS `rdb_documents` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rapport_id`     INT UNSIGNED NOT NULL,
  `type_document`  VARCHAR(40)  NOT NULL DEFAULT 'annexe',
  `nom_original`   VARCHAR(255) NOT NULL,
  `nom_stockage`   VARCHAR(255) NOT NULL,
  `chemin_fichier` VARCHAR(500) NOT NULL,
  `type_mime`      VARCHAR(100) DEFAULT NULL,
  `taille_fichier` INT UNSIGNED DEFAULT NULL,
  `extension`      VARCHAR(15)  DEFAULT NULL,
  `depose_par`     INT UNSIGNED DEFAULT NULL,
  `date_upload`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_rd_rapport` (`rapport_id`),
  CONSTRAINT `fk_rd_rapport` FOREIGN KEY (`rapport_id`) REFERENCES `rapports_rdb` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rd_depose`  FOREIGN KEY (`depose_par`)  REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Permission RDB (remplace libellé RAPPORT) ────────────
SET @sid = (SELECT id FROM modules WHERE code='SUBVENTIONS' LIMIT 1);

INSERT IGNORE INTO `permissions` (`module_id`,`action`,`libelle`) VALUES
(@sid,'RAPPORT','RDB – Rapport d\'Exécution Budgétaire');

UPDATE `permissions` p
JOIN `modules` m ON m.id = p.module_id
SET p.libelle = 'RDB – Rapport d\'Exécution Budgétaire'
WHERE m.code = 'SUBVENTIONS' AND p.action = 'RAPPORT';

COMMIT;
