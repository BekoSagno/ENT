<?php
// modules/identite/search_auto.php — Recherche live ANAQ (JSON)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/auto_anaq.php';

session_demarrer();
exiger_permission('IDENTITE_CRICT', 'VOIR');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q = clean($_GET['q'] ?? '');
$en_edit = !empty($_GET['edit']);
if (mb_strlen($q) < 2) {
    echo json_encode(['results' => [], 'count' => 0, 'q' => $q], JSON_UNESCAPED_UNICODE);
    exit;
}

$results = auto_anaq_recherche($q, 20, $en_edit);
echo json_encode(['results' => $results, 'count' => count($results), 'q' => $q], JSON_UNESCAPED_UNICODE);
