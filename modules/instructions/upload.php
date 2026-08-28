<?php
// modules/instructions/upload.php — Téléchargement sécurisé d'un output de tâche
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/instruction.php';

session_demarrer();
exiger_connexion();

$user = user_courant();
$id   = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('Paramètre manquant.'); }

$db = get_db();
$s  = $db->prepare('SELECT * FROM instructions_taches_outputs WHERE id=? LIMIT 1');
$s->execute([$id]);
$doc = $s->fetch();
if (!$doc || $doc['type'] === 'LIEN' || empty($doc['chemin'])) { http_response_code(404); exit('Document introuvable.'); }

$instruction_id = (int)$doc['instruction_id'];
$tache_id       = (int)$doc['tache_id'];

// Accès : émetteur de l'instruction OU responsable de la tâche
if (!_instr_est_emetteur($instruction_id, (int)$user['id'])
    && !_instr_est_responsable($tache_id, (int)$user['id'])) {
    http_response_code(403); exit('Accès refusé.');
}

$chemin = UPLOAD_DIR . $doc['chemin'];
if (!is_file($chemin)) { http_response_code(404); exit('Fichier absent du serveur.'); }

log_activite((int)$user['id'], 'INSTRUCTIONS', 'TELECHARGEMENT', "Output #{$id}");

header('Content-Description: File Transfer');
header('Content-Type: ' . ($doc['type_mime'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($doc['nom_original']) . '"');
header('Content-Length: ' . filesize($chemin));
header('Cache-Control: private, must-revalidate');
header('Pragma: private');
readfile($chemin);
exit;
