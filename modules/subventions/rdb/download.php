<?php
// modules/subventions/rdb/download.php — Téléchargement sécurisé des annexes RDB
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../models/rdb.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'RAPPORT');

$doc_id = (int)($_GET['id'] ?? 0);
$doc = rdb_doc_get($doc_id);
if (!$doc) { http_response_code(404); exit('Document introuvable.'); }

$abs = APP_ROOT . '/' . ltrim($doc['chemin_fichier'], '/');
if (!is_file($abs)) { http_response_code(404); exit('Fichier absent.'); }

$mime = $doc['type_mime'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($doc['nom_original']) . '"');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit;
