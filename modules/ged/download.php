<?php
// modules/ged/download.php — Service sécurisé des fichiers GED
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/ged.php';

session_demarrer();
exiger_permission('GED', 'VOIR');

$doc_id = (int)($_GET['id'] ?? 0);
$forcer = isset($_GET['dl']);  // dl=1 → téléchargement, sinon aperçu inline

// Le contrôle d'accès (héritage, restreint, propriétaire, groupe) est dans le modèle.
ged_servir_fichier($doc_id, (int)user_id(), $forcer);
