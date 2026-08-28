<?php
// modules/projet_recherche/actions.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet_recherche.php';

session_demarrer();
exiger_permission(RECHERCHE_MODULE, 'VOIR');
recherche_assurer_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Requête invalide.');
    redirect('/modules/projet_recherche/index.php');
}

$action = clean($_POST['action'] ?? '');

switch ($action) {
    case 'creer':
        $r = recherche_creer(clean($_POST['titre'] ?? ''));
        set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? '');
        if ($r['succes']) {
            redirect('/modules/projet_recherche/editer.php?id=' . (int)$r['id'] . '&etape=1');
        }
        redirect('/modules/projet_recherche/creer.php');
        break;

    case 'sauver_etape':
        $id = (int)($_POST['id'] ?? 0);
        $etape = max(1, min(6, (int)($_POST['etape'] ?? 1)));
        $r = recherche_sauver_parcours($id, $etape, $_POST);
        if (!$r['succes']) {
            set_flash('error', $r['message'] ?? 'Erreur');
        }
        $next = $etape;
        if (!empty($_POST['_suivant'])) $next = min(6, $etape + 1);
        if (!empty($_POST['_precedent'])) $next = max(1, $etape - 1);
        if (!empty($_POST['_apercu'])) {
            redirect('/modules/projet_recherche/editer.php?id=' . $id . '&etape=6');
        }
        redirect('/modules/projet_recherche/editer.php?id=' . $id . '&etape=' . $next);
        break;

    case 'titre':
        $id = (int)($_POST['id'] ?? 0);
        $r = recherche_mettre_a_jour_titre($id, clean($_POST['titre'] ?? ''));
        set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? '');
        redirect('/modules/projet_recherche/editer.php?id=' . $id . '&etape=' . (int)($_POST['etape'] ?? 1));
        break;

    case 'statut':
        $id = (int)($_POST['id'] ?? 0);
        $r = recherche_changer_statut($id, clean($_POST['statut'] ?? ''));
        set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? '');
        redirect('/modules/projet_recherche/editer.php?id=' . $id . '&etape=6');
        break;

    case 'supprimer':
        $id = (int)($_POST['id'] ?? 0);
        $r = recherche_supprimer($id);
        set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? '');
        redirect('/modules/projet_recherche/index.php');
        break;

    default:
        set_flash('error', 'Action inconnue.');
        redirect('/modules/projet_recherche/index.php');
}
