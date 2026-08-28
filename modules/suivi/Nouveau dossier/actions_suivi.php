<?php
// modules/suivi/actions_suivi.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/suivi.php';

session_demarrer();
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/modules/suivi/index.php'); }
if (!csrf_verify()) {
    set_flash('error', 'Requête invalide. Veuillez réessayer.');
    redirect('/modules/suivi/index.php');
}

$action    = clean($_POST['action']      ?? '');
$fiche_id  = (int)($_POST['fiche_id']   ?? 0);
$commentaire = clean($_POST['commentaire'] ?? '');

if (!$fiche_id) {
    set_flash('error', 'Fiche introuvable.');
    redirect('/modules/suivi/index.php');
}

switch ($action) {

    case 'VALIDEE':
        exiger_permission('SUIVI_QUOTIDIEN', 'VALIDER');
        $res = suivi_valider_fiche($fiche_id, $commentaire);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Fiche validée avec succès.' : ($res['message'] ?? 'Erreur lors de la validation.'));
        break;

    case 'REJETEE':
        exiger_permission('SUIVI_QUOTIDIEN', 'VALIDER');
        if (empty(trim($commentaire))) {
            set_flash('error', 'Le motif du rejet est obligatoire.');
            redirect('/modules/suivi/index.php');
        }
        $res = suivi_rejeter_fiche($fiche_id, $commentaire);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Fiche rejetée.' : ($res['message'] ?? 'Erreur lors du rejet.'));
        break;

    default:
        set_flash('error', 'Action non reconnue.');
}

// Retour vers la page précédente ou l'index
$retour = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/modules/suivi/index.php');
header('Location: ' . $retour);
exit;
