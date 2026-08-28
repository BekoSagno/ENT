-- ============================================================
--  MODULE INSTRUCTIONS (v2) — Conteneur + tâches
--  Une instruction regroupe 1 (tâche unique) ou N tâches (tâches
--  multiples), chacune affectée à un responsable, avec son propre
--  délai (minutes/heures/jours) et ses dépendances.
--  Script ré-exécutable : il supprime les tables instructions
--  existantes avant de les recréer.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `instructions_taches_outputs`;
DROP TABLE IF EXISTS `instructions_taches`;
DROP TABLE IF EXISTS `instructions_destinataires`; -- ancienne v1
DROP TABLE IF EXISTS `instructions_documents`;     -- ancienne v1
DROP TABLE IF EXISTS `instructions`;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Conteneur : instructions ─────────────────────────────────
CREATE TABLE `instructions` (
  `id`          int(10) UNSIGNED NOT NULL,
  `code`        varchar(30)  NOT NULL COMMENT 'INST-AAAA-NNN',
  `objet`       varchar(255) NOT NULL COMMENT 'Ex : Paiement des primes du personnel',
  `description` text         DEFAULT NULL COMMENT 'Contexte général (optionnel)',
  `type`        enum('UNIQUE','MULTIPLE') NOT NULL DEFAULT 'UNIQUE',
  `priorite`    enum('FAIBLE','NORMALE','HAUTE','CRITIQUE') NOT NULL DEFAULT 'NORMALE',
  `statut`      enum('EN_COURS','TERMINEE','ANNULEE') NOT NULL DEFAULT 'EN_COURS',
  `emetteur_id` int(10) UNSIGNED NOT NULL,
  `cree_le`     datetime NOT NULL DEFAULT current_timestamp(),
  `modifie_le`  datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tâches (1 par responsable) ───────────────────────────────
CREATE TABLE `instructions_taches` (
  `id`              int(10) UNSIGNED NOT NULL,
  `instruction_id`  int(10) UNSIGNED NOT NULL,
  `code`            varchar(20)  DEFAULT NULL COMMENT 'T-01, T-02…',
  `titre`           varchar(300) NOT NULL COMMENT 'La tâche à faire',
  `description`     text         DEFAULT NULL,
  `assigne_a`       int(10) UNSIGNED DEFAULT NULL COMMENT 'Responsable de la tâche',
  `delai_valeur`    smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `delai_unite`     enum('MINUTE','HEURE','JOUR') NOT NULL DEFAULT 'HEURE',
  `date_echeance`   datetime     DEFAULT NULL COMMENT 'NULL tant que bloquée par une dépendance',
  `dependance_type` enum('INDEPENDANTE','APRES_FIN','APRES_DEBUT') NOT NULL DEFAULT 'INDEPENDANTE',
  `depend_de`       int(10) UNSIGNED DEFAULT NULL COMMENT 'Autre tâche de la même instruction',
  `statut`          enum('BLOQUEE','A_FAIRE','EN_COURS','EN_REVISION','TERMINEE','ANNULEE') NOT NULL DEFAULT 'A_FAIRE',
  `avancement`      tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `priorite`        enum('FAIBLE','NORMALE','HAUTE','CRITIQUE') NOT NULL DEFAULT 'NORMALE',
  `reponse`         text         DEFAULT NULL COMMENT 'Compte rendu du responsable',
  `vue_le`          datetime     DEFAULT NULL COMMENT 'Accusé de réception',
  `termine_le`      datetime     DEFAULT NULL,
  `ordre`           smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `cree_par`        int(10) UNSIGNED DEFAULT NULL,
  `cree_le`         datetime NOT NULL DEFAULT current_timestamp(),
  `modifie_le`      datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Documents output (par tâche) ─────────────────────────────
CREATE TABLE `instructions_taches_outputs` (
  `id`             int(10) UNSIGNED NOT NULL,
  `tache_id`       int(10) UNSIGNED NOT NULL,
  `instruction_id` int(10) UNSIGNED NOT NULL,
  `type`           enum('FICHIER','IMAGE','LIEN') NOT NULL DEFAULT 'FICHIER',
  `nom_original`   varchar(255)  DEFAULT NULL,
  `nom_stockage`   varchar(255)  DEFAULT NULL,
  `chemin`         varchar(500)  DEFAULT NULL,
  `type_mime`      varchar(100)  DEFAULT NULL,
  `taille`         int(10) UNSIGNED DEFAULT NULL,
  `extension`      varchar(15)   DEFAULT NULL,
  `url`            varchar(2000) DEFAULT NULL,
  `libelle`        varchar(300)  DEFAULT NULL,
  `depose_par`     int(10) UNSIGNED DEFAULT NULL,
  `cree_le`        datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Clés & index ─────────────────────────────────────────────
ALTER TABLE `instructions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_code` (`code`),
  ADD KEY `idx_emetteur` (`emetteur_id`),
  ADD KEY `idx_statut` (`statut`);

ALTER TABLE `instructions_taches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_instruction` (`instruction_id`),
  ADD KEY `idx_assigne` (`assigne_a`),
  ADD KEY `idx_depend` (`depend_de`),
  ADD KEY `idx_echeance` (`date_echeance`);

ALTER TABLE `instructions_taches_outputs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tache` (`tache_id`),
  ADD KEY `idx_instr` (`instruction_id`);

ALTER TABLE `instructions`               MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `instructions_taches`        MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `instructions_taches_outputs` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- ── Contraintes ──────────────────────────────────────────────
ALTER TABLE `instructions`
  ADD CONSTRAINT `fk_inst_emetteur` FOREIGN KEY (`emetteur_id`) REFERENCES `utilisateurs` (`id`);

ALTER TABLE `instructions_taches`
  ADD CONSTRAINT `fk_it_instruction` FOREIGN KEY (`instruction_id`) REFERENCES `instructions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_it_assigne`     FOREIGN KEY (`assigne_a`)      REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_it_depend`      FOREIGN KEY (`depend_de`)      REFERENCES `instructions_taches` (`id`) ON DELETE SET NULL;

ALTER TABLE `instructions_taches_outputs`
  ADD CONSTRAINT `fk_ito_tache`       FOREIGN KEY (`tache_id`)       REFERENCES `instructions_taches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ito_instruction` FOREIGN KEY (`instruction_id`) REFERENCES `instructions` (`id`) ON DELETE CASCADE;

-- ============================================================
--  Module + permissions + droits (idempotent)
-- ============================================================
INSERT INTO `modules` (`id`, `code`, `libelle`, `icone`, `url_base`, `ordre`, `actif`, `cree_le`) VALUES
(16, 'INSTRUCTIONS', 'Instructions du DG', 'fa-bullhorn', '/modules/instructions/', 3, 1, NOW())
ON DUPLICATE KEY UPDATE `libelle`=VALUES(`libelle`), `icone`=VALUES(`icone`), `url_base`=VALUES(`url_base`);

INSERT INTO `permissions` (`id`, `module_id`, `action`, `libelle`) VALUES
(80, 16, 'VOIR',      'Consulter – Instructions'),
(81, 16, 'CREER',     'Émettre une instruction – Instructions'),
(82, 16, 'MODIFIER',  'Modifier – Instructions'),
(83, 16, 'SUPPRIMER', 'Annuler/Supprimer – Instructions'),
(84, 16, 'VALIDER',   'Clôturer/valider – Instructions')
ON DUPLICATE KEY UPDATE `libelle`=VALUES(`libelle`);

-- Émission + gestion : DG (groupe 2) et SUPER_ADMIN (groupe 1)
INSERT INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(1,80,1),(1,81,1),(1,82,1),(1,83,1),(1,84,1),
(2,80,1),(2,81,1),(2,82,1),(2,83,1),(2,84,1)
ON DUPLICATE KEY UPDATE `accorde`=1;

-- Consultation (VOIR) pour tous les groupes (les responsables de tâche)
INSERT INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`)
SELECT g.id, 80, 1 FROM `groupes_utilisateurs` g
WHERE NOT EXISTS (SELECT 1 FROM `groupes_permissions` gp WHERE gp.groupe_id=g.id AND gp.permission_id=80);
