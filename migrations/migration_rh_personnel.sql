-- ============================================================
-- CRICT — Migration RH / Personnel (socle)
-- Fichier : migration_rh_personnel.sql
--
-- Contenu : groupe RH, fiches personnel, CV, organigramme, contrats
-- Mes demandes (congés, absences, missions) → rh_demandes_unifie.sql
-- Ordre : 1) ce fichier  2) rh_demandes_unifie.sql
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. GROUPE RH ────────────────────────────────────────────
INSERT INTO `groupes_utilisateurs` (`code`, `libelle`, `departement`, `description`, `niveau`, `actif`)
SELECT 'RH', 'Gestionnaire RH', 'ADMIN_GENERALE', 'Gestion des ressources humaines', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM `groupes_utilisateurs` WHERE `code` = 'RH');

-- ── 2. PERSONNEL (socle — table maîtresse) ──────────────────
CREATE TABLE IF NOT EXISTS `personnel` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilisateur_id`         INT UNSIGNED NOT NULL,
  `matricule_rh`           VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lieu_naissance`         VARCHAR(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationalite`            VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `situation_matrimoniale` ENUM('CELIBATAIRE','MARIE','DIVORCE','VEUF') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_enfants`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `fonction`               VARCHAR(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_embauche`          DATE DEFAULT NULL,
  `type_contrat`           ENUM('CDD','CDI','STAGIAIRE','CONSULTANT','VACATAIRE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut_rh`              ENUM('ACTIF','CONGE','MISSION','SUSPENDU','DEMISSION','RETRAITE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIF',
  `cree_par`               INT UNSIGNED DEFAULT NULL,
  `cree_le`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utilisateur` (`utilisateur_id`),
  UNIQUE KEY `uq_matricule_rh` (`matricule_rh`),
  KEY `idx_statut_rh` (`statut_rh`),
  KEY `fk_personnel_createur` (`cree_par`),
  CONSTRAINT `fk_personnel_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_createur`    FOREIGN KEY (`cree_par`)       REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_cv` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` INT UNSIGNED NOT NULL,
  `resume_professionnel` TEXT COLLATE utf8mb4_unicode_ci,
  `fichier_cv` VARCHAR(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modifie_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cv_personnel` (`personnel_id`),
  CONSTRAINT `fk_cv_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_formations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` INT UNSIGNED NOT NULL,
  `diplome` VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `etablissement` VARCHAR(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pays` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_debut` DATE DEFAULT NULL,
  `date_fin` DATE DEFAULT NULL,
  `fichier_diplome` VARCHAR(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_formation_personnel` (`personnel_id`),
  CONSTRAINT `fk_formation_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_experiences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` INT UNSIGNED NOT NULL,
  `organisation` VARCHAR(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `poste` VARCHAR(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_debut` DATE DEFAULT NULL,
  `date_fin` DATE DEFAULT NULL,
  `description` TEXT COLLATE utf8mb4_unicode_ci,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_experience_personnel` (`personnel_id`),
  CONSTRAINT `fk_experience_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_certifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` INT UNSIGNED NOT NULL,
  `certification` VARCHAR(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organisme` VARCHAR(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_obtention` DATE DEFAULT NULL,
  `date_expiration` DATE DEFAULT NULL,
  `document` VARCHAR(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_certif_personnel` (`personnel_id`),
  CONSTRAINT `fk_certif_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `competences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_competence_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_competences` (
  `personnel_id` INT UNSIGNED NOT NULL,
  `competence_id` INT UNSIGNED NOT NULL,
  `niveau` ENUM('DEBUTANT','INTERMEDIAIRE','AVANCE','EXPERT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INTERMEDIAIRE',
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`personnel_id`,`competence_id`),
  KEY `fk_pc_competence` (`competence_id`),
  CONSTRAINT `fk_pc_personnel`  FOREIGN KEY (`personnel_id`)  REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pc_competence` FOREIGN KEY (`competence_id`) REFERENCES `competences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permission MODIFIER_SENSIBLE
INSERT INTO `permissions` (`module_id`, `action`, `libelle`)
SELECT 6, 'MODIFIER_SENSIBLE', 'Modifier les données RH sensibles (contrat, statut, affectation)'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module_id` = 6 AND `action` = 'MODIFIER_SENSIBLE');

INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`)
SELECT g.id, p.id, 1
FROM `groupes_utilisateurs` g
JOIN `permissions` p ON p.module_id = 6
WHERE g.code IN ('RH', 'DG');

-- Profil RH pour tout utilisateur sans fiche
INSERT INTO `personnel` (`utilisateur_id`, `statut_rh`, `cree_par`)
SELECT u.id, 'ACTIF', u.cree_par
FROM `utilisateurs` u
LEFT JOIN `personnel` p ON p.utilisateur_id = u.id
WHERE p.id IS NULL;

-- ── 3. ORGANIGRAMME ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `grades` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `libelle` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie` ENUM('ACADEMIQUE','TECHNIQUE','ADMINISTRATIF','AUTRE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AUTRE',
  `niveau` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grade_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `unites_organisationnelles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom` VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` ENUM('DIRECTION','DEPARTEMENT','LABORATOIRE','SERVICE','EQUIPE','AUTRE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SERVICE',
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `responsable_id` INT UNSIGNED DEFAULT NULL,
  `description` TEXT COLLATE utf8mb4_unicode_ci,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unite_code` (`code`),
  KEY `idx_parent` (`parent_id`),
  KEY `fk_unite_responsable` (`responsable_id`),
  CONSTRAINT `fk_unite_parent`      FOREIGN KEY (`parent_id`)      REFERENCES `unites_organisationnelles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_unite_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `postes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intitule` VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unite_id` INT UNSIGNED DEFAULT NULL,
  `grade_id` INT UNSIGNED DEFAULT NULL,
  `description` TEXT COLLATE utf8mb4_unicode_ci,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_poste_code` (`code`),
  KEY `fk_poste_unite` (`unite_id`),
  KEY `fk_poste_grade` (`grade_id`),
  CONSTRAINT `fk_poste_unite` FOREIGN KEY (`unite_id`) REFERENCES `unites_organisationnelles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_poste_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colonnes organigramme sur personnel (idempotent)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personnel' AND COLUMN_NAME='poste_id');
SET @sql := IF(@col=0, "ALTER TABLE `personnel` ADD COLUMN `poste_id` INT UNSIGNED DEFAULT NULL AFTER `fonction`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personnel' AND COLUMN_NAME='grade_id');
SET @sql := IF(@col=0, "ALTER TABLE `personnel` ADD COLUMN `grade_id` INT UNSIGNED DEFAULT NULL AFTER `poste_id`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personnel' AND COLUMN_NAME='responsable_id');
SET @sql := IF(@col=0, "ALTER TABLE `personnel` ADD COLUMN `responsable_id` INT UNSIGNED DEFAULT NULL AFTER `grade_id`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 4. CONTRATS (dossier RH) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `contrats` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` INT UNSIGNED NOT NULL,
  `reference` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_contrat` ENUM('CDD','CDI','STAGIAIRE','CONSULTANT','VACATAIRE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CDD',
  `intitule` VARCHAR(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE DEFAULT NULL,
  `statut` ENUM('EN_COURS','EXPIRE','RESILIE','RENOUVELE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EN_COURS',
  `document` VARCHAR(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` TEXT COLLATE utf8mb4_unicode_ci,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_contrat_personnel` (`personnel_id`),
  KEY `idx_contrat_fin` (`date_fin`),
  CONSTRAINT `fk_contrat_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. DONNÉES DE RÉFÉRENCE (grades) ────────────────────────
INSERT IGNORE INTO `grades` (`code`, `libelle`, `categorie`, `niveau`) VALUES
('DR', 'Directeur de recherche', 'ACADEMIQUE', 6),
('MR', 'Maître de recherche', 'ACADEMIQUE', 5),
('CR', 'Chargé de recherche', 'ACADEMIQUE', 4),
('AR', 'Attaché de recherche', 'ACADEMIQUE', 3),
('ASSIST', 'Assistant de recherche', 'ACADEMIQUE', 2),
('ING', 'Ingénieur', 'TECHNIQUE', 3),
('TECH', 'Technicien', 'TECHNIQUE', 2),
('CADRE', 'Cadre administratif', 'ADMINISTRATIF', 3),
('AGENT', 'Agent administratif', 'ADMINISTRATIF', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Fin migration RH / Personnel
-- Ensuite : exécuter rh_demandes_unifie.sql (Mes demandes)
-- ============================================================
