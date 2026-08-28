<?php
// modules/courriers/sync.php
// Relève la boîte info@ et crée les courriers entrants.
//   • En WEB : déclenché par le bouton « Relever info@ » (permission CREER),
//     puis redirige vers le registre avec un message flash.
//   • En CLI : utilisable par une tâche cron, ex. toutes les 10 min :
//       */10 * * * * php /chemin/news_crict/modules/courriers/sync.php >> /var/log/crict_courriers.log 2>&1
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/courrier.php';
require_once __DIR__ . '/../../includes/courrier_imap.php';

$en_cli = (PHP_SAPI === 'cli');

if ($en_cli) {
    // ── Mode tâche planifiée ──
    // Pas de session active : user_id() renverra null (réception automatique).
    if (!isset($_SESSION)) $_SESSION = [];
    $res = courrier_relever_emails(100);
    fwrite(STDOUT, '['.date('Y-m-d H:i:s').'] '.($res['succes'] ? 'OK' : 'ÉCHEC').' — '.$res['message'].PHP_EOL);
    exit($res['succes'] ? 0 : 1);
}

// ── Mode web (interface) ──
session_demarrer();
exiger_permission('COURRIERS', 'CREER');

$res = courrier_relever_emails(50);
set_flash($res['succes'] ? ($res['crees'] > 0 ? 'success' : 'info') : 'error', $res['message']);

header('Location: ' . APP_URL . '/modules/courriers/index.php');
exit;
