<?php
// modules/subventions/rdb/actions.php — Actions POST RDB
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../models/rdb.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'RAPPORT');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Requête invalide.');
    redirect('/modules/subventions/rdb/index.php');
}

$action = clean($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? 0);

switch ($action) {
    case 'creer':
        $annee     = (int)($_POST['annee'] ?? 0);
        $trimestre = (int)($_POST['trimestre'] ?? 0);
        $res = rdb_creer($annee, $trimestre);
        if ($res['succes']) {
            set_flash('success', 'RDB créé.');
            redirect('/modules/subventions/rdb/form.php?id=' . $res['id']);
        }
        set_flash('error', $res['message'] ?? 'Erreur.');
        redirect('/modules/subventions/rdb/index.php');
        break;

    case 'dupliquer':
        $src_id    = (int)($_POST['source_id'] ?? 0);
        $annee     = (int)($_POST['annee'] ?? 0);
        $trimestre = (int)($_POST['trimestre'] ?? 0);
        $res = rdb_dupliquer($src_id, $annee, $trimestre);
        if ($res['succes']) {
            set_flash('success', 'RDB dupliqué.');
            redirect('/modules/subventions/rdb/form.php?id=' . $res['id']);
        }
        set_flash('error', $res['message'] ?? 'Erreur.');
        redirect('/modules/subventions/rdb/index.php');
        break;

    case 'finaliser':
        $res = rdb_finaliser($id);
        set_flash($res['succes'] ? 'success' : 'error', $res['succes'] ? 'Rapport finalisé.' : ($res['message'] ?? 'Erreur.'));
        redirect('/modules/subventions/rdb/form.php?id=' . $id);
        break;

    case 'rouvrir':
        $res = rdb_rouvrir($id);
        set_flash($res['succes'] ? 'success' : 'error', $res['succes'] ? 'Rapport rouvert — vous pouvez à nouveau ajouter des lignes.' : ($res['message'] ?? 'Erreur.'));
        redirect('/modules/subventions/rdb/form.php?id=' . $id);
        break;

    case 'supprimer':
        $res = rdb_supprimer($id);
        set_flash($res['succes'] ? 'success' : 'error', $res['succes'] ? 'Rapport supprimé.' : ($res['message'] ?? 'Erreur.'));
        redirect('/modules/subventions/rdb/index.php');
        break;

    case 'prefill':
        $res = rdb_prefill_subventions($id);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? (($res['added'] ?? 0) . ' ligne(s) importée(s) — tableaux remplacés.') : ($res['message'] ?? 'Erreur.'));
        redirect('/modules/subventions/rdb/form.php?id=' . $id . '&step=3');
        break;

    case 'doc_suppr':
        $doc_id = (int)($_POST['doc_id'] ?? 0);
        $res = rdb_doc_supprimer($doc_id, $id);
        set_flash($res['succes'] ? 'success' : 'error', $res['succes'] ? 'Document supprimé.' : ($res['message'] ?? 'Erreur.'));
        redirect('/modules/subventions/rdb/form.php?id=' . $id . '&step=6');
        break;

    default:
        set_flash('error', 'Action non reconnue.');
        redirect('/modules/subventions/rdb/index.php');
}
