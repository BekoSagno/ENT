<?php
// modules/courriers/actions.php — Traitement des actions POST
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/courrier.php';

session_demarrer();
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Requête invalide.');
    redirect('/modules/courriers/index.php');
}

$action = clean($_POST['action']      ?? '');
$cid    = (int)($_POST['courrier_id'] ?? 0);

$retour = $cid
    ? '/modules/courriers/voir.php?id=' . $cid
    : '/modules/courriers/index.php';

switch ($action) {

    // ── Supprimer un courrier ──
    case 'supprimer':
        exiger_permission('COURRIERS', 'SUPPRIMER');
        $res = courrier_supprimer($cid);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Courrier supprimé.' : ($res['message'] ?? 'Erreur.'));
        $retour = '/modules/courriers/index.php';
        break;

    // ── Supprimer un document ──
    case 'supprimer_doc':
        exiger_permission('COURRIERS', 'MODIFIER');
        $doc_id = (int)($_POST['doc_id'] ?? 0);
        $res = courrier_doc_supprimer($doc_id);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Document supprimé.' : ($res['message'] ?? 'Erreur.'));
        break;

    // ── Affecter / orienter ──
    case 'affecter':
        exiger_permission('COURRIERS', 'MODIFIER');
        $service_id = (int)($_POST['service_id'] ?? 0);
        $affecte_a  = (int)($_POST['affecte_a']  ?? 0);
        $res = courrier_affecter($cid, $service_id ?: null, $affecte_a ?: null, trim($_POST['instruction'] ?? ''));
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Affectation enregistrée.' : ($res['message'] ?? 'Erreur.'));
        break;

    // ── Changer le statut ──
    case 'statut':
        exiger_permission('COURRIERS', 'VALIDER');
        $res = courrier_changer_statut($cid, clean($_POST['statut'] ?? ''), clean($_POST['commentaire'] ?? ''));
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Statut mis à jour.' : ($res['message'] ?? 'Erreur.'));
        break;

    default:
        set_flash('error', 'Action non reconnue.');
}

redirect($retour);
