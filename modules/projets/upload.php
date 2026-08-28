<?php
// modules/projets/upload.php — Téléchargement sécurisé
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet.php';

session_demarrer();
exiger_connexion();

$user  = user_courant();
$id    = (int)($_GET['id']   ?? 0);
$type  = clean($_GET['type'] ?? 'output'); // output | projet

if (!$id) { http_response_code(400); exit('Paramètre manquant.'); }

$db = get_db();

if ($type === 'projet') {
    $s = $db->prepare('SELECT * FROM projets_documents WHERE id=?');
    $s->execute([$id]);
    $doc = $s->fetch();
    if (!$doc) { http_response_code(404); exit('Introuvable.'); }

    if (!_projet_est_membre((int)$doc['projet_id'], (int)$user['id'])
        && !_projet_est_chef((int)$doc['projet_id'], (int)$user['id'])) {
        http_response_code(403); exit('Accès refusé.');
    }
    $chemin = UPLOAD_DIR . $doc['chemin'];
    $nom    = $doc['nom_original'];
    $mime   = $doc['type_mime'];
    $ext    = $doc['extension'];

} else {
    $s = $db->prepare('SELECT * FROM projets_taches_outputs WHERE id=?');
    $s->execute([$id]);
    $out = $s->fetch();
    if (!$out || empty($out['chemin'])) { http_response_code(404); exit('Introuvable.'); }

    if (!_projet_est_membre((int)$out['projet_id'], (int)$user['id'])
        && !_projet_est_chef((int)$out['projet_id'], (int)$user['id'])) {
        http_response_code(403); exit('Accès refusé.');
    }
    $chemin = UPLOAD_DIR . $out['chemin'];
    $nom    = $out['nom_original'];
    $mime   = $out['type_mime'];
    $ext    = $out['extension'];
}

if (!file_exists($chemin)) { http_response_code(404); exit('Fichier absent du serveur.'); }

// Inline pour PDF et images, attachment pour le reste
$inline = ['pdf','jpg','jpeg','png','webp','gif'];
$dispo  = in_array(strtolower($ext), $inline) ? 'inline' : 'attachment';

header('Content-Type: '        . $mime);
header('Content-Disposition: ' . $dispo . '; filename="' . rawurlencode($nom) . '"');
header('Content-Length: '      . filesize($chemin));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($chemin);
exit;
