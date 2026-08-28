-- ============================================================
-- migration_subventions_v2.sql — Module Subventions CRICT
-- SANS les tables depenses/pieces_jointes
-- (remplacées par dossiers_depenses_daf dans le module Marchés)
-- ============================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ────────────────────────────────────────────────────────────
-- 1. CATÉGORIES DE DÉPENSES
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories_depenses` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `code_categorie`  VARCHAR(10)      NOT NULL COMMENT 'I, II, III, IV…',
  `libelle`         VARCHAR(200)     NOT NULL,
  `description`     TEXT             DEFAULT NULL,
  `ordre_affichage` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `actif`           TINYINT(1)       NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code_categorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `categories_depenses`
  (`code_categorie`,`libelle`,`ordre_affichage`) VALUES
  ('I',   'Dépenses de personnel',                        1),
  ('II',  'Achat de Fournitures et Biens Courants',       2),
  ('III', 'Consommations (Eaux, Électricité, Télécomm)',  3),
  ('IV',  'Entretiens et Réparations',                    4);


-- ────────────────────────────────────────────────────────────
-- 2. CAISSES DE SUBVENTION
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `caisses_subvention` (
  `id`                  INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `code_caisse`         VARCHAR(20)       NOT NULL,
  `libelle`             VARCHAR(150)      NOT NULL,
  `description`         TEXT              DEFAULT NULL,
  `source_financement`  VARCHAR(200)      DEFAULT NULL,
  `montant_initial`     DECIMAL(18,2)     NOT NULL,
  `montant_utilise`     DECIMAL(18,2)     NOT NULL DEFAULT 0.00,
  `montant_disponible`  DECIMAL(18,2)     NOT NULL,
  `trimestre`           TINYINT UNSIGNED  NOT NULL,
  `annee`               SMALLINT UNSIGNED NOT NULL,
  `date_debut`          DATE              DEFAULT NULL,
  `date_fin`            DATE              DEFAULT NULL,
  `statut`              ENUM('active','epuisee','cloturee','suspendue')
                                          NOT NULL DEFAULT 'active',
  `cree_par`            INT UNSIGNED      DEFAULT NULL,
  `date_creation`       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code`     (`code_caisse`),
  KEY `idx_annee_trim`     (`annee`,`trimestre`),
  KEY `idx_statut`         (`statut`),
  CONSTRAINT `fk_cs_cree`  FOREIGN KEY (`cree_par`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 3. LIGNES BUDGÉTAIRES PAR CAISSE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lignes_budgetaires` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `caisse_id`        INT UNSIGNED  NOT NULL,
  `categorie_id`     INT UNSIGNED  NOT NULL,
  `montant_alloue`   DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `montant_consomme` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `notes`            TEXT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caisse_cat` (`caisse_id`,`categorie_id`),
  CONSTRAINT `fk_lb_caisse`    FOREIGN KEY (`caisse_id`)    REFERENCES `caisses_subvention`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lb_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories_depenses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 4. DOSSIERS D'EXÉCUTION DES DÉPENSES (DAF)
--    Wizard 6 étapes : 3 offres → fournisseur retenu → montants → exécution
--    Ce tableau remplace les tables depenses + depenses_pieces_jointes
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dossiers_depenses_daf` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `numero_dossier`    VARCHAR(30)      NOT NULL,
  `libelle`           VARCHAR(300)     DEFAULT NULL,
  `categorie_id`      INT UNSIGNED     DEFAULT NULL COMMENT 'Catégorie de dépense',
  `caisse_id`         INT UNSIGNED     DEFAULT NULL COMMENT 'Caisse défalquée lors de l\'exécution',

  -- Fournisseur 1
  `fournisseur_1_id`  INT UNSIGNED     DEFAULT NULL,
  `fournisseur_1_nom` VARCHAR(200)     DEFAULT NULL,
  `facture_1_path`    VARCHAR(500)     DEFAULT NULL,
  `f1_rccm_path`      VARCHAR(500)     DEFAULT NULL,
  `f1_nif_path`       VARCHAR(500)     DEFAULT NULL,
  `f1_quittance_path` VARCHAR(500)     DEFAULT NULL,
  `f1_quitus_path`    VARCHAR(500)     DEFAULT NULL,
  `motif_f1`          TEXT             DEFAULT NULL,

  -- Fournisseur 2
  `fournisseur_2_id`  INT UNSIGNED     DEFAULT NULL,
  `fournisseur_2_nom` VARCHAR(200)     DEFAULT NULL,
  `facture_2_path`    VARCHAR(500)     DEFAULT NULL,
  `f2_rccm_path`      VARCHAR(500)     DEFAULT NULL,
  `f2_nif_path`       VARCHAR(500)     DEFAULT NULL,
  `f2_quittance_path` VARCHAR(500)     DEFAULT NULL,
  `f2_quitus_path`    VARCHAR(500)     DEFAULT NULL,
  `motif_f2`          TEXT             DEFAULT NULL,

  -- Fournisseur 3
  `fournisseur_3_id`  INT UNSIGNED     DEFAULT NULL,
  `fournisseur_3_nom` VARCHAR(200)     DEFAULT NULL,
  `facture_3_path`    VARCHAR(500)     DEFAULT NULL,
  `f3_rccm_path`      VARCHAR(500)     DEFAULT NULL,
  `f3_nif_path`       VARCHAR(500)     DEFAULT NULL,
  `f3_quittance_path` VARCHAR(500)     DEFAULT NULL,
  `f3_quitus_path`    VARCHAR(500)     DEFAULT NULL,
  `motif_f3`          TEXT             DEFAULT NULL,

  -- Décision
  `fournisseur_favori` TINYINT UNSIGNED DEFAULT NULL COMMENT '1, 2 ou 3',

  -- Montants
  `montant_ht`        DECIMAL(18,2)    DEFAULT NULL,
  `montant_taxe`      DECIMAL(18,2)    DEFAULT NULL,
  `montant_ttc`       DECIMAL(18,2)    DEFAULT NULL,
  `regime_fiscal`     ENUM('tva','precompte','sans_tva') DEFAULT NULL,

  -- Période d'exécution
  `periode_annee`     SMALLINT UNSIGNED DEFAULT NULL,
  `periode_trimestre` TINYINT UNSIGNED  DEFAULT NULL,

  -- Pièces d'exécution
  `exec_lettre_paiement_path` VARCHAR(500) DEFAULT NULL,
  `exec_bon_engagement_path`  VARCHAR(500) DEFAULT NULL,
  `exec_mandat_paiement_path` VARCHAR(500) DEFAULT NULL,
  `exec_liquidation_path`     VARCHAR(500) DEFAULT NULL,
  `exec_pieces_completes`     TINYINT(1)   NOT NULL DEFAULT 0,

  -- Statut workflow
  `statut`            ENUM('brouillon','en_attente_execution','execute')
                                       NOT NULL DEFAULT 'brouillon',

  -- Audit
  `cree_par`          INT UNSIGNED     DEFAULT NULL,
  `execute_par`       INT UNSIGNED     DEFAULT NULL,
  `date_creation`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_execution`    DATETIME         DEFAULT NULL,
  `modifie_le`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_numero`       (`numero_dossier`),
  KEY `idx_daf_statut`         (`statut`),
  KEY `idx_daf_caisse`         (`caisse_id`),
  KEY `idx_daf_categorie`      (`categorie_id`),
  KEY `idx_daf_cree`           (`cree_par`),

  CONSTRAINT `fk_daf_caisse`    FOREIGN KEY (`caisse_id`)    REFERENCES `caisses_subvention`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_daf_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories_depenses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_daf_f1`        FOREIGN KEY (`fournisseur_1_id`) REFERENCES `fournisseurs`    (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_daf_f2`        FOREIGN KEY (`fournisseur_2_id`) REFERENCES `fournisseurs`    (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_daf_f3`        FOREIGN KEY (`fournisseur_3_id`) REFERENCES `fournisseurs`    (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_daf_cree`      FOREIGN KEY (`cree_par`)     REFERENCES `utilisateurs`        (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_daf_execute`   FOREIGN KEY (`execute_par`)  REFERENCES `utilisateurs`        (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- 5. VUE RÉSUMÉ CAISSES
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW `v_caisses_resume` AS
SELECT
    c.*,
    COUNT(DISTINCT d.id)                                            AS nb_dossiers,
    SUM(CASE WHEN d.statut='execute' THEN d.montant_ttc ELSE 0 END) AS total_execute,
    ROUND((c.montant_utilise / NULLIF(c.montant_initial,0))*100,1)  AS taux_consommation
FROM caisses_subvention c
LEFT JOIN dossiers_depenses_daf d ON d.caisse_id = c.id
GROUP BY c.id;


-- ────────────────────────────────────────────────────────────
-- 6. Modules + Permissions
-- ────────────────────────────────────────────────────────────

-- Module SUBVENTIONS
INSERT IGNORE INTO `modules` (`code`,`libelle`,`icone`,`url_base`,`ordre`,`actif`)
VALUES ('SUBVENTIONS','Gestion des Subventions','fa-hand-holding-usd','/modules/subventions/',5,1);

-- Module MARCHES (exécution des dépenses DAF)
INSERT IGNORE INTO `modules` (`code`,`libelle`,`icone`,`url_base`,`ordre`,`actif`)
VALUES ('MARCHES','Passation des Marchés Publics','fa-file-contract','/modules/marches/',4,1);

-- Permissions SUBVENTIONS
SET @sid = (SELECT id FROM modules WHERE code='SUBVENTIONS' LIMIT 1);
INSERT IGNORE INTO `permissions` (`module_id`,`action`,`libelle`) VALUES
(@sid,'VOIR',      'Consulter – Subventions'),
(@sid,'CREER',     'Créer – Subventions'),
(@sid,'MODIFIER',  'Modifier – Subventions'),
(@sid,'SUPPRIMER', 'Supprimer – Subventions'),
(@sid,'VALIDER',   'Valider – Subventions'),
(@sid,'EXPORTER',  'Exporter – Subventions');

-- Permissions MARCHES
SET @mid = (SELECT id FROM modules WHERE code='MARCHES' LIMIT 1);
INSERT IGNORE INTO `permissions` (`module_id`,`action`,`libelle`) VALUES
(@mid,'VOIR',      'Consulter – Marchés Publics'),
(@mid,'CREER',     'Créer – Marchés Publics'),
(@mid,'MODIFIER',  'Modifier – Marchés Publics'),
(@mid,'SUPPRIMER', 'Supprimer – Marchés Publics'),
(@mid,'VALIDER',   'Valider – Marchés Publics'),
(@mid,'EXPORTER',  'Exporter – Marchés Publics');

-- SUBVENTIONS : SUPER_ADMIN + DG + DAF = tout
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`,`permission_id`,`accorde`)
SELECT g.id, p.id, 1 FROM groupes_utilisateurs g, permissions p
JOIN modules m ON m.id=p.module_id
WHERE g.code IN ('SUPER_ADMIN','DG','DAF') AND m.code='SUBVENTIONS';

-- SUBVENTIONS : CF + COMPTABLE = VOIR + EXPORTER
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`,`permission_id`,`accorde`)
SELECT g.id, p.id, 1 FROM groupes_utilisateurs g, permissions p
JOIN modules m ON m.id=p.module_id
WHERE g.code IN ('CF','COMPTABLE','PRMP') AND m.code='SUBVENTIONS' AND p.action IN ('VOIR','EXPORTER');

-- SUBVENTIONS : autres = VOIR
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`,`permission_id`,`accorde`)
SELECT g.id, p.id, 1 FROM groupes_utilisateurs g, permissions p
JOIN modules m ON m.id=p.module_id
WHERE g.code NOT IN ('SUPER_ADMIN','DG','DAF','CF','COMPTABLE','PRMP')
  AND m.code='SUBVENTIONS' AND p.action='VOIR';

-- MARCHES : SUPER_ADMIN + DG + DAF + PRMP = tout
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`,`permission_id`,`accorde`)
SELECT g.id, p.id, 1 FROM groupes_utilisateurs g, permissions p
JOIN modules m ON m.id=p.module_id
WHERE g.code IN ('SUPER_ADMIN','DG','DAF','PRMP') AND m.code='MARCHES';

-- MARCHES : autres = VOIR
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`,`permission_id`,`accorde`)
SELECT g.id, p.id, 1 FROM groupes_utilisateurs g, permissions p
JOIN modules m ON m.id=p.module_id
WHERE g.code NOT IN ('SUPER_ADMIN','DG','DAF','PRMP')
  AND m.code='MARCHES' AND p.action='VOIR';

COMMIT;
