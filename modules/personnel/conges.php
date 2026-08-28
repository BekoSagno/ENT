<?php
// modules/personnel/conges.php — Redirection vers Mes demandes
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';

$tab = clean($_GET['tab'] ?? 'mes');
$q   = $tab === 'valider' ? '?tab=valider' : '';
redirect('/modules/demandes/demandes.php' . $q);
