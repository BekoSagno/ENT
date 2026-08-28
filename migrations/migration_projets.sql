-- ============================================================
-- migration_projets.sql — Module Gestion des Projets  CRICT
-- Base : crict_ent   Charset : utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ────────────────────────────────────────────────────────────
-- 1. PROJETS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projets` (
  `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `code`             VARCHAR(30)      NOT NULL COMMENT 'PROJ-AAAA-NNN',
  `titre`            VARCHAR(255)     NOT NULL,
  `description`      TEXT,
  `tdr`              LONGTEXT         COMMENT 'Termes De Référence (texte / Markdown)',
  `cahier_charges`   LONGTEXT         COMMENT 'Cahier des charges',
  `type_projet`      ENUM('ADMINISTRATIF','INFORMATIQUE','TERRAIN','RECHERCHE','AUTRE')
                                      NOT NULL DEFAULT 'AUTRE',
  `budget`           DECIMAL(18,2)    DEFAULT NULL,
  `devise`           VARCHAR(10)      NOT NULL DEFAULT 'GNF',
  `date_debut`       DATE             NOT NULL,
  `date_fin_prevue`  DATE             NOT NULL,
  `date_fin_reelle`  DATE             DEFAULT NULL,
  `statut`           ENUM('PLANIFIE','EN_COURS','SUSPENDU','TERMINE','ANNULE')
                                      NOT NULL DEFAULT 'PLANIFIE',
  `priorite`         ENUM('FAIBLE','NORMALE','HAUTE','CRITIQUE')
                                      NOT NULL DEFAULT 'NORMALE',
  `responsable_id`   INT UNSIGNED     NOT NULL,
  `cree_par`         INT UNSIGNED     DEFAULT NULL,
  `cree_le`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE  KEY `uq_code`       (`code`),
  KEY     `idx_statut`        (`statut`),
  KEY     `idx_responsable`   (`responsable_id`),
  KEY     `idx_dates`         (`date_debut`,`date_fin_prevue`),
  CONSTRAINT `fk_proj_resp`   FOREIGN KEY (`responsable_id`) REFERENCES `utilisateurs`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_proj_cree`   FOREIGN KEY (`cree_par`)       REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 2. MEMBRES DU PROJET
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projets_membres` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`      INT UNSIGNED NOT NULL,
  `utilisateur_id` INT UNSIGNED NOT NULL,
  `role`           ENUM('CHEF','MEMBRE','OBSERVATEUR') NOT NULL DEFAULT 'MEMBRE',
  `ajoute_le`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE  KEY `uq_proj_user`  (`projet_id`,`utilisateur_id`),
  KEY     `idx_user`          (`utilisateur_id`),
  CONSTRAINT `fk_pm_projet`  FOREIGN KEY (`projet_id`)      REFERENCES `projets`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_user`    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 3. TÂCHES
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projets_taches` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `projet_id`        INT UNSIGNED    NOT NULL,
  `parent_id`        INT UNSIGNED    DEFAULT NULL   COMMENT 'Sous-tâche',
  `code`             VARCHAR(40)     DEFAULT NULL,
  `titre`            VARCHAR(300)    NOT NULL,
  `description`      TEXT,
  `assigne_a`        INT UNSIGNED    DEFAULT NULL,
  `date_debut`       DATE            DEFAULT NULL,
  `date_fin_prevue`  DATE            DEFAULT NULL   COMMENT 'Deadline',
  `date_fin_reelle`  DATE            DEFAULT NULL,
  `duree_estimee`    SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Heures',
  `dependance_type`  ENUM('INDEPENDANTE','APRES_FIN','APRES_DEBUT')
                                     NOT NULL DEFAULT 'INDEPENDANTE',
  `depend_de`        INT UNSIGNED    DEFAULT NULL,
  `statut`           ENUM('A_FAIRE','EN_COURS','EN_REVISION','TERMINEE','BLOQUEE','ANNULEE')
                                     NOT NULL DEFAULT 'A_FAIRE',
  `avancement`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `priorite`         ENUM('FAIBLE','NORMALE','HAUTE','CRITIQUE')
                                     NOT NULL DEFAULT 'NORMALE',
  `outputs_attendus` TEXT            COMMENT 'Description des livrables attendus',
  `ordre`            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `cree_par`         INT UNSIGNED    DEFAULT NULL,
  `cree_le`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_t_projet`  (`projet_id`),
  KEY `idx_t_parent`  (`parent_id`),
  KEY `idx_t_assigne` (`assigne_a`),
  KEY `idx_t_statut`  (`statut`),
  KEY `idx_t_depend`  (`depend_de`),
  CONSTRAINT `fk_t_projet`  FOREIGN KEY (`projet_id`) REFERENCES `projets`        (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_parent`  FOREIGN KEY (`parent_id`) REFERENCES `projets_taches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_t_assigne` FOREIGN KEY (`assigne_a`) REFERENCES `utilisateurs`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_t_depend`  FOREIGN KEY (`depend_de`) REFERENCES `projets_taches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_t_cree`    FOREIGN KEY (`cree_par`)  REFERENCES `utilisateurs`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 4. OUTPUTS / LIVRABLES DES TÂCHES
--    type  FICHIER  → documents / PDF / Word / Excel (administratif)
--          IMAGE    → captures terrain (JPEG, PNG…)
--          LIEN     → URL GitHub / démo / externe (informatique)
--          NOTE     → note texte libre
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projets_taches_outputs` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tache_id`     INT UNSIGNED  NOT NULL,
  `projet_id`    INT UNSIGNED  NOT NULL,
  `type`         ENUM('FICHIER','IMAGE','LIEN','NOTE') NOT NULL DEFAULT 'FICHIER',
  `nom_original` VARCHAR(255)  DEFAULT NULL,
  `nom_stockage` VARCHAR(255)  DEFAULT NULL,
  `chemin`       VARCHAR(500)  DEFAULT NULL,
  `type_mime`    VARCHAR(100)  DEFAULT NULL,
  `taille`       INT UNSIGNED  DEFAULT NULL,
  `extension`    VARCHAR(15)   DEFAULT NULL,
  `url`          VARCHAR(2000) DEFAULT NULL,
  `contenu`      TEXT          DEFAULT NULL,
  `libelle`      VARCHAR(300)  DEFAULT NULL,
  `depose_par`   INT UNSIGNED  DEFAULT NULL,
  `cree_le`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_o_tache`  (`tache_id`),
  KEY `idx_o_projet` (`projet_id`),
  CONSTRAINT `fk_o_tache`  FOREIGN KEY (`tache_id`)   REFERENCES `projets_taches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_o_projet` FOREIGN KEY (`projet_id`)  REFERENCES `projets`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_o_dep`    FOREIGN KEY (`depose_par`) REFERENCES `utilisateurs`    (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 5. COMMENTAIRES SUR LES TÂCHES
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projets_taches_commentaires` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tache_id`   INT UNSIGNED NOT NULL,
  `auteur_id`  INT UNSIGNED DEFAULT NULL,
  `contenu`    TEXT         NOT NULL,
  `cree_le`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_c_tache` (`tache_id`),
  CONSTRAINT `fk_c_tache`  FOREIGN KEY (`tache_id`)  REFERENCES `projets_taches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_c_auteur` FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 6. DOCUMENTS GLOBAUX DU PROJET  (TDR, CDC, Budget…)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projets_documents` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `projet_id`    INT UNSIGNED NOT NULL,
  `categorie`    ENUM('TDR','CAHIER_CHARGES','BUDGET','RAPPORT','AUTRE') NOT NULL DEFAULT 'AUTRE',
  `nom_original` VARCHAR(255) NOT NULL,
  `nom_stockage` VARCHAR(255) NOT NULL,
  `chemin`       VARCHAR(500) NOT NULL,
  `type_mime`    VARCHAR(100) NOT NULL,
  `taille`       INT UNSIGNED NOT NULL,
  `extension`    VARCHAR(15)  NOT NULL,
  `libelle`      VARCHAR(300) DEFAULT NULL,
  `depose_par`   INT UNSIGNED DEFAULT NULL,
  `cree_le`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pd_projet`    (`projet_id`),
  KEY `idx_pd_categorie` (`categorie`),
  CONSTRAINT `fk_pd_projet` FOREIGN KEY (`projet_id`)  REFERENCES `projets`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_dep`    FOREIGN KEY (`depose_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 7. Activer le module PROJETS dans la table modules
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `modules` (`code`, `libelle`, `actif`)
VALUES ('PROJETS', 'Gestion des Projets', 1);

-- Permission VOIR et CREER pour le groupe DG (adapter groupe_id selon votre base)
-- À ajuster selon vos groupes réels :
-- INSERT INTO `groupes_permissions` (groupe_id, permission_id, accorde)
-- SELECT g.id, p.id, 1 FROM groupes_utilisateurs g, permissions p
-- JOIN modules m ON m.id = p.module_id
-- WHERE g.code = 'DG' AND m.code = 'PROJETS';

COMMIT;
