<?php
// modules/parametres/actions.php — Actions POST
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/privilege.php';

session_demarrer();
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error','Requête invalide.');
    redirect('/modules/parametres/index.php');
}

$action = clean($_POST['action'] ?? '');
$gid    = (int)($_POST['groupe_id'] ?? 0);

$retour = $gid
    ? APP_URL.'/modules/parametres/index.php'
    : APP_URL.'/modules/parametres/index.php';

switch ($action) {
    case 'activer_groupe':
        exiger_permission('PARAMETRES','MODIFIER');
        $res = priv_groupe_activer_desactiver($gid, true);
        set_flash($res['succes']?'success':'error', $res['succes']?'Groupe activé.':$res['message']);
        break;

    case 'desactiver_groupe':
        exiger_permission('PARAMETRES','MODIFIER');
        $res = priv_groupe_activer_desactiver($gid, false);
        set_flash($res['succes']?'success':'error', $res['succes']?'Groupe désactivé.':$res['message']);
        break;

    case 'supprimer_groupe':
        exiger_permission('PARAMETRES','SUPPRIMER');
        $res = priv_groupe_supprimer($gid);
        set_flash($res['succes']?'success':'error', $res['succes']?'Groupe supprimé.':$res['message']);
        break;

    default:
        set_flash('error','Action non reconnue.');
}

header('Location: '.$retour); exit;
