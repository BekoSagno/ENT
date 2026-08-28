<?php
// modules/identite/auto_canevas_download.php — Téléchargement du guide canevas
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/auto_anaq.php';

session_demarrer();
exiger_permission('IDENTITE_CRICT', 'VOIR');

$id = (int)($_GET['id'] ?? 0);
$c  = auto_anaq_canevas_par_id($id);
if (!$c || empty($c['fichier_guide'])) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$abs = APP_ROOT . '/' . ltrim($c['fichier_guide'], '/');
if (!is_file($abs)) {
    http_response_code(404);
    exit('Fichier introuvable sur le serveur.');
}

$nom = $c['fichier_nom'] ?: basename($abs);
$mime = mime_content_type($abs) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nom) . '"');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit;
