<?php
// modules/marches/file.php — Serveur de fichiers PDF sécurisé
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/marche.php';

session_demarrer();
exiger_permission('MARCHES','VOIR');

$user    = user_courant();
$uid     = (int)$user['id'];
$is_chef = in_array($user['groupe_code']??'',['SUPER_ADMIN','DG','DAF','PRMP']);

$id  = (int)($_GET['id']  ?? 0);
$doc = clean($_GET['doc'] ?? '');
if (!$id || !$doc) { http_response_code(400); exit('Paramètres invalides.'); }

marche_serve_file($id, $doc, $uid, $is_chef);
