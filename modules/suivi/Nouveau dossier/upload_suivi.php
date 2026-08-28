<?php
/**
 * modules/suivi/upload_suivi.php
 * ─────────────────────────────────────────────────────────────
 * Endpoint AJAX pour :
 *   - POST  : uploader une pièce jointe sur une activité
 *   - DELETE (via POST ?_method=DELETE) : supprimer une PJ
 *   - GET   : télécharger / afficher une PJ
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

session_demarrer();

// ── Constantes ──────────────────────────────────────────────
// Adaptez UPLOAD_BASE_DIR à votre arborescence réelle
if (!defined('UPLOAD_BASE_DIR')) {
    define('UPLOAD_BASE_DIR', __DIR__ . '/../../uploads/suivi/');
}

const UPLOAD_MAX_SIZE   = 10 * 1024 * 1024;  // 10 Mo
const UPLOAD_MIME_ALLOW = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
];
const UPLOAD_EXT_ALLOW  = ['pdf','doc','docx','xls','xlsx','odt','ods'];

// ── Helpers locaux ──────────────────────────────────────────
function json_reponse(bool $ok, string $message, array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['succes' => $ok, 'message' => $message], $data));
    exit;
}

function verifier_acces_fiche(int $fiche_id, array $user): array {
    $db   = get_db();
    $stmt = $db->prepare('SELECT f.*, u.id AS owner_id FROM suivi_fiches_journalieres f
                           JOIN utilisateurs u ON u.id = f.utilisateur_id
                           WHERE f.id = ?');
    $stmt->execute([$fiche_id]);
    $fiche = $stmt->fetch();
    if (!$fiche) {
        json_reponse(false, 'Fiche introuvable.');
    }
    // Propriétaire ou superviseur
    $peut_valider = a_permission('SUIVI_QUOTIDIEN', 'VALIDER');
    if ((int)$fiche['owner_id'] !== (int)$user['id'] && !$peut_valider) {
        json_reponse(false, 'Accès refusé.');
    }
    return $fiche;
}

function verifier_acces_pj(int $pj_id, array $user): array {
    $db   = get_db();
    $stmt = $db->prepare('SELECT pj.*, f.utilisateur_id AS owner_id, f.statut AS fiche_statut
                           FROM suivi_pieces_jointes pj
                           JOIN suivi_fiches_journalieres f ON f.id = pj.fiche_id
                           WHERE pj.id = ?');
    $stmt->execute([$pj_id]);
    $pj = $stmt->fetch();
    if (!$pj) {
        json_reponse(false, 'Pièce jointe introuvable.');
    }
    $peut_valider = a_permission('SUIVI_QUOTIDIEN', 'VALIDER');
    if ((int)$pj['owner_id'] !== (int)$user['id'] && !$peut_valider) {
        json_reponse(false, 'Accès refusé.');
    }
    return $pj;
}

function creer_dossier(string $chemin): void {
    if (!is_dir($chemin) && !mkdir($chemin, 0750, true)) {
        json_reponse(false, 'Impossible de créer le dossier de stockage.');
    }
}

// ── Routage ─────────────────────────────────────────────────
exiger_connexion();
$user   = user_courant();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ╔════════════════════════════════╗
// ║  GET – Téléchargement          ║
// ╚════════════════════════════════╝
if ($method === 'GET' && $action === 'download') {
    $pj_id = (int)($_GET['id'] ?? 0);
    if (!$pj_id) {
        http_response_code(400);
        exit('Paramètre manquant.');
    }
    $pj = verifier_acces_pj($pj_id, $user);

    $chemin_abs = UPLOAD_BASE_DIR . $pj['chemin'];
    if (!file_exists($chemin_abs)) {
        http_response_code(404);
        exit('Fichier introuvable sur le serveur.');
    }

    $inline   = in_array($pj['extension'], ['pdf']);
    $dispo    = $inline ? 'inline' : 'attachment';

    header('Content-Type: '         . $pj['type_mime']);
    header('Content-Disposition: '  . $dispo . '; filename="' . rawurlencode($pj['nom_original']) . '"');
    header('Content-Length: '       . $pj['taille']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($chemin_abs);
    exit;
}

// À partir d'ici on est en POST → JSON
header('Content-Type: application/json; charset=utf-8');

if ($method !== 'POST') {
    json_reponse(false, 'Méthode non supportée.');
}

// Vérification CSRF
if (!csrf_verify()) {
    json_reponse(false, 'Jeton CSRF invalide.');
}

// ╔════════════════════════════════╗
// ║  POST action=upload            ║
// ╚════════════════════════════════╝
if ($action === 'upload') {

    $fiche_id   = (int)($_POST['fiche_id']   ?? 0);
    $activite_id = (int)($_POST['activite_id'] ?? 0);  // 0 = pas encore sauvegardé → sera rattaché après save

    if (!$fiche_id) {
        json_reponse(false, "Fiche non spécifiée. Enregistrez d'abord le brouillon.");
    }

    $fiche = verifier_acces_fiche($fiche_id, $user);

    // Fiche déjà validée : plus d'upload
    if ($fiche['statut'] === 'VALIDEE') {
        json_reponse(false, "Impossible d'ajouter des fichiers à une fiche validée.");
    }

    if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
        json_reponse(false, 'Aucun fichier reçu.');
    }

    $f = $_FILES['fichier'];

    // Erreur PHP upload
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $erreurs = [
            UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite serveur).',
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (limite formulaire).',
            UPLOAD_ERR_PARTIAL    => 'Transfert incomplet.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible décrire sur le disque.',
            UPLOAD_ERR_EXTENSION  => 'Upload bloqué par une extension PHP.',
        ];
        json_reponse(false, $erreurs[$f['error']] ?? 'Erreur upload inconnue.');
    }

    // Taille
    if ($f['size'] > UPLOAD_MAX_SIZE) {
        json_reponse(false, 'Fichier trop volumineux (max 10 Mo).');
    }

    // Extension
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_EXT_ALLOW, true)) {
        json_reponse(false, 'Extension non autorisée. Formats acceptés : PDF, DOC, DOCX, XLS, XLSX, ODT, ODS.');
    }

    // MIME via finfo (plus fiable que $_FILES['type'])
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime_reel = $finfo->file($f['tmp_name']);
    if (!in_array($mime_reel, UPLOAD_MIME_ALLOW, true)) {
        json_reponse(false, 'Type de fichier non autorisé.');
    }

    // Dossier de destination : uploads/suivi/YYYY/MM/fiche_{id}/
    $sous_dossier = date('Y') . '/' . date('m') . '/fiche_' . $fiche_id . '/';
    $dossier_abs  = UPLOAD_BASE_DIR . $sous_dossier;
    creer_dossier($dossier_abs);

    // Nom unique
    $nom_stockage = bin2hex(random_bytes(16)) . '.' . $ext;
    $chemin_rel   = $sous_dossier . $nom_stockage;
    $chemin_abs   = UPLOAD_BASE_DIR . $chemin_rel;

    if (!move_uploaded_file($f['tmp_name'], $chemin_abs)) {
        json_reponse(false, 'Échec du déplacement du fichier.');
    }

    // Insertion en base
    try {
        $db   = get_db();
        $stmt = $db->prepare('
            INSERT INTO suivi_pieces_jointes
                (activite_id, fiche_id, nom_original, nom_stockage, chemin, type_mime, taille, extension, televerse_par)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        // activite_id peut être 0 si la fiche vient juste d'être créée et les activités pas encore sauvées
        // Dans ce cas on le stocke à 0 et on le mettra à jour lors de la sauvegarde de la fiche
        $stmt->execute([
            $activite_id ?: null,
            $fiche_id,
            $f['name'],
            $nom_stockage,
            $chemin_rel,
            $mime_reel,
            $f['size'],
            $ext,
            (int)$user['id'],
        ]);
        $pj_id = (int)$db->lastInsertId();
    } catch (Throwable $e) {
        @unlink($chemin_abs);
        error_log('[CRICT upload_suivi] ' . $e->getMessage());
        json_reponse(false, 'Erreur base de données.');
    }

    // Libellé taille lisible
    $taille_aff = $f['size'] < 1024 * 1024
        ? round($f['size'] / 1024, 1) . ' Ko'
        : round($f['size'] / (1024 * 1024), 2) . ' Mo';

    $icones = [
        'pdf'  => 'fa-file-pdf',
        'doc'  => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls'  => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'odt'  => 'fa-file-lines',
        'ods'  => 'fa-file-lines',
    ];

    json_reponse(true, 'Fichier téléversé avec succès.', [
        'pj' => [
            'id'          => $pj_id,
            'nom'         => htmlspecialchars($f['name'], ENT_QUOTES),
            'taille'      => $taille_aff,
            'extension'   => $ext,
            'icone'       => $icones[$ext] ?? 'fa-file',
            'url_dl'      => 'upload_suivi.php?action=download&id=' . $pj_id,
        ],
    ]);
}

// ╔════════════════════════════════╗
// ║  POST action=supprimer         ║
// ╚════════════════════════════════╝
if ($action === 'supprimer') {

    $pj_id = (int)($_POST['pj_id'] ?? 0);
    if (!$pj_id) {
        json_reponse(false, 'Identifiant manquant.');
    }

    $pj    = verifier_acces_pj($pj_id, $user);
    $fiche = verifier_acces_fiche((int)$pj['fiche_id'], $user);

    if ($fiche['statut'] === 'VALIDEE') {
        json_reponse(false, "Impossible de supprimer un fichier d'une fiche validée.");
    }

    // Supprimer le fichier physique
    $chemin_abs = UPLOAD_BASE_DIR . $pj['chemin'];
    if (file_exists($chemin_abs)) {
        @unlink($chemin_abs);
    }

    // Supprimer en base
    try {
        $db = get_db();
        $db->prepare('DELETE FROM suivi_pieces_jointes WHERE id = ?')->execute([$pj_id]);
    } catch (Throwable $e) {
        error_log('[CRICT upload_suivi] supprimer : ' . $e->getMessage());
        json_reponse(false, 'Erreur lors de la suppression en base.');
    }

    json_reponse(true, 'Fichier supprimé.');
}

// ╔════════════════════════════════╗
// ║  POST action=lister            ║
// ╚════════════════════════════════╝
if ($action === 'lister') {

    $fiche_id    = (int)($_POST['fiche_id']    ?? 0);
    $activite_id = (int)($_POST['activite_id'] ?? 0);

    if (!$fiche_id) {
        json_reponse(false, 'Fiche non spécifiée.');
    }

    verifier_acces_fiche($fiche_id, $user);

    try {
        $db    = get_db();
        $where = 'fiche_id = ?';
        $param = [$fiche_id];
        if ($activite_id) {
            $where .= ' AND activite_id = ?';
            $param[]= $activite_id;
        }
        $stmt = $db->prepare("SELECT * FROM suivi_pieces_jointes WHERE $where ORDER BY cree_le ASC");
        $stmt->execute($param);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        json_reponse(false, 'Erreur base de données.');
    }

    $icones = [
        'pdf'  => 'fa-file-pdf',
        'doc'  => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls'  => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'odt'  => 'fa-file-lines',
        'ods'  => 'fa-file-lines',
    ];

    $liste = array_map(function ($r) use ($icones) {
        $taille = (int)$r['taille'];
        return [
            'id'        => (int)$r['id'],
            'nom'       => htmlspecialchars($r['nom_original'], ENT_QUOTES),
            'taille'    => $taille < 1024*1024
                            ? round($taille/1024, 1).' Ko'
                            : round($taille/(1024*1024), 2).' Mo',
            'extension' => $r['extension'],
            'icone'     => $icones[$r['extension']] ?? 'fa-file',
            'url_dl'    => 'upload_suivi.php?action=download&id='.$r['id'],
            'activite_id' => (int)$r['activite_id'],
        ];
    }, $rows);

    json_reponse(true, '', ['liste' => $liste]);
}

json_reponse(false, 'Action non reconnue.');
