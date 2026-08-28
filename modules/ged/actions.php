<?php
// modules/ged/actions.php — Actions POST du module GED
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/ged.php';

session_demarrer();
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Requête invalide.');
    redirect('/modules/ged/index.php');
}

$uid     = (int)user_id();
$action  = clean($_POST['action'] ?? '');
$depot_id = (int)($_POST['depot_id'] ?? 0);
$dossier_post = (int)($_POST['dossier_id'] ?? 0);
$retour_depot = $depot_id ? '/modules/ged/depot.php?id=' . $depot_id : '/modules/ged/index.php';
// Retour en conservant le sous-dossier courant
$retour_dossier = $retour_depot . ($depot_id && $dossier_post ? '&dossier=' . $dossier_post : '');

// Garantit que l'utilisateur courant peut gérer le dépôt visé
function ged_exiger_gestion_depot(int $depot_id, int $uid): array {
    $depot = ged_depot_charger($depot_id);
    if (!$depot) { set_flash('error', 'Dépôt introuvable.'); redirect('/modules/ged/index.php'); }
    $droits = ged_depot_droits($depot, $uid);
    if (!$droits['gerer']) { set_flash('error', 'Action non autorisée.'); redirect('/modules/ged/index.php'); }
    return $depot;
}

switch ($action) {

    // ── Dépôts ────────────────────────────────────────────
    case 'creer_depot':
        exiger_permission('GED', 'CREER');
        $res = ged_depot_creer([
            'nom'         => clean($_POST['nom'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'couleur'     => $_POST['couleur'] ?? '',
            'icone'       => $_POST['icone'] ?? '',
            'visibilite'  => $_POST['visibilite'] ?? 'PRIVE',
            'membres'     => $_POST['membres'] ?? [],
            'roles'       => $_POST['roles'] ?? [],
        ], $uid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Dépôt créé avec succès.' : ($res['message'] ?? 'Erreur.'));
        redirect($res['succes'] ? '/modules/ged/depot.php?id=' . $res['id'] : '/modules/ged/index.php');
        break;

    case 'modifier_depot':
        ged_exiger_gestion_depot($depot_id, $uid);
        $res = ged_depot_modifier($depot_id, [
            'nom'         => clean($_POST['nom'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'couleur'     => $_POST['couleur'] ?? '',
            'icone'       => $_POST['icone'] ?? '',
            'visibilite'  => $_POST['visibilite'] ?? 'PRIVE',
            'membres'     => $_POST['membres'] ?? [],
            'roles'       => $_POST['roles'] ?? [],
        ], $uid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Dépôt mis à jour.' : ($res['message'] ?? 'Erreur.'));
        redirect($retour_depot);
        break;

    case 'supprimer_depot':
        ged_exiger_gestion_depot($depot_id, $uid);
        $res = ged_depot_supprimer($depot_id, $uid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Dépôt supprimé.' : ($res['message'] ?? 'Erreur.'));
        redirect('/modules/ged/index.php');
        break;

    // ── Documents ─────────────────────────────────────────
    case 'deposer_document':
        $depot  = ged_depot_charger($depot_id);
        if (!$depot) { set_flash('error', 'Dépôt introuvable.'); redirect('/modules/ged/index.php'); }
        $droits = ged_depot_droits($depot, $uid);
        if (!$droits['deposer']) { set_flash('error', 'Vous ne pouvez pas déposer dans ce dépôt.'); redirect($retour_depot); }

        $res = ged_document_deposer($depot_id, $_FILES['fichier'] ?? [], [
            'titre'       => clean($_POST['titre'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'portee'      => $_POST['portee'] ?? 'HERITE',
            'users'       => $_POST['users'] ?? [],
            'groupes'     => $_POST['groupes'] ?? [],
            'dossier_id'  => $dossier_post,
        ], $uid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Document déposé.' : ($res['message'] ?? 'Erreur.'));
        redirect($retour_dossier);
        break;

    // ── Sous-dossiers ─────────────────────────────────────
    case 'creer_dossier':
        $depot  = ged_depot_charger($depot_id);
        if (!$depot) { set_flash('error', 'Dépôt introuvable.'); redirect('/modules/ged/index.php'); }
        $droits = ged_depot_droits($depot, $uid);
        if (!$droits['deposer']) { set_flash('error', 'Vous ne pouvez pas créer de dossier ici.'); redirect($retour_depot); }

        $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
        $res = ged_dossier_creer($depot_id, $parent, clean($_POST['nom'] ?? ''), $uid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Dossier créé.' : ($res['message'] ?? 'Erreur.'));
        // Revenir au niveau courant (dans le dossier parent si on y était)
        redirect($retour_depot . ($parent ? '&dossier=' . $parent : ''));
        break;

    case 'renommer_dossier':
        $dossier = ged_dossier_charger((int)($_POST['dossier_id'] ?? 0));
        if (!$dossier) { set_flash('error', 'Dossier introuvable.'); redirect($retour_depot); }
        $depot  = ged_depot_charger((int)$dossier['depot_id']);
        $droits = ged_depot_droits($depot, $uid);
        if (!$droits['gerer']) { set_flash('error', 'Action non autorisée.'); redirect($retour_depot); }

        $res = ged_dossier_renommer((int)$dossier['id'], clean($_POST['nom'] ?? ''), $uid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Dossier renommé.' : ($res['message'] ?? 'Erreur.'));
        redirect($retour_depot . (!empty($dossier['parent_id']) ? '&dossier=' . (int)$dossier['parent_id'] : ''));
        break;

    case 'supprimer_dossier':
        $dossier = ged_dossier_charger((int)($_POST['dossier_id'] ?? 0));
        if (!$dossier) { set_flash('error', 'Dossier introuvable.'); redirect($retour_depot); }
        $depot  = ged_depot_charger((int)$dossier['depot_id']);
        $droits = ged_depot_droits($depot, $uid);
        if (!$droits['gerer']) { set_flash('error', 'Action non autorisée.'); redirect($retour_depot); }

        $res = ged_dossier_supprimer((int)$dossier['id'], $uid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Dossier supprimé (sous-dossiers inclus). Les documents reviennent à la racine.' : ($res['message'] ?? 'Erreur.'));
        redirect($retour_depot . (!empty($dossier['parent_id']) ? '&dossier=' . (int)$dossier['parent_id'] : ''));
        break;

    case 'modifier_acces_document':
        $doc_id = (int)($_POST['document_id'] ?? 0);
        $doc    = ged_document_charger($doc_id);
        if (!$doc) { set_flash('error', 'Document introuvable.'); redirect($retour_depot); }
        $depot  = ged_depot_charger((int)$doc['depot_id']);
        $droits = ged_depot_droits($depot, $uid);
        $peut   = $droits['gerer'] || (int)($doc['depose_par'] ?? 0) === $uid;
        if (!$peut) { set_flash('error', 'Action non autorisée.'); redirect($retour_depot); }

        $res = ged_document_modifier_acces($doc_id, [
            'portee'  => $_POST['portee'] ?? 'HERITE',
            'titre'   => clean($_POST['titre'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'users'   => $_POST['users'] ?? [],
            'groupes' => $_POST['groupes'] ?? [],
        ], $uid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Accès du document mis à jour.' : ($res['message'] ?? 'Erreur.'));
        redirect($retour_depot);
        break;

    case 'supprimer_document':
        $doc_id = (int)($_POST['document_id'] ?? 0);
        $doc    = ged_document_charger($doc_id);
        if (!$doc) { set_flash('error', 'Document introuvable.'); redirect($retour_depot); }
        $depot  = ged_depot_charger((int)$doc['depot_id']);
        $droits = ged_depot_droits($depot, $uid);
        $peut   = $droits['gerer'] || (int)($doc['depose_par'] ?? 0) === $uid;
        if (!$peut) { set_flash('error', 'Action non autorisée.'); redirect($retour_depot); }

        $res = ged_document_supprimer($doc_id, $uid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Document supprimé.' : ($res['message'] ?? 'Erreur.'));
        redirect($retour_depot);
        break;

    default:
        set_flash('error', 'Action non reconnue.');
        redirect('/modules/ged/index.php');
}
