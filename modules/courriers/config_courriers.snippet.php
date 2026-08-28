<?php
// ============================================================
//  À COLLER DANS  config/config.php
//  (après le bloc des constantes MAIL_* existantes)
//
//  Distinction des deux adresses :
//   • ent@crict.edu.gn  = compte TECHNIQUE du système (type noreply).
//       → déjà configuré via MAIL_USER / MAIL_FROM / MAIL_PASS.
//       → sert aux envois automatiques (identifiants, notifications internes).
//   • info@crict.edu.gn = boîte INSTITUTIONNELLE de l'assistant(e) de direction.
//       → adresse publique où écrivent les partenaires.
//       → on la RELÈVE en IMAP (réception des courriers entrants)
//       → et les RÉPONSES aux correspondants partent en son nom (SMTP).
// ============================================================

// ── Identité institutionnelle (info@) ──────────────────────
define('MAIL_INFO_ADDRESS', 'info@crict.edu.gn');   // adresse publique
define('MAIL_INFO_NAME',    'CRICT — Direction');   // nom affiché dans le From
define('MAIL_INFO_PASS',    '');                     // ⚠ mot de passe de la boîte info@ (IMAP + SMTP)

// ── Réception IMAP de la boîte info@ ───────────────────────
// Renseigner le serveur IMAP de l'hébergeur de crict.edu.gn.
// (souvent le même hôte que le SMTP, ex. mail.supremecluster.com)
define('IMAP_INFO_HOST',          'mail.crict.edu.gn'); // ⚠ à adapter (serveur IMAP réel)
define('IMAP_INFO_PORT',          993);                 // 993 = IMAP over SSL
define('IMAP_INFO_ENCRYPTION',    'ssl');               // 'ssl' | 'tls'
define('IMAP_INFO_FOLDER',        'INBOX');             // dossier à relever
define('IMAP_INFO_VALIDATE_CERT', true);               // false si certificat auto-signé

// Remarques :
//  • L'extension PHP « imap » doit être activée (php-imap).
//      Ubuntu/Debian : sudo apt install php-imap && sudo phpenmod imap
//      cPanel/WHM    : activer « imap » dans l'éditeur d'extensions PHP.
//  • Les RÉPONSES sortantes réutilisent MAIL_HOST / MAIL_PORT / MAIL_SECURE
//    (mêmes serveur SMTP), mais s'authentifient avec MAIL_INFO_ADDRESS +
//    MAIL_INFO_PASS et posent From: info@. Si MAIL_INFO_PASS est vide,
//    le système se rabat proprement sur le compte ent@ pour l'envoi.
