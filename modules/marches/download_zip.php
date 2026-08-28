<?php
// modules/marches/download_zip.php — Téléchargement ZIP des pièces d'un fournisseur
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/marche.php';

session_demarrer();
exiger_permission('MARCHES','VOIR');

if (!class_exists(ZipArchive::class)) { http_response_code(501); exit('Extension ZIP non disponible.'); }

$user    = user_courant();
$uid     = (int)$user['id'];
$is_chef = in_array($user['groupe_code']??'',['SUPER_ADMIN','DG','DAF','PRMP']);

$id = (int)($_GET['id'] ?? 0);
$f  = (int)($_GET['f']  ?? 0);
if (!$id || $f < 1 || $f > 3) { http_response_code(400); exit('Paramètres invalides.'); }

$d = marche_get($id);
if (!$d || (!$is_chef && (int)$d['cree_par'] !== $uid)) { http_response_code(403); exit('Accès refusé.'); }

$cols = [
    'facture_'.$f    => 'facture_'.$f.'_path',
    'f'.$f.'_rccm'   => 'f'.$f.'_rccm_path',
    'f'.$f.'_nif'    => 'f'.$f.'_nif_path',
    'f'.$f.'_quittance' => 'f'.$f.'_quittance_path',
    'f'.$f.'_quitus' => 'f'.$f.'_quitus_path',
];

$entries = [];
foreach ($cols as $label => $col) {
    $rel = trim($d[$col] ?? '');
    if (!$rel) continue;
    $abs = UPLOAD_DIR . ltrim($rel, '/');
    if (!file_exists($abs) || strtolower(pathinfo($abs, PATHINFO_EXTENSION)) !== 'pdf') continue;
    $zip_name = 'F'.$f.'_'.preg_replace('/[^\p{L}\p{N}_]/u','_',$label).'.pdf';
    $entries[$zip_name] = $abs;
}

if (!$entries) { http_response_code(404); exit('Aucun document PDF disponible pour ce fournisseur.'); }

$tmp = tempnam(sys_get_temp_dir(), 'marche_zip_');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { http_response_code(500); @unlink($tmp); exit('Erreur ZIP.'); }
foreach ($entries as $zn => $ap) $zip->addFile($ap, $zn);
$zip->close();

$size = filesize($tmp);
$dl   = preg_replace('/[^\p{L}\p{N}_\-]/u','_',$d['numero_dossier']??'dossier').'_F'.$f.'_documents.zip';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.addslashes($dl).'"');
header('Content-Length: '.$size);
header('Cache-Control: private, no-store');
readfile($tmp);
@unlink($tmp);
exit;
