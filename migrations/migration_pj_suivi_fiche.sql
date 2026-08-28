-- ============================================================
-- Migration : Pièces jointes des activités journalières
-- À exécuter sur la base de données `crict`
-- ============================================================

CREATE TABLE IF NOT EXISTS `suivi_pieces_jointes` (
  `id`            int UNSIGNED      NOT NULL AUTO_INCREMENT,
  `activite_id`   int UNSIGNED      NOT NULL COMMENT 'FK → suivi_activites_journalieres.id',
  `fiche_id`      int UNSIGNED      NOT NULL COMMENT 'Dénormalisé pour accès rapide par fiche',
  `nom_original`  varchar(255)      COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom affiché à l'utilisateur',
  `nom_stockage`  varchar(255)      COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom unique sur disque (UUID)',
  `chemin`        varchar(500)      COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Chemin relatif depuis UPLOAD_DIR',
  `type_mime`     varchar(100)      COLLATE utf8mb4_unicode_ci NOT NULL,
  `taille`        int UNSIGNED      NOT NULL COMMENT 'Taille en octets',
  `extension`     varchar(10)       COLLATE utf8mb4_unicode_ci NOT NULL,
  `televerse_par` int UNSIGNED      DEFAULT NULL COMMENT 'FK → utilisateurs.id',
  `cree_le`       datetime          NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_activite` (`activite_id`),
  KEY `idx_fiche`    (`fiche_id`),
  KEY `idx_user`     (`televerse_par`),

  CONSTRAINT `fk_pj_activite`
    FOREIGN KEY (`activite_id`)
    REFERENCES `suivi_activites_journalieres` (`id`)
    ON DELETE CASCADE,

  CONSTRAINT `fk_pj_fiche`
    FOREIGN KEY (`fiche_id`)
    REFERENCES `suivi_fiches_journalieres` (`id`)
    ON DELETE CASCADE,

  CONSTRAINT `fk_pj_user`
    FOREIGN KEY (`televerse_par`)
    REFERENCES `utilisateurs` (`id`)
    ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pièces jointes (PDF, Word, Excel) attachées aux activités journalières';
