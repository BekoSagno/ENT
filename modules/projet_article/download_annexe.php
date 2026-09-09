<?php
// modules/projet_article/download_annexe.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet_article.php';

session_demarrer();
exiger_permission(ARTICLE_MODULE, 'VOIR');

$id = (int)($_GET['id'] ?? 0);
$f  = basename((string)($_GET['f'] ?? ''));
if ($id <= 0 || $f === '' || strpos($f, '..') !== false) {
    http_response_code(400);
    exit('Requête invalide.');
}

$d = article_charger($id);
if (!$d) {
    http_response_code(404);
    exit('Article introuvable.');
}
if (!article_peut_gerer() && (int)$d['cree_par'] !== (int)user_id()) {
    http_response_code(403);
    exit('Accès refusé.');
}

$meta = null;
foreach ($d['contenu_arr']['fin']['annexes_fichiers'] ?? [] as $row) {
    if (($row['fichier'] ?? '') === $f) {
        $meta = $row;
        break;
    }
}
if (!$meta) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$path = article_annexe_dir($id) . $f;
if (!is_file($path)) {
    http_response_code(404);
    exit('Fichier manquant sur le serveur.');
}

$nom = $meta['nom'] ?? $f;
$mime = $meta['mime'] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nom) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
