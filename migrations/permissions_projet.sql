-- ============================================================
-- permissions_projets.sql
-- Ajout des permissions du module PROJETS dans groupes_permissions
-- Base : crict_ent
-- ============================================================
--
-- Module PROJETS (module_id = 3) — IDs des permissions :
--   7  = VOIR     – Consulter les projets
--  16  = CREER    – Créer des projets et tâches
--  25  = MODIFIER – Modifier projets et tâches
--  34  = SUPPRIMER
--  43  = VALIDER
--  52  = EXPORTER
--
-- Groupes :
--   1 SUPER_ADMIN  2 DG  3 SS  4 CQ
--   5 DAF          6 CF  7 COMPTABLE  8 PRMP
--   9 CHERCHEUR   10 TECHNICIEN
-- ============================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ────────────────────────────────────────────────────────────
-- GROUPE 1 : SUPER_ADMIN — toutes les permissions (déjà
--   couvert dans le code par le shortcut groupe_code='SUPER_ADMIN',
--   mais on insère quand même pour la cohérence de la table)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(1,  7, 1),   -- PROJETS : VOIR
(1, 16, 1),   -- PROJETS : CREER
(1, 25, 1),   -- PROJETS : MODIFIER
(1, 34, 1),   -- PROJETS : SUPPRIMER
(1, 43, 1),   -- PROJETS : VALIDER
(1, 52, 1);   -- PROJETS : EXPORTER

-- ────────────────────────────────────────────────────────────
-- GROUPE 2 : DG (Directeur Général)
--   Crée les projets, assigne les équipes, valide, exporte
--   → toutes les permissions PROJETS
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(2,  7, 1),   -- VOIR
(2, 16, 1),   -- CREER
(2, 25, 1),   -- MODIFIER
(2, 34, 1),   -- SUPPRIMER
(2, 43, 1),   -- VALIDER
(2, 52, 1);   -- EXPORTER

-- ────────────────────────────────────────────────────────────
-- GROUPE 3 : SS (Secrétaire de Direction)
--   Consulte les projets, crée/modifie les tâches qui lui sont
--   assignées, exporte pour les rapports de direction
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(3,  7, 1),   -- VOIR
(3, 52, 1);   -- EXPORTER

-- ────────────────────────────────────────────────────────────
-- GROUPE 4 : CQ (Chargé de Qualité)
--   Consulte et audite les projets, peut exporter pour rapports,
--   peut modifier (mise à jour avancement) mais ne crée pas
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(4,  7, 1),   -- VOIR
(4, 25, 1),   -- MODIFIER (mise à jour avancement de ses tâches)
(4, 52, 1);   -- EXPORTER

-- ────────────────────────────────────────────────────────────
-- GROUPE 5 : DAF (Directeur Administratif & Financier)
--   Supervise les projets liés aux finances, peut créer
--   des projets administratifs/budgétaires, valide et exporte
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(5,  7, 1),   -- VOIR
(5, 16, 1),   -- CREER
(5, 25, 1),   -- MODIFIER
(5, 43, 1),   -- VALIDER
(5, 52, 1);   -- EXPORTER

-- ────────────────────────────────────────────────────────────
-- GROUPE 6 : CF (Contrôleur Financier)
--   Consulte les projets (suivi budgétaire), exporte
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(6,  7, 1),   -- VOIR
(6, 52, 1);   -- EXPORTER

-- ────────────────────────────────────────────────────────────
-- GROUPE 7 : COMPTABLE
--   Consulte uniquement (besoin de voir les projets pour
--   le suivi des dépenses associées)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(7,  7, 1);   -- VOIR

-- ────────────────────────────────────────────────────────────
-- GROUPE 8 : PRMP (Marchés Publics)
--   Consulte les projets liés aux marchés, exporte
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(8,  7, 1),   -- VOIR
(8, 52, 1);   -- EXPORTER

-- ────────────────────────────────────────────────────────────
-- GROUPE 9 : CHERCHEUR (Docteur)
--   Cœur de cible : crée ses propres projets de recherche,
--   gère ses tâches, dépose les outputs, exporte
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(9,  7, 1),   -- VOIR
(9, 16, 1),   -- CREER
(9, 25, 1),   -- MODIFIER
(9, 52, 1);   -- EXPORTER

-- ────────────────────────────────────────────────────────────
-- GROUPE 10 : TECHNICIEN (Licence/Master)
--   Membre d'équipe sur les projets, voit et modifie
--   ses propres tâches (avancement, outputs)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `groupes_permissions` (`groupe_id`, `permission_id`, `accorde`) VALUES
(10,  7, 1),  -- VOIR
(10, 25, 1);  -- MODIFIER (mise à jour de ses tâches assignées)

COMMIT;

-- ============================================================
-- VÉRIFICATION (optionnel, à exécuter après)
-- ============================================================
-- SELECT g.code AS groupe, p.action, p.libelle
-- FROM groupes_permissions gp
-- JOIN groupes_utilisateurs g ON g.id = gp.groupe_id
-- JOIN permissions p ON p.id = gp.permission_id
-- WHERE p.module_id = 3
-- ORDER BY gp.groupe_id, p.action;