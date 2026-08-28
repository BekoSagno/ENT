-- ============================================================
-- migration_rapports_dgpeip.sql
-- Module Subventions — sous-module « Exécution de rapport »
-- Formulaire de renseignement à transmettre à la DGPEIP.
-- Tables NOUVELLES uniquement (aucun impact sur l'existant).
-- Base : crict_ent
-- ============================================================

SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 1. RAPPORT (1 ligne = 1 rapport transmis)
--    Les sections répétables sont stockées en JSON (LONGTEXT)
--    pour rester simple et flexible (années variables, etc.).
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rapports_dgpeip` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `statut`               ENUM('brouillon','finalise') NOT NULL DEFAULT 'brouillon',

  -- Section 1 — Désignation de l'EPA
  `nom_epa`              VARCHAR(255) DEFAULT NULL,
  `sigle`                VARCHAR(100) DEFAULT NULL,
  `ministere_tutelle`    VARCHAR(255) DEFAULT NULL,
  `date_creation_epa`    VARCHAR(100) DEFAULT NULL,
  `texte_creation`       TEXT         DEFAULT NULL,
  `texte_organisation`   TEXT         DEFAULT NULL,
  `siege`                VARCHAR(255) DEFAULT NULL,
  `mission`              TEXT         DEFAULT NULL,

  -- Années couvertes (ex : "2024,2025,2026")
  `annees`               VARCHAR(100) DEFAULT NULL,

  -- Sections répétables (JSON encodé)
  `cadre_institutionnel` LONGTEXT     DEFAULT NULL,
  `diligences`           LONGTEXT     DEFAULT NULL,
  `rh_effectifs`         LONGTEXT     DEFAULT NULL,
  `execution_budgetaire` LONGTEXT     DEFAULT NULL,

  -- Audit
  `cree_par`             INT UNSIGNED DEFAULT NULL,
  `date_creation`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finalise_par`         INT UNSIGNED DEFAULT NULL,
  `date_finalisation`    DATETIME     DEFAULT NULL,
  `date_maj`             DATETIME     DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_rd_statut` (`statut`),
  CONSTRAINT `fk_rd_cree`  FOREIGN KEY (`cree_par`)      REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rd_final` FOREIGN KEY (`finalise_par`)  REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 2. DOCUMENTS JOINTS AU RAPPORT
--    (rapports des corps de contrôle, autres pièces)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rapports_dgpeip_documents` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rapport_id`     INT UNSIGNED NOT NULL,
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
  KEY `idx_rdd_rapport` (`rapport_id`),
  CONSTRAINT `fk_rdd_rapport` FOREIGN KEY (`rapport_id`) REFERENCES `rapports_dgpeip` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rdd_depose`  FOREIGN KEY (`depose_par`)  REFERENCES `utilisateurs`    (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 3. PERMISSION « RAPPORT » pour le module SUBVENTIONS
--    (déléguable par le Super Admin via la matrice des permissions)
-- ────────────────────────────────────────────────────────────
SET @sid = (SELECT id FROM modules WHERE code='SUBVENTIONS' LIMIT 1);

INSERT IGNORE INTO `permissions` (`module_id`,`action`,`libelle`) VALUES
(@sid,'RAPPORT','Exécution de rapport DGPEIP – Subventions');

INSERT IGNORE INTO `groupes_permissions` (`groupe_id`,`permission_id`,`accorde`)
SELECT g.id, p.id, 1
FROM groupes_utilisateurs g, permissions p
JOIN modules m ON m.id = p.module_id
WHERE g.code IN ('SUPER_ADMIN','DG','DAF')
  AND m.code = 'SUBVENTIONS'
  AND p.action = 'RAPPORT';
