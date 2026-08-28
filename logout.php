<?php
// logout.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
session_demarrer();
auth_deconnecter();
redirect('/login.php');
