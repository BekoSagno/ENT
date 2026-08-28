-- ============================================================
-- MODULE COURRIERS — Migration SQL
-- Système de gestion des courriers numériques & physiques (CRICT-ENT)
-- À exécuter une seule fois sur la base `crict_ent`.
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1) Table principale : courriers (entrants & sortants)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `courriers` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CR-A-2026-0001 (Arrivée) / CR-D-2026-0001 (Départ)',
  `sens` enum('ENTRANT','SORTANT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ENTRANT',
  `canal` enum('PHYSIQUE','EMAIL','FAX','MAIN_PROPRE','AUTRE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHYSIQUE',
  `type_courrier` enum('LETTRE','NOTE','FACTURE','CONVOCATION','DEMANDE','RAPPORT','INVITATION','CIRCULAIRE','CONTRAT','AUTRE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LETTRE',
  `objet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text COLLATE utf8mb4_unicode_ci COMMENT 'Corps / résumé du courrier (ou corps de l''email)',

  -- Correspondant : expéditeur si ENTRANT, destinataire si SORTANT
  `correspondant_nom` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correspondant_organisation` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correspondant_email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correspondant_telephone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correspondant_adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,

  -- Dates
  `date_courrier` date DEFAULT NULL COMMENT 'Date figurant sur le courrier',
  `date_reception` datetime DEFAULT NULL COMMENT 'Date de réception / enregistrement',
  `date_echeance` date DEFAULT NULL COMMENT 'Délai de traitement attendu',

  -- Classement
  `priorite` enum('FAIBLE','NORMALE','HAUTE','URGENTE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NORMALE',
  `confidentialite` enum('NORMAL','CONFIDENTIEL','SECRET') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NORMAL',

  -- Orientation / affectation
  `service_id` int UNSIGNED DEFAULT NULL COMMENT 'Unité organisationnelle concernée',
  `affecte_a` int UNSIGNED DEFAULT NULL COMMENT 'Utilisateur en charge',
  `instruction` text COLLATE utf8mb4_unicode_ci COMMENT 'Annotation / instruction de la direction',

  -- Suivi du cycle de traitement
  `statut` enum('RECU','AFFECTE','EN_TRAITEMENT','TRAITE','ARCHIVE','CLOTURE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RECU',

  -- Suivi e-mail
  `email_message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_envoye` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Courrier sortant déjà transmis par email',

  -- Méta
  `enregistre_par` int UNSIGNED DEFAULT NULL,
  `traite_par` int UNSIGNED DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reference` (`reference`),
  KEY `idx_sens` (`sens`),
  KEY `idx_statut` (`statut`),
  KEY `idx_canal` (`canal`),
  KEY `idx_priorite` (`priorite`),
  KEY `idx_service` (`service_id`),
  KEY `idx_affecte` (`affecte_a`),
  KEY `idx_date_reception` (`date_reception`),
  KEY `idx_email_msgid` (`email_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2) Documents joints (scans, pièces jointes, réponses)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `courriers_documents` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `courrier_id` int UNSIGNED NOT NULL,
  `type_document` enum('SCAN','PIECE_JOINTE','REPONSE','AUTRE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SCAN',
  `nom_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_stockage` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin_fichier` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille_fichier` int UNSIGNED DEFAULT NULL,
  `extension` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `depose_par` int UNSIGNED DEFAULT NULL,
  `date_upload` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cd_courrier` (`courrier_id`),
  KEY `idx_cd_type` (`type_document`),
  KEY `fk_cd_depose` (`depose_par`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3) Journal de suivi / traçabilité du traitement
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `courriers_suivi` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `courrier_id` int UNSIGNED NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ENREGISTREMENT, AFFECTATION, TRAITEMENT, REPONSE, CLOTURE, ARCHIVAGE…',
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `ancien_statut` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nouveau_statut` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `par_utilisateur` int UNSIGNED DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cs_courrier` (`courrier_id`),
  KEY `fk_cs_user` (`par_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4) Enregistrement du module
-- ------------------------------------------------------------
INSERT INTO `modules` (`id`, `code`, `libelle`, `icone`, `url_base`, `ordre`, `actif`, `cree_le`)
VALUES (17, 'COURRIERS', 'Gestion des Courriers', 'fa-envelopes-bulk', '/modules/courriers/', 11, 1, NOW())
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`), `icone` = VALUES(`icone`), `url_base` = VALUES(`url_base`), `actif` = 1;

-- ------------------------------------------------------------
-- 5) Permissions du module (VOIR, CREER, MODIFIER, SUPPRIMER, VALIDER, EXPORTER)
--    VALIDER = traiter / clôturer / archiver un courrier
-- ------------------------------------------------------------
INSERT INTO `permissions` (`id`, `module_id`, `action`, `libelle`) VALUES
(85, 17, 'VOIR',      'Consulter – Gestion des Courriers'),
(86, 17, 'CREER',     'Enregistrer un courrier – Gestion des Courriers'),
(87, 17, 'MODIFIER',  'Modifier / affecter – Gestion des Courriers'),
(88, 17, 'SUPPRIMER', 'Supprimer – Gestion des Courriers'),
(89, 17, 'VALIDER',   'Traiter / clôturer / archiver – Gestion des Courriers'),
(90, 17, 'EXPORTER',  'Exporter – Gestion des Courriers')
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);

-- ------------------------------------------------------------
-- 6) Attribution des permissions par groupe
--    SUPER_ADMIN (1) a déjà accès total automatiquement.
--    SS (3) = Secrétaire Scientifique / Assistant(e) de direction : accès complet.
--    DG (2) = Directeur Général : consultation + validation/clôture.
-- ------------------------------------------------------------
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`, `cree_le`) VALUES
-- Secrétaire Scientifique : gestion complète des courriers
(3, 85, 1, NOW()), (3, 86, 1, NOW()), (3, 87, 1, NOW()), (3, 88, 1, NOW()), (3, 89, 1, NOW()), (3, 90, 1, NOW()),
-- Directeur Général : voir + valider/clôturer + exporter
(2, 85, 1, NOW()), (2, 89, 1, NOW()), (2, 90, 1, NOW());

SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- Fin de la migration MODULE COURRIERS
-- ============================================================
