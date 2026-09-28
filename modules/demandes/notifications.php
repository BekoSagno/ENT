<?php
// Ancien centre de notifications → Option A : redirection vers l'alerte ciblée
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

session_demarrer();
exiger_connexion();
redirect('/modules/demandes/demandes.php?voir_alerte=1');
