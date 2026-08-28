-- ============================================================
-- fournisseur_type_libre.sql
-- Autorise la saisie libre du type de fournisseur (+ « Biens et services »)
-- en remplaçant l'ENUM('biens','services') par un VARCHAR.
-- Base : crict_ent
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `fournisseurs`
  MODIFY COLUMN `type_fournisseur` VARCHAR(100) NOT NULL DEFAULT 'biens';
