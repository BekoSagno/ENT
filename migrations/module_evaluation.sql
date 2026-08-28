-- ============================================================
-- module_evaluation_complet.sql
-- Module ÉVALUATION (code ENT : IDENTITE_CRICT)
--
-- Remplace :
--   • module_identite_crict.sql
--   • module_identite_crict_complet.sql
--   • module_identite_crict_preremplissage.sql
--   • module_evaluation_referentiels.sql
--
-- PRINCIPE :
--   • CREATE TABLE IF NOT EXISTS uniquement (aucun DROP).
--   • Ré-exécutable sans effet de bord sur les données saisies.
--   • Préremplissage : uniquement si champs vides (ne écrase pas).
--   • Grille complète : 6 champs actifs + référentiels DGRS / Auto-évaluation.
-- Base : crict_ent
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. TABLES DE RÉFÉRENCE ──────────────────────────────────

CREATE TABLE IF NOT EXISTS `identite_champs` (
  `id`            tinyint UNSIGNED NOT NULL,
  `code`          varchar(10)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle`       varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description`   varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icone`         varchar(50)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa-folder',
  `type_notation` enum('DISPOSITIF','RH','MIXTE','SCORE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DISPOSITIF',
  `ordre`         tinyint UNSIGNED NOT NULL DEFAULT 0,
  `actif`         tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_identite_champ_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `identite_echelles` (
  `id`            smallint UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_notation` enum('DISPOSITIF','RH','MIXTE','SCORE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `score`         tinyint UNSIGNED NOT NULL,
  `libelle`       varchar(80)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `signification` text         COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_echelle` (`type_notation`,`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `identite_criteres` (
  `id`                   smallint UNSIGNED NOT NULL AUTO_INCREMENT,
  `champ_id`             tinyint UNSIGNED NOT NULL,
  `code`                 varchar(10)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle`              varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `indicateurs_cles`     text         COLLATE utf8mb4_unicode_ci,
  `sources_verification` text         COLLATE utf8mb4_unicode_ci,
  `type_saisie`          enum('SCORE','NOMBRE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SCORE',
  `ordre`                tinyint UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_critere_code` (`code`),
  KEY `fk_critere_champ` (`champ_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `identite_identification` (
  `cle`         varchar(40)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle`     varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur`      text         COLLATE utf8mb4_unicode_ci,
  `ordre`       tinyint UNSIGNED NOT NULL DEFAULT 0,
  `modifie_par` int UNSIGNED DEFAULT NULL,
  `modifie_le`  datetime DEFAULT NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `identite_contenu` (
  `critere_id`           smallint UNSIGNED NOT NULL,
  `reponse_officielle`   text COLLATE utf8mb4_unicode_ci,
  `preuves_disponibles`  text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score`                tinyint UNSIGNED DEFAULT NULL,
  `valeur_numerique`     decimal(12,2) DEFAULT NULL,
  `modifie_par`          int UNSIGNED DEFAULT NULL,
  `modifie_le`           datetime DEFAULT NULL,
  PRIMARY KEY (`critere_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `identite_observations` (
  `champ_id`      tinyint UNSIGNED NOT NULL,
  `forces`        text COLLATE utf8mb4_unicode_ci,
  `faiblesses`    text COLLATE utf8mb4_unicode_ci,
  `niveau_global` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modifie_par`   int UNSIGNED DEFAULT NULL,
  `modifie_le`    datetime DEFAULT NULL,
  PRIMARY KEY (`champ_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `identite_pieces` (
  `id`            int UNSIGNED NOT NULL AUTO_INCREMENT,
  `critere_id`    smallint UNSIGNED NOT NULL,
  `nom_original`  varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_stockage`  varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin_fichier` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_mime`     varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille_fichier` int UNSIGNED DEFAULT NULL,
  `depose_par`    int UNSIGNED DEFAULT NULL,
  `cree_le`       datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_piece_critere` (`critere_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. TABLES D'EXTENSION (SWOT, indicateurs, recommandations) ──

CREATE TABLE IF NOT EXISTS `identite_indicateurs` (
  `cle`     varchar(50)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur`  varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordre`   tinyint UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `identite_swot` (
  `ligne`        tinyint UNSIGNED NOT NULL,
  `forces`       text COLLATE utf8mb4_unicode_ci,
  `faiblesses`   text COLLATE utf8mb4_unicode_ci,
  `opportunites` text COLLATE utf8mb4_unicode_ci,
  `menaces`      text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`ligne`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `identite_recommandations` (
  `id`      tinyint UNSIGNED NOT NULL DEFAULT 1,
  `contenu` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. RÉFÉRENTIELS D'ÉVALUATION (DGRS, Auto-évaluation CIAQ) ──

CREATE TABLE IF NOT EXISTS `identite_referentiel_contenu` (
  `referentiel` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL
      COMMENT 'DGRS | AUTO_EVALUATION_ANAQ',
  `critere_id`  smallint UNSIGNED NOT NULL,
  `score`       tinyint UNSIGNED DEFAULT NULL COMMENT 'Note 0 à 5',
  `commentaire` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modifie_par` int UNSIGNED DEFAULT NULL,
  `modifie_le`  datetime DEFAULT NULL,
  PRIMARY KEY (`referentiel`,`critere_id`),
  KEY `idx_ref_critere` (`critere_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. MISE À NIVEAU SI TABLE ANCIENNE (sans preuves_disponibles) ──

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='identite_contenu' AND COLUMN_NAME='preuves_disponibles');
SET @sql := IF(@col=0,
  "ALTER TABLE `identite_contenu` ADD COLUMN `preuves_disponibles` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `reponse_officielle`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 5. DONNÉES DE RÉFÉRENCE ─────────────────────────────────

INSERT INTO `identite_champs` (`id`, `code`, `libelle`, `description`, `icone`, `type_notation`, `ordre`, `actif`) VALUES
(1, 'CHAMP1', 'Gouvernance & pilotage institutionnel', 'Cadre légal, organigramme, gouvernance, plan stratégique…', 'fa-landmark', 'DISPOSITIF', 1, 1),
(2, 'CHAMP2', 'Vie scientifique & performance de la recherche', 'Axes, projets, publications, partenariats…', 'fa-flask', 'DISPOSITIF', 2, 1),
(3, 'CHAMP3', 'Ressources humaines', 'Effectifs, qualification, formation, GPEC…', 'fa-users-gear', 'RH', 3, 1),
(4, 'CHAMP4', 'Infrastructures & équipements', 'Laboratoires, équipements, connectivité, HSE…', 'fa-building', 'MIXTE', 4, 1),
(5, 'CHAMP5', 'Financement & gestion financière', 'Budget, partenaires financiers, transparence…', 'fa-coins', 'SCORE', 5, 1),
(6, 'CHAMP6', 'Innovation, partenariats & impact', 'Valorisation, politiques publiques, rayonnement…', 'fa-lightbulb', 'SCORE', 6, 1)
ON DUPLICATE KEY UPDATE
  `libelle` = VALUES(`libelle`), `description` = VALUES(`description`),
  `icone` = VALUES(`icone`), `type_notation` = VALUES(`type_notation`), `actif` = 1;

UPDATE `identite_champs` SET `actif` = 1;

INSERT INTO `identite_echelles` (`type_notation`, `score`, `libelle`, `signification`) VALUES
('DISPOSITIF', 0, 'Inexistant', 'Aucun dispositif ou preuve disponible'),
('DISPOSITIF', 1, 'Très faible', 'Dispositif embryonnaire ou non fonctionnel'),
('DISPOSITIF', 2, 'Faible', 'Dispositif existant mais insuffisant'),
('DISPOSITIF', 3, 'Moyen', 'Dispositif fonctionnel mais perfectible'),
('DISPOSITIF', 4, 'Bon', 'Dispositif satisfaisant et opérationnel'),
('DISPOSITIF', 5, 'Excellent', 'Bonne pratique institutionnalisée'),
('RH', 0, 'Inexistant', 'Absence d''organisation administrative fonctionnelle.'),
('RH', 1, 'Très faible', 'Les insuffisances organisationnelles compromettent les activités de l''institution.'),
('RH', 2, 'Faible', 'Le fonctionnement administratif est perturbé par un déficit important de ressources humaines.'),
('RH', 3, 'Moyen / Satisfaisant', 'Les structures existent mais présentent des insuffisances importantes en personnel ou en organisation.'),
('RH', 4, 'Très satisfaisant', 'L''organisation fonctionne correctement malgré quelques insuffisances limitées.'),
('RH', 5, 'Excellent', 'L''organisation administrative est structurée, les postes sont pourvus et les ressources humaines sont qualifiées et suffisantes.'),
('SCORE', 0, 'Inexistant', 'Aucun dispositif ou preuve disponible'),
('SCORE', 1, 'Très faible', 'Dispositif embryonnaire ou non fonctionnel'),
('SCORE', 2, 'Faible', 'Dispositif existant mais insuffisant'),
('SCORE', 3, 'Moyen', 'Dispositif fonctionnel mais perfectible'),
('SCORE', 4, 'Bon', 'Dispositif satisfaisant et opérationnel'),
('SCORE', 5, 'Excellent', 'Bonne pratique institutionnalisée')
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`), `signification` = VALUES(`signification`);

INSERT INTO `identite_identification` (`cle`, `libelle`, `valeur`, `ordre`) VALUES
('denomination',   'Dénomination',                            NULL, 1),
('domaines',       'Domaines d''intervention',                NULL, 2),
('axes_recherche', 'Principaux axes de recherche',            NULL, 3),
('adresse',        'Localité et adresse',                     NULL, 4),
('site_web',       'Site web fonctionnel de l''institution',  NULL, 5),
('contact',        'Principal contact (téléphone, courriel)', NULL, 6)
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);

-- Critères Champ 1
INSERT INTO `identite_criteres` (`champ_id`, `code`, `libelle`, `indicateurs_cles`, `sources_verification`, `type_saisie`, `ordre`) VALUES
(1,'1.1','Existence d''un cadre légal','Acte de création, statut juridique, textes organiques','Décrets, arrêtés, règlement intérieur','SCORE',1),
(1,'1.2','Existence d''un organigramme fonctionnel','Organigramme validé et appliqué','Organigramme officiel','SCORE',2),
(1,'1.3','Fonctionnement des organes de gouvernance','Conseil scientifique, CA, réunions régulières','PV, décisions','SCORE',3),
(1,'1.4','Existence d''un plan stratégique','Plan aligné aux priorités nationales','Plan stratégique','SCORE',4),
(1,'1.5','Dispositif de suivi-évaluation','Mécanisme interne fonctionnel','Rapports, outils de suivi','SCORE',5),
(1,'1.6','Assurance qualité','Cellule AQ, autoévaluation, plans d''amélioration','Rapports AQ','SCORE',6),
(1,'1.7','Politique d''éthique et intégrité scientifique','Comité éthique, code déontologique','Charte, décisions','SCORE',7),
(1,'1.8','Communication institutionnelle','Site web, visibilité scientifique, diffusion','Site, réseaux sociaux','SCORE',8)
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`), `indicateurs_cles` = VALUES(`indicateurs_cles`), `sources_verification` = VALUES(`sources_verification`);

-- Critères Champs 2 à 6
INSERT INTO `identite_criteres` (`champ_id`, `code`, `libelle`, `indicateurs_cles`, `sources_verification`, `type_saisie`, `ordre`) VALUES
(2,'2.1','Cohérence des axes de recherche','Alignement avec les priorités nationales','Agendas scientifiques','SCORE',1),
(2,'2.2','Existence de programmes/projets actifs','Nombre de projets exécutés','Rapports de projets','SCORE',2),
(2,'2.3','Dimension pluridisciplinaire','Collaboration entre disciplines','Programmes de recherche','SCORE',3),
(2,'2.4','Production scientifique','Articles, ouvrages, communications','Liste des publications','SCORE',4),
(2,'2.5','Publications indexées','Présence dans Scopus, WoS, DOAJ','Bases de données','SCORE',5),
(2,'2.6','Encadrement scientifique','Masters et doctorats encadrés','Registres académiques','SCORE',6),
(2,'2.7','Partenariats scientifiques','Coopérations nationales et internationales','Conventions','SCORE',7),
(2,'2.8','Innovation et valorisation','Brevets, prototypes, innovations','Rapports techniques','SCORE',8),
(2,'2.9','Diffusion des résultats','Colloques, vulgarisation, médias','Rapports d''activités','SCORE',9),
(3,'3.1','Adéquation profils/missions','Qualification des chercheurs','CV, listes du personnel','SCORE',1),
(3,'3.2','Suffisance des effectifs','Ratio chercheurs/projets','Base RH','SCORE',2),
(3,'3.3','Présence de chercheurs seniors','Rang magistral, HDR','Dossiers administratifs','SCORE',3),
(3,'3.4','Formation continue','Plan de renforcement des capacités','Plan de formation','SCORE',4),
(3,'3.5','Gestion prévisionnelle des RH','GPEC disponible','Documents RH','SCORE',5),
(3,'3.6','Disponibilité du personnel technique','Techniciens, ingénieurs de labo','Organigramme','SCORE',6),
(3,'3.7','Motivation et stabilité du personnel','Rotation, satisfaction','Enquêtes internes','SCORE',7),
(4,'4.1','Disponibilité des laboratoires','Nombre et fonctionnalité','Visites terrain','NOMBRE',1),
(4,'4.2','État des équipements scientifiques','Fonctionnels / non fonctionnels','Inventaire','NOMBRE',2),
(4,'4.3','Maintenance des équipements','Plan de maintenance','Rapports techniques','NOMBRE',3),
(4,'4.4','Connectivité et numérique','Internet, bases de données','Observation','NOMBRE',4),
(4,'4.5','Sécurité / HSE','Politique sécurité/laboratoire','Rapports HSE','SCORE',5),
(4,'4.6','Moyens logistiques','Véhicules, groupes électrogènes','Inventaire','SCORE',6),
(4,'4.7','Documentation scientifique','Bibliothèque, plateformes','Abonnements','SCORE',7),
(5,'5.1','Stratégie de mobilisation des ressources','Diversification des financements','Plans financiers','SCORE',1),
(5,'5.2','Niveau de financement des activités','Budget recherche','Budget annuel','SCORE',2),
(5,'5.3','Diversité des partenaires financiers','Public / privé / international','Conventions','SCORE',3),
(5,'5.4','Procédures financières','Manuel de procédures','Audit','SCORE',4),
(5,'5.5','Transparence financière','Rapports financiers disponibles','États financiers','SCORE',5),
(5,'5.6','Viabilité économique','Pérennité des financements','Analyse financière','SCORE',6),
(6,'6.1','Culture de l''innovation','Activités de sensibilisation','Rapports','SCORE',1),
(6,'6.2','Collaboration avec le secteur productif','Partenariats industrie/entreprises','Accords','SCORE',2),
(6,'6.3','Valorisation des résultats','Brevets, incubations','Rapports','SCORE',3),
(6,'6.4','Contribution aux politiques publiques','Études stratégiques','Notes techniques','SCORE',4),
(6,'6.5','Impact socio-économique','Adoption des résultats','Témoignages, études','SCORE',5),
(6,'6.6','Rayonnement national/international','Réseaux scientifiques','Consortiums','SCORE',6)
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`), `indicateurs_cles` = VALUES(`indicateurs_cles`), `sources_verification` = VALUES(`sources_verification`);

INSERT IGNORE INTO `identite_contenu` (`critere_id`, `reponse_officielle`, `score`)
SELECT c.id, NULL, NULL FROM `identite_criteres` c
LEFT JOIN `identite_contenu` ct ON ct.critere_id = c.id
WHERE ct.critere_id IS NULL;

INSERT IGNORE INTO `identite_observations` (`champ_id`, `forces`, `faiblesses`, `niveau_global`) VALUES
(1, NULL, NULL, NULL), (2, NULL, NULL, NULL), (3, NULL, NULL, NULL),
(4, NULL, NULL, NULL), (5, NULL, NULL, NULL), (6, NULL, NULL, NULL);

INSERT INTO `identite_indicateurs` (`cle`, `libelle`, `valeur`, `ordre`) VALUES
('nb_chercheurs', 'Nombre total de chercheurs', NULL, 1),
('nb_phd', 'Nombre de PhD', NULL, 2),
('nb_publications', 'Nombre de publications 2024–2025', NULL, 3),
('nb_publications_indexees', 'Nombre de publications indexées', NULL, 4),
('nb_projets_finances', 'Nombre de projets financés', NULL, 5),
('nb_partenariats', 'Nombre de partenaires actifs', NULL, 6),
('nb_brevets', 'Nombre de brevets/prototypes', NULL, 7)
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);

INSERT IGNORE INTO `identite_swot` (`ligne`) VALUES (1), (2), (3);
INSERT IGNORE INTO `identite_recommandations` (`id`, `contenu`) VALUES (1, NULL);

-- ── 6. PRÉREMPLISSAGE (uniquement si vide — ne remplace pas les saisies) ──

UPDATE `identite_identification` SET `valeur` = 'Centre de Recherche en Informatique et Cyber-Technologie (CRICT)'
WHERE `cle` = 'denomination' AND (`valeur` IS NULL OR TRIM(`valeur`) = '');

UPDATE `identite_identification` SET `valeur` = 'Recherche scientifique et technologique en informatique, cybersécurité, systèmes d''information et technologies numériques au service du développement national.'
WHERE `cle` = 'domaines' AND (`valeur` IS NULL OR TRIM(`valeur`) = '');

UPDATE `identite_identification` SET `valeur` = 'Cybersécurité et résilience numérique
Intelligence artificielle et sciences des données
Systèmes d''information et réseaux
Innovation technologique et transfert de compétences'
WHERE `cle` = 'axes_recherche' AND (`valeur` IS NULL OR TRIM(`valeur`) = '');

UPDATE `identite_identification` SET `valeur` = 'Conakry, République de Guinée'
WHERE `cle` = 'adresse' AND (`valeur` IS NULL OR TRIM(`valeur`) = '');

UPDATE `identite_identification` SET `valeur` = 'https://crict.edu.gn'
WHERE `cle` = 'site_web' AND (`valeur` IS NULL OR TRIM(`valeur`) = '');

UPDATE `identite_identification` SET `valeur` = 'Courriel : info@crict.edu.gn — Direction du CRICT'
WHERE `cle` = 'contact' AND (`valeur` IS NULL OR TRIM(`valeur`) = '');

UPDATE `identite_contenu` ct JOIN `identite_criteres` c ON c.id = ct.critere_id
SET ct.reponse_officielle = 'Le CRICT est une institution de recherche régie par un cadre juridique comprenant l''acte de création, le statut juridique et les textes organiques. Les références officielles (décrets, arrêtés, règlement intérieur) sont tenues à la disposition des partenaires et des missions d''évaluation.'
WHERE c.code = '1.1' AND (ct.reponse_officielle IS NULL OR TRIM(ct.reponse_officielle) = '');

UPDATE `identite_contenu` ct JOIN `identite_criteres` c ON c.id = ct.critere_id
SET ct.reponse_officielle = 'Le CRICT dispose d''un organigramme fonctionnel validé par la direction, reflétant la structure des départements, des fonctions de pilotage et des unités opérationnelles. L''organigramme officiel est appliqué au quotidien au sein de l''institution.'
WHERE c.code = '1.2' AND (ct.reponse_officielle IS NULL OR TRIM(ct.reponse_officielle) = '');

UPDATE `identite_contenu` ct JOIN `identite_criteres` c ON c.id = ct.critere_id
SET ct.reponse_officielle = 'Les organes de gouvernance du CRICT (conseil scientifique, instances de direction, réunions de pilotage) se réunissent régulièrement pour orienter les activités. Les procès-verbaux et décisions officielles attestent du fonctionnement de ces instances.'
WHERE c.code = '1.3' AND (ct.reponse_officielle IS NULL OR TRIM(ct.reponse_officielle) = '');

UPDATE `identite_contenu` ct JOIN `identite_criteres` c ON c.id = ct.critere_id
SET ct.reponse_officielle = 'Le CRICT s''appuie sur un plan stratégique aligné sur les priorités nationales en matière de recherche, d''innovation et de transformation numérique. Ce plan guide les axes de développement scientifique et institutionnel du centre.'
WHERE c.code = '1.4' AND (ct.reponse_officielle IS NULL OR TRIM(ct.reponse_officielle) = '');

UPDATE `identite_contenu` ct JOIN `identite_criteres` c ON c.id = ct.critere_id
SET ct.reponse_officielle = 'Un dispositif interne de suivi-évaluation permet de mesurer l''avancement des activités du centre. Des rapports périodiques et des outils de suivi sont produits pour le pilotage institutionnel.'
WHERE c.code = '1.5' AND (ct.reponse_officielle IS NULL OR TRIM(ct.reponse_officielle) = '');

UPDATE `identite_contenu` ct JOIN `identite_criteres` c ON c.id = ct.critere_id
SET ct.reponse_officielle = 'Le CRICT met en œuvre une démarche d''assurance qualité incluant l''autoévaluation et des plans d''amélioration continue. Les rapports qualité documentent cette démarche au sein de l''institution.'
WHERE c.code = '1.6' AND (ct.reponse_officielle IS NULL OR TRIM(ct.reponse_officielle) = '');

UPDATE `identite_contenu` ct JOIN `identite_criteres` c ON c.id = ct.critere_id
SET ct.reponse_officielle = 'Le CRICT promeut l''éthique et l''intégrité scientifique à travers une politique institutionnelle, un code déontologique et, le cas échéant, un comité d''éthique. Les chartes et décisions afférentes encadrent la conduite de la recherche.'
WHERE c.code = '1.7' AND (ct.reponse_officielle IS NULL OR TRIM(ct.reponse_officielle) = '');

UPDATE `identite_contenu` ct JOIN `identite_criteres` c ON c.id = ct.critere_id
SET ct.reponse_officielle = 'Le CRICT assure sa communication institutionnelle via son site web, sa visibilité scientifique et la diffusion de ses activités (publications, événements, réseaux professionnels). Ces canaux renforcent la transparence et le rayonnement du centre.'
WHERE c.code = '1.8' AND (ct.reponse_officielle IS NULL OR TRIM(ct.reponse_officielle) = '');

-- ── 7. MODULE ENT + PERMISSIONS (sans IDs figés) ────────────

INSERT INTO `modules` (`code`, `libelle`, `icone`, `url_base`, `ordre`, `actif`)
SELECT 'IDENTITE_CRICT', 'Évaluation', 'fa-landmark', '/modules/identite/', 12, 1
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'IDENTITE_CRICT');

UPDATE `modules` SET `libelle` = 'Évaluation', `actif` = 1 WHERE `code` = 'IDENTITE_CRICT';

SET @mid := (SELECT id FROM `modules` WHERE `code` = 'IDENTITE_CRICT' LIMIT 1);

INSERT INTO `permissions` (`module_id`, `action`, `libelle`)
SELECT @mid, a.action, a.libelle FROM (
    SELECT 'VOIR'     AS action, 'Consulter – Évaluation'     AS libelle
    UNION ALL SELECT 'MODIFIER', 'Mettre à jour – Évaluation'
    UNION ALL SELECT 'EXPORTER', 'Exporter – Évaluation'
) a
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` p WHERE p.module_id = @mid AND p.action = a.action
);

INSERT INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`)
SELECT g.id, p.id, 1
FROM `groupes_utilisateurs` g
JOIN `permissions` p ON p.module_id = @mid AND p.action = 'VOIR'
WHERE g.actif = 1
  AND NOT EXISTS (
      SELECT 1 FROM `groupes_permissions` gp
      WHERE gp.groupe_id = g.id AND gp.permission_id = p.id
  );

INSERT INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`)
SELECT g.id, p.id, 1
FROM `groupes_utilisateurs` g
JOIN `permissions` p ON p.module_id = @mid AND p.action IN ('MODIFIER','EXPORTER')
WHERE g.code IN ('SUPER_ADMIN','DG')
  AND NOT EXISTS (
      SELECT 1 FROM `groupes_permissions` gp
      WHERE gp.groupe_id = g.id AND gp.permission_id = p.id
  );

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Fin migration MODULE ÉVALUATION (complet)
-- ============================================================