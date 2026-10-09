<?php
// modules/courriers/download.php — Ouverture / téléchargement sécurisé des documents
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/courrier.php';

session_demarrer();
exiger_connexion();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('Paramètre manquant.'); }

$stmt = get_db()->prepare('SELECT * FROM courriers_documents WHERE id = ?');
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); exit('Document introuvable.'); }

$sc = get_db()->prepare('SELECT id, confidentialite, enregistre_par FROM courriers WHERE id = ?');
$sc->execute([(int)$doc['courrier_id']]);
$cour = $sc->fetch();
if (!$cour || !courrier_peut_voir($cour)) { http_response_code(403); exit('Accès non autorisé.'); }

// UPLOAD_DIR peut différer de APP_ROOT/uploads/ selon la configuration du serveur
$candidats = [
    APP_ROOT . '/' . ltrim($doc['chemin_fichier'], '/'),
    COURRIER_UPLOAD_DIR . (int)$doc['courrier_id'] . '/' . basename($doc['nom_stockage']),
];
$chemin_abs = null;
foreach ($candidats as $c) {
    if (is_file($c)) { $chemin_abs = $c; break; }
}
if (!$chemin_abs) { http_response_code(404); exit('Fichier absent du serveur.'); }

$ext    = strtolower($doc['extension'] ?? '');
$mime   = $doc['type_mime'] ?: 'application/octet-stream';
$inline = empty($_GET['dl']) && in_array($ext, ['pdf','jpg','jpeg','png','webp'], true);

$nom       = $doc['nom_original'] ?: ('document.' . $ext);
$nom_ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nom);

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
     . '; filename="' . $nom_ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nom));
header('Content-Length: ' . filesize($chemin_abs));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($chemin_abs);
exit;
