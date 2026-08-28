<?php
// modules/identite/auto_download.php — Téléchargement pièce jointe ANAQ
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

session_demarrer();
exiger_permission('IDENTITE_CRICT', 'VOIR');

$id = (int)($_GET['id'] ?? 0);
$dl = !empty($_GET['dl']);

$s = get_db()->prepare('SELECT * FROM identite_auto_pieces WHERE id = ?');
$s->execute([$id]);
$doc = $s->fetch();
if (!$doc) {
    http_response_code(404);
    exit('Document introuvable.');
}

$abs = APP_ROOT . '/' . ltrim($doc['chemin_fichier'], '/');
if (!is_file($abs)) {
    http_response_code(404);
    exit('Fichier absent.');
}

$mime = $doc['type_mime'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('X-Content-Type-Options: nosniff');
if ($dl) {
    header('Content-Disposition: attachment; filename="' . basename($doc['nom_original']) . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($doc['nom_original']) . '"');
}
readfile($abs);
exit;
