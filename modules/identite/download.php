<?php
// modules/identite/download.php — Téléchargement sécurisé des pièces
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/identite.php';

session_demarrer();
exiger_permission('IDENTITE_CRICT', 'VOIR');

$piece_id = (int)($_GET['id'] ?? 0);
$download = isset($_GET['dl']);

identite_servir_fichier($piece_id, $download);
