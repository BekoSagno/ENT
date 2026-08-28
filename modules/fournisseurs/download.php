<?php
// modules/fournisseurs/download.php — Téléchargement sécurisé des documents
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

session_demarrer();
exiger_permission('FOURNISSEURS', 'VOIR');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('Paramètre manquant.'); }

$db   = get_db();
$stmt = $db->prepare('SELECT * FROM documents_fournisseurs WHERE id = ?');
$stmt->execute([$id]);
$doc  = $stmt->fetch();

if (!$doc) { http_response_code(404); exit('Document introuvable.'); }

$chemin_abs = APP_ROOT . '/' . ltrim($doc['chemin_fichier'], '/');
if (!file_exists($chemin_abs)) { http_response_code(404); exit('Fichier absent du serveur.'); }

$ext    = strtolower($doc['extension'] ?? '');
$mime   = $doc['type_mime'] ?: 'application/octet-stream';
$inline = in_array($ext, ['pdf','jpg','jpeg','png','webp']);
$dispo  = $inline ? 'inline' : 'attachment';

header('Content-Type: '        . $mime);
header('Content-Disposition: ' . $dispo . '; filename="' . rawurlencode($doc['nom_original']) . '"');
header('Content-Length: '      . filesize($chemin_abs));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($chemin_abs);
exit;
