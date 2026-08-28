-- ============================================================
-- migration_fournisseurs.sql — Module Gestion des Fournisseurs
-- Base : crict_ent
-- ============================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ────────────────────────────────────────────────────────────
-- 1. TABLE FOURNISSEURS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fournisseurs` (
  `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `nom_entreprise`      VARCHAR(150)    NOT NULL,
  `telephone`           VARCHAR(20)     NOT NULL,
  `site_web`            VARCHAR(200)    DEFAULT NULL,
  `email`               VARCHAR(100)    NOT NULL,
  `type_fournisseur`    ENUM('biens','services') NOT NULL,
  `categorie`           VARCHAR(100)    NOT NULL,
  `nom_gerant`          VARCHAR(100)    NOT NULL,
  `telephone_gerant`    VARCHAR(20)     NOT NULL,
  `numero_rccm`         VARCHAR(50)     NOT NULL,
  `quittance`           VARCHAR(100)    DEFAULT NULL,
  `nif`                 VARCHAR(50)     NOT NULL,
  `nom_utilisateur`     VARCHAR(50)     NOT NULL,
  `mot_de_passe`        VARCHAR(255)    NOT NULL,
  `statut`              ENUM('en_attente','valide','rejete','suspendu')
                                        NOT NULL DEFAULT 'en_attente',
  `date_inscription`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_validation`     DATETIME        DEFAULT NULL,
  `valide_par`          INT UNSIGNED    DEFAULT NULL,
  `motif_rejet`         TEXT            DEFAULT NULL,
  `note_moyenne`        DECIMAL(3,2)    NOT NULL DEFAULT 0.00,
  `nombre_evaluations`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `cree_par`            INT UNSIGNED    DEFAULT NULL,
  `modifie_le`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email`          (`email`),
  UNIQUE KEY `uq_nom_utilisateur`(`nom_utilisateur`),
  KEY `idx_statut`               (`statut`),
  KEY `idx_type`                 (`type_fournisseur`),

  CONSTRAINT `fk_f_valide_par` FOREIGN KEY (`valide_par`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_f_cree_par`   FOREIGN KEY (`cree_par`)   REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 2. TABLE DOCUMENTS FOURNISSEURS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents_fournisseurs` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `fournisseur_id`  INT UNSIGNED  NOT NULL,
  `type_document`   ENUM('piece_identite','rccm','nif','quittance','autre')
                                  NOT NULL DEFAULT 'autre',
  `nom_original`    VARCHAR(255)  NOT NULL,
  `nom_stockage`    VARCHAR(255)  NOT NULL,
  `chemin_fichier`  VARCHAR(500)  NOT NULL,
  `type_mime`       VARCHAR(100)  DEFAULT NULL,
  `taille_fichier`  INT UNSIGNED  DEFAULT NULL,
  `extension`       VARCHAR(15)   DEFAULT NULL,
  `depose_par`      INT UNSIGNED  DEFAULT NULL,
  `date_upload`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_df_fournisseur` (`fournisseur_id`),
  KEY `idx_df_type`        (`type_document`),

  CONSTRAINT `fk_df_fournisseur` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_df_depose`      FOREIGN KEY (`depose_par`)     REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 3. Activer le module FOURNISSEURS dans la table modules
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `modules` (`code`, `libelle`, `icone`, `url_base`, `ordre`, `actif`)
VALUES ('FOURNISSEURS', 'Gestion des Fournisseurs', 'fa-truck', '/modules/fournisseurs/', 7, 1);

-- Récupérer l'id du module inséré
SET @mod_id = (SELECT id FROM modules WHERE code = 'FOURNISSEURS' LIMIT 1);

-- Insérer les 6 permissions du module
INSERT IGNORE INTO `permissions` (`module_id`, `action`, `libelle`) VALUES
(@mod_id, 'VOIR',      'Consulter – Gestion des Fournisseurs'),
(@mod_id, 'CREER',     'Créer – Gestion des Fournisseurs'),
(@mod_id, 'MODIFIER',  'Modifier – Gestion des Fournisseurs'),
(@mod_id, 'SUPPRIMER', 'Supprimer – Gestion des Fournisseurs'),
(@mod_id, 'VALIDER',   'Valider – Gestion des Fournisseurs'),
(@mod_id, 'EXPORTER',  'Exporter – Gestion des Fournisseurs');

-- Accorder toutes les permissions à SUPER_ADMIN (1) et DG (2)
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`)
SELECT g.id, p.id, 1
FROM groupes_utilisateurs g, permissions p
JOIN modules m ON m.id = p.module_id
WHERE g.code IN ('SUPER_ADMIN', 'DG') AND m.code = 'FOURNISSEURS';

-- VOIR uniquement pour les autres groupes
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`)
SELECT g.id, p.id, 1
FROM groupes_utilisateurs g, permissions p
JOIN modules m ON m.id = p.module_id
WHERE g.code NOT IN ('SUPER_ADMIN', 'DG')
  AND m.code = 'FOURNISSEURS'
  AND p.action = 'VOIR';

COMMIT;
