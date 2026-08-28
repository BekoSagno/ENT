<?php
// modules/identite/search.php — Recherche instantanée (JSON)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/identite.php';

session_demarrer();
exiger_permission('IDENTITE_CRICT', 'VOIR');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q = clean($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['results' => [], 'count' => 0, 'q' => '']);
    exit;
}

$results = identite_recherche_etendue($q, 50);
$payload = [];
foreach ($results as $r) {
    $payload[] = [
        'type'    => $r['type'],
        'code'    => $r['code'],
        'titre'   => $r['titre'],
        'sous'    => $r['sous'],
        'url'     => $r['url'],
        'snippet' => $r['snippet'],
        'score'   => $r['score'],
        'champ_id'=> $r['champ_id'] ?? null,
    ];
}

echo json_encode([
    'results' => $payload,
    'count'   => count($payload),
    'q'       => $q,
], JSON_UNESCAPED_UNICODE);
