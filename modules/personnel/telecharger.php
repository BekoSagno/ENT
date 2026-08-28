<?php
// modules/personnel/telecharger.php — Service contrôlé des pièces RH
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/personnel.php';

session_demarrer();
exiger_connexion();

$type = clean($_GET['type'] ?? '');   // cv | diplome | certif
$id   = (int)($_GET['id'] ?? 0);
if (!$id || !in_array($type, ['cv', 'diplome', 'certif'], true)) {
    http_response_code(400);
    exit('Paramètres invalides.');
}

$db = get_db();

// Localiser le fichier + le personnel propriétaire
switch ($type) {
    case 'cv':
        $row = $db->prepare("SELECT personnel_id, fichier_cv AS chemin FROM personnel_cv WHERE id = ?");
        break;
    case 'diplome':
        $row = $db->prepare("SELECT personnel_id, fichier_diplome AS chemin FROM personnel_formations WHERE id = ?");
        break;
    case 'certif':
        $row = $db->prepare("SELECT personnel_id, document AS chemin FROM personnel_certifications WHERE id = ?");
        break;
}
$row->execute([$id]);
$piece = $row->fetch();

if (!$piece || empty($piece['chemin'])) {
    http_response_code(404);
    exit('Pièce introuvable.');
}

// Contrôle d'accès : propriétaire du dossier OU droit RH (VOIR)
$perso = personnel_par_id((int)$piece['personnel_id']);
$est_proprietaire = $perso && (int)$perso['utilisateur_id'] === (int)user_id();
if (!$est_proprietaire && !a_permission('PERSONNEL', 'VOIR')) {
    http_response_code(403);
    exit('Accès refusé.');
}

$chemin = UPLOAD_DIR . $piece['chemin'];
if (!is_file($chemin)) {
    http_response_code(404);
    exit('Fichier absent du serveur.');
}

$ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
$mimes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
$mime   = $mimes[$ext] ?? 'application/octet-stream';
$inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) ? 'inline' : 'attachment';

// Nom de fichier lisible
$nom_lisible = $type . '_' . preg_replace('/[^A-Za-z0-9]/', '', ($perso['nom'] ?? '') . ($perso['prenoms'] ?? '')) . '.' . $ext;

log_activite(user_id(), 'PERSONNEL', 'TELECHARGEMENT', "Pièce $type ID $id (profil {$piece['personnel_id']})");

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $inline . '; filename="' . $nom_lisible . '"');
header('Content-Length: ' . filesize($chemin));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
readfile($chemin);
exit;
