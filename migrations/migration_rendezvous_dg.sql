-- ============================================================
-- Module RENDEZVOUS_DG — Rendez-vous & visites du DG
-- Schéma complet (demandes, disponibilités, notifications).
-- Idempotent. Google Agenda = phase 2 (hors scope).
-- ============================================================
SET NAMES utf8mb4;
START TRANSACTION;

-- ── Demandes de rendez-vous ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `dg_rendezvous` (
  `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`                VARCHAR(30)  NOT NULL,
  `source`                   ENUM('INTERNE','PUBLIC') NOT NULL DEFAULT 'INTERNE',
  `type_rdv`                 ENUM('RENDEZVOUS','VISITE','AUTRE') NOT NULL DEFAULT 'RENDEZVOUS',
  `type_autre`               VARCHAR(150) DEFAULT NULL,
  `demandeur_utilisateur_id` INT UNSIGNED DEFAULT NULL,
  `demandeur_personnel_id`   INT UNSIGNED DEFAULT NULL,
  `nom`                      VARCHAR(100) NOT NULL,
  `prenoms`                  VARCHAR(150) NOT NULL,
  `email`                    VARCHAR(150) NOT NULL,
  `telephone`                VARCHAR(40)  DEFAULT NULL,
  `organisation`             VARCHAR(200) DEFAULT NULL,
  `fonction`                 VARCHAR(200) DEFAULT NULL,
  `objet`                    TEXT NOT NULL,
  `date_souhaitee`           DATE DEFAULT NULL,
  `creneau_souhaite`         TIME DEFAULT NULL,
  `note_demandeur`           TEXT DEFAULT NULL,
  `piece_jointe`             VARCHAR(500) DEFAULT NULL,
  `disponibilite_id`         INT UNSIGNED DEFAULT NULL,
  `statut`                   ENUM('EN_ATTENTE','CONFIRME','REFUSE','ANNULE','TERMINE')
                             NOT NULL DEFAULT 'EN_ATTENTE',
  `date_confirmee`           DATE DEFAULT NULL,
  `heure_debut`              TIME DEFAULT NULL,
  `heure_fin`                TIME DEFAULT NULL,
  `duree_minutes`            SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `lieu`                     VARCHAR(255) DEFAULT NULL,
  `hors_plage`               TINYINT(1) NOT NULL DEFAULT 0,
  `commentaire_gestion`      TEXT DEFAULT NULL,
  `motif_refus`              TEXT DEFAULT NULL,
  `motif_annulation`         TEXT DEFAULT NULL,
  `planifie_par`             INT UNSIGNED DEFAULT NULL,
  `planifie_le`              DATETIME DEFAULT NULL,
  `rappel_j1_envoye_le`      DATETIME DEFAULT NULL,
  `cree_le`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rdv_ref` (`reference`),
  KEY `idx_rdv_statut` (`statut`),
  KEY `idx_rdv_date` (`date_confirmee`),
  KEY `idx_rdv_email` (`email`),
  KEY `idx_rdv_user` (`demandeur_utilisateur_id`),
  KEY `idx_rdv_dispo` (`disponibilite_id`),
  KEY `idx_rdv_creneau` (`date_confirmee`, `heure_debut`, `statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colonnes ajoutées après installations antérieures
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dg_rendezvous' AND COLUMN_NAME = 'disponibilite_id');
SET @sql := IF(@col = 0,
  'ALTER TABLE `dg_rendezvous` ADD COLUMN `disponibilite_id` INT UNSIGNED DEFAULT NULL AFTER `piece_jointe`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dg_rendezvous' AND COLUMN_NAME = 'motif_annulation');
SET @sql := IF(@col = 0,
  'ALTER TABLE `dg_rendezvous` ADD COLUMN `motif_annulation` TEXT DEFAULT NULL AFTER `motif_refus`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── Disponibilités du DG ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dg_disponibilites` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_dispo`      ENUM('RECURRENT','PONCTUEL') NOT NULL DEFAULT 'RECURRENT',
  `jour_semaine`    TINYINT UNSIGNED DEFAULT NULL COMMENT '1=lundi … 7=dimanche',
  `date_ponctuelle` DATE DEFAULT NULL,
  `heure_debut`     TIME NOT NULL,
  `heure_fin`       TIME NOT NULL,
  `duree_minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `actif`           TINYINT(1) NOT NULL DEFAULT 1,
  `note`            VARCHAR(255) DEFAULT NULL,
  `cree_par`        INT UNSIGNED DEFAULT NULL,
  `cree_le`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dispo_type` (`type_dispo`, `actif`),
  KEY `idx_dispo_jour` (`jour_semaine`),
  KEY `idx_dispo_date` (`date_ponctuelle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dg_dispo_exceptions` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `disponibilite_id` INT UNSIGNED NOT NULL,
  `date_exception`   DATE NOT NULL,
  `motif`            VARCHAR(255) DEFAULT NULL,
  `cree_par`         INT UNSIGNED DEFAULT NULL,
  `cree_le`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dispo_exc` (`disponibilite_id`, `date_exception`),
  KEY `idx_exc_date` (`date_exception`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications internes ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `dg_rdv_notifications` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilisateur_id` INT UNSIGNED NOT NULL,
  `rendezvous_id`  INT UNSIGNED DEFAULT NULL,
  `titre`          VARCHAR(200) NOT NULL,
  `message`        TEXT NOT NULL,
  `lu`             TINYINT(1) NOT NULL DEFAULT 0,
  `cree_le`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`utilisateur_id`, `lu`),
  KEY `idx_notif_rdv` (`rendezvous_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Module + permissions ─────────────────────────────────────
INSERT INTO `modules` (`code`, `libelle`, `icone`, `url_base`, `ordre`, `actif`)
SELECT 'RENDEZVOUS_DG', 'Rendez-vous DG', 'fa-calendar-check', '/modules/rendezvous_dg/', 8, 1
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'RENDEZVOUS_DG');

SET @mid := (SELECT id FROM `modules` WHERE code = 'RENDEZVOUS_DG' LIMIT 1);

INSERT INTO `permissions` (`module_id`, `action`, `libelle`)
SELECT @mid, a.action, a.libelle FROM (
    SELECT 'VOIR' AS action, 'Consulter les rendez-vous DG' AS libelle
    UNION ALL SELECT 'CREER', 'Créer une demande de rendez-vous'
    UNION ALL SELECT 'VALIDER', 'Gérer disponibilités / confirmer / refuser'
    UNION ALL SELECT 'MODIFIER', 'Modifier une demande'
) a
WHERE @mid IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `permissions` p WHERE p.module_id = @mid AND p.action = a.action
  );

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
JOIN `permissions` p ON p.module_id = @mid AND p.action IN ('VALIDER','MODIFIER')
WHERE g.code IN ('SUPER_ADMIN','DG')
  AND NOT EXISTS (
      SELECT 1 FROM `groupes_permissions` gp
      WHERE gp.groupe_id = g.id AND gp.permission_id = p.id
  );

COMMIT;
