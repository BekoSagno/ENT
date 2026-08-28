<?php
/**
 *
 *
 *  (définir RDV_DG_RAPPEL_TOKEN dans config si besoin)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../models/rendezvous_dg.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $tokenAttendu = defined('RDV_DG_RAPPEL_TOKEN') ? RDV_DG_RAPPEL_TOKEN : 'CRICT_RDV_RAPPEL';
    if (($_GET['token'] ?? '') !== $tokenAttendu) {
        http_response_code(403);
        exit('Forbidden');
    }
}

rdv_dg_assurer_schema();
$termines = rdv_dg_marquer_termines();
$rappels  = rdv_dg_envoyer_rappels_j1();

$msg = "OK — rappels J-1: $rappels — terminés: $termines\n";
if ($isCli) {
    echo $msg;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
}
