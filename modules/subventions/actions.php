<?php
// modules/subventions/actions.php — Actions POST
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/subvention.php';

session_demarrer();
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error','Requête invalide.');
    redirect('/modules/subventions/index.php');
}

$action    = clean($_POST['action']    ?? '');
$caisse_id = (int)($_POST['caisse_id'] ?? 0);

$retour = $caisse_id
    ? APP_URL . '/modules/subventions/caisse_voir.php?id=' . $caisse_id
    : APP_URL . '/modules/subventions/index.php';

switch ($action) {
    case 'changer_statut_caisse':
        exiger_permission('SUBVENTIONS','VALIDER');
        $res = caisse_changer_statut($caisse_id, clean($_POST['statut']??''));
        set_flash($res['succes']?'success':'error', $res['succes']?'Statut modifié.':$res['message']);
        break;
    default:
        set_flash('error','Action non reconnue.');
}

header('Location: '.$retour); exit;
