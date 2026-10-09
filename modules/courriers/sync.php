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

require_once __DIR__ . '/../../includes/mailer_courriers.php';

$en_cli = (PHP_SAPI === 'cli');

// Relève + information du DG pour les courriers créés
$relever = function (int $limite): array {
    $avant = (int)get_db()->query('SELECT COALESCE(MAX(id),0) FROM courriers')->fetchColumn();
    $res   = courrier_relever_emails($limite);
    if (!empty($res['crees'])) {
        try {
            $s = get_db()->prepare("SELECT * FROM courriers WHERE id > ? AND sens='ENTRANT' AND email_message_id IS NOT NULL ORDER BY id");
            $s->execute([$avant]);
            courrier_notifier_nouveaux_entrants($s->fetchAll());
        } catch (Throwable $e) {
            error_log('[CRICT sync] notification DG : ' . $e->getMessage());
        }
    }
    return $res;
};

if ($en_cli) {
    // ── Mode tâche planifiée ──
    // Pas de session active : user_id() renverra null (réception automatique).
    if (!isset($_SESSION)) $_SESSION = [];
    $res = $relever(100);
    fwrite(STDOUT, '['.date('Y-m-d H:i:s').'] '.($res['succes'] ? 'OK' : 'ÉCHEC').' — '.$res['message'].PHP_EOL);
    exit($res['succes'] ? 0 : 1);
}

// ── Mode web (interface) ──
session_demarrer();
exiger_permission('COURRIERS', 'CREER');

$res = $relever(50);
set_flash($res['succes'] ? ($res['crees'] > 0 ? 'success' : 'info') : 'error', $res['message']);

header('Location: ' . APP_URL . '/modules/courriers/index.php');
exit;
