<?php
// modules/courriers/sync.php
// Relève la boîte info@ et crée les courriers entrants.
//   • En WEB : déclenché par le bouton « Relever info@ » (permission CREER),
//     puis redirige vers le registre avec un message flash.
//   • En AUTO : appel en arrière-plan à l'ouverture du registre (POST auto=1),
//     au plus une fois toutes les 5 minutes ; réponse JSON.
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
    $res = courrier_releve_verrouillee(fn() => $relever(100), 0, true);
    fwrite(STDOUT, '['.date('Y-m-d H:i:s').'] '.($res['succes'] ? 'OK' : 'ÉCHEC').' — '.$res['message'].PHP_EOL);
    exit($res['succes'] ? 0 : 1);
}

session_demarrer();

// ── Mode automatique (registre ouvert) ──
if (($_POST['auto'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    if (!courrier_releve_auto_active() || !user_id() || !csrf_verify() || !(a_permission('COURRIERS', 'VOIR') || courrier_est_dg())) {
        http_response_code(403);
        echo json_encode(['crees' => 0]);
        exit;
    }
    // Libère la session (navigation non bloquée) ; la relève se fait au nom du système,
    // pour que le DG soit prévenu même si c'est lui qui a ouvert le registre.
    session_write_close();
    $_SESSION = [];
    ignore_user_abort(true);
    @set_time_limit(120);

    $res = courrier_releve_verrouillee(fn() => $relever(20), 300, false);
    echo json_encode(['crees' => (int)($res['crees'] ?? 0)]);
    exit;
}

// ── Mode web (bouton) ──
exiger_permission('COURRIERS', 'CREER');

$res = courrier_releve_verrouillee(fn() => $relever(50), 0, true);
set_flash($res['succes'] ? ($res['crees'] > 0 ? 'success' : 'info') : 'error', $res['message']);

header('Location: ' . APP_URL . '/modules/courriers/index.php');
exit;
