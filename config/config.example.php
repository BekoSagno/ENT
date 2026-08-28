<?php
// ============================================================
// config/config.example.php — Modèle de configuration CRICT ENT
// Copier vers config.php et renseigner les valeurs réelles.
// ============================================================

define('APP_NAME',    'CRICT – Système de Gestion Administrative');
define('APP_SHORT',   'CRICT-ENT');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost:8000');
define('APP_ROOT',    dirname(__DIR__));

// --- Base de données ---
define('DB_HOST',     'localhost');
define('DB_PORT',     3306);
define('DB_NAME',     'crict_ent');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// --- Sécurité ---
define('SESSION_LIFETIME',  7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION',   900);
define('BCRYPT_COST',        12);
define('CSRF_TOKEN_LENGTH',  32);
define('CURRENCY', 'GNF');

// --- Email / SMTP ---
define('MAIL_ENABLED',   true);
define('MAIL_HOST',      'mail.example.com');
define('MAIL_PORT',      465);
define('MAIL_SECURE',    'ssl');
define('MAIL_USER',      'ent@example.com');
define('MAIL_PASS',      'CHANGER_MOI');
define('MAIL_FROM',      'ent@example.com');
define('MAIL_FROM_NAME', APP_SHORT);
define('MAIL_REPLY_TO',  'ent@example.com');
define('MAIL_DEBUG',     false);

// --- Uploads ---
define('UPLOAD_DIR',  APP_ROOT . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMG_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

define('MAIL_INFO_ADDRESS', 'info@example.com');
define('MAIL_INFO_NAME',    'CRICT — Direction');
define('MAIL_INFO_PASS',    'CHANGER_MOI');

define('IMAP_INFO_HOST',          'mail.example.com');
define('IMAP_INFO_PORT',          993);
define('IMAP_INFO_ENCRYPTION',    'ssl');
define('IMAP_INFO_FOLDER',        'INBOX');
define('IMAP_INFO_VALIDATE_CERT', true);

date_default_timezone_set('Africa/Conakry');

ini_set('display_errors', 0);
error_reporting(E_ALL);
