<?php
// modules/fournisseurs/actions.php — Traitement des actions POST
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/fournisseur.php';

session_demarrer();
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Requête invalide.');
    redirect('/modules/fournisseurs/index.php');
}

$action    = clean($_POST['action']         ?? '');
$fid       = (int)($_POST['fournisseur_id'] ?? 0);
$retour_id = (int)($_POST['retour_id']      ?? $fid);

// URL de retour
$retour = $retour_id
    ? APP_URL . '/modules/fournisseurs/voir.php?id=' . $retour_id
    : APP_URL . '/modules/fournisseurs/index.php';

switch ($action) {

    // ── Valider ──
    case 'valider':
        exiger_permission('FOURNISSEURS', 'VALIDER');
        $res = fournisseur_changer_statut($fid, 'valide');
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Fournisseur validé.' : ($res['message'] ?? 'Erreur.'));
        break;

    // ── Suspendre ──
    case 'suspendu':
        exiger_permission('FOURNISSEURS', 'VALIDER');
        $res = fournisseur_changer_statut($fid, 'suspendu');
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Fournisseur suspendu.' : ($res['message'] ?? 'Erreur.'));
        break;

    // ── Rejeter ──
    case 'rejeter':
        exiger_permission('FOURNISSEURS', 'VALIDER');
        $motif = clean($_POST['motif'] ?? '');
        if (empty($motif)) {
            set_flash('error', 'Le motif du rejet est obligatoire.');
            break;
        }
        $res = fournisseur_changer_statut($fid, 'rejete', $motif);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Fournisseur rejeté.' : ($res['message'] ?? 'Erreur.'));
        break;

    // ── Supprimer fournisseur ──
    case 'supprimer':
        exiger_permission('FOURNISSEURS', 'SUPPRIMER');
        $res = fournisseur_supprimer($fid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Fournisseur supprimé.' : ($res['message'] ?? 'Erreur.'));
        $retour = APP_URL . '/modules/fournisseurs/index.php';
        break;

    // ── Supprimer un document ──
    case 'supprimer_doc':
        exiger_permission('FOURNISSEURS', 'MODIFIER');
        $doc_id = (int)($_POST['doc_id'] ?? 0);
        $res = fournisseur_doc_supprimer($doc_id);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Document supprimé.' : ($res['message'] ?? 'Erreur.'));
        break;

    default:
        set_flash('error', 'Action non reconnue.');
}

header('Location: ' . $retour);
exit;
