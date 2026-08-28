<?php
// ============================================================
// includes/mailer.php — Envoi d'emails (PHPMailer) + templates
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ──────────────────────────────────────────────────────────
// Chargement de PHPMailer (Composer ou manuel)
//   - Composer : composer require phpmailer/phpmailer  → vendor/autoload.php
//   - Manuel   : déposer la lib dans libs/PHPMailer/src/
// ──────────────────────────────────────────────────────────
if (!class_exists(PHPMailer::class)) {
    $autoload = APP_ROOT . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    } else {
        $base = APP_ROOT . '/libs/PHPMailer/src/';
        foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $f) {
            if (is_file($base . $f)) require_once $base . $f;
        }
    }
}

// ──────────────────────────────────────────────────────────
// Valeurs SMTP par défaut (surchargées par config/config.php)
// ──────────────────────────────────────────────────────────

if (!defined('MAIL_ENABLED'))   define('MAIL_ENABLED', true);
if (!defined('MAIL_HOST'))      define('MAIL_HOST', 'mail.supremecluster.com');
if (!defined('MAIL_PORT'))      define('MAIL_PORT', 465);
if (!defined('MAIL_SECURE'))    define('MAIL_SECURE', 'ssl'); // 'tls' | 'ssl' | ''
if (!defined('MAIL_USER'))      define('MAIL_USER', 'ent@crict.edu.gn');
if (!defined('MAIL_PASS'))      define('MAIL_PASS', '');
if (!defined('MAIL_FROM'))      define('MAIL_FROM', 'ent@crict.edu.gn');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', APP_SHORT);
if (!defined('MAIL_REPLY_TO'))  define('MAIL_REPLY_TO', 'ent@crict.edu.gn');
if (!defined('MAIL_DEBUG'))     define('MAIL_DEBUG', false);

// ──────────────────────────────────────────────────────────
// Instancie un PHPMailer configuré en SMTP
// ──────────────────────────────────────────────────────────
function mailer_make(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->Port       = (int) MAIL_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';

    if (MAIL_USER !== '') {
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USER;
        $mail->Password = MAIL_PASS;
    }
    if (MAIL_SECURE === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif (MAIL_SECURE === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    if (MAIL_DEBUG) {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function ($str, $level) { error_log("[CRICT SMTP] $str"); };
    }

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    if (MAIL_REPLY_TO !== '') $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);

    return $mail;
}

// ──────────────────────────────────────────────────────────
// Envoi générique d'un email HTML
//   Retourne ['succes'=>bool, 'message'=>string]
// ──────────────────────────────────────────────────────────
function envoyer_email(string $to, string $toName, string $sujet, string $html, string $texte = ''): array {
    if (!MAIL_ENABLED) {
        return ['succes' => false, 'message' => 'Envoi d\'emails désactivé.'];
    }
    if (!class_exists(PHPMailer::class)) {
        error_log('[CRICT mailer] PHPMailer introuvable (Composer ou libs/ manquant).');
        return ['succes' => false, 'message' => 'Librairie d\'envoi indisponible.'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['succes' => false, 'message' => 'Adresse email invalide.'];
    }

    try {
        $mail = mailer_make();
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $html;
        $mail->AltBody = $texte !== '' ? $texte : trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
        $mail->send();
        return ['succes' => true, 'message' => 'Email envoyé.'];
    } catch (PHPMailerException $e) {
        error_log('[CRICT mailer] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Échec de l\'envoi : ' . $e->getMessage()];
    } catch (Throwable $e) {
        error_log('[CRICT mailer] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de l\'envoi de l\'email.'];
    }
}

// ──────────────────────────────────────────────────────────
// Notification : identifiants de connexion d'un nouvel utilisateur
//   $user = ['prenoms','nom','email','matricule']
// ──────────────────────────────────────────────────────────
function notifier_identifiants_utilisateur(array $user, string $mot_de_passe): array {
    $nom_complet = trim(($user['prenoms'] ?? '') . ' ' . ($user['nom'] ?? ''));
    $identifiant = strtolower(trim($user['email'] ?? ''));
    $html  = email_template_identifiants($user, $mot_de_passe);
    $sujet = 'Vos identifiants de connexion — ' . APP_SHORT;
    return envoyer_email($identifiant, $nom_complet, $sujet, $html);
}

// ──────────────────────────────────────────────────────────
// Template HTML moderne (table-based, styles inline pour
// compatibilité maximale avec les clients de messagerie)
// ──────────────────────────────────────────────────────────
function email_template_identifiants(array $user, string $mot_de_passe): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $prenoms     = $h($user['prenoms'] ?? '');
    $nom         = $h($user['nom'] ?? '');
    $matricule   = $h($user['matricule'] ?? '');
    $identifiant = $h(strtolower(trim($user['email'] ?? '')));
    $mdp         = $h($mot_de_passe);
    $login_url   = $h(rtrim(APP_URL, '/') . '/login.php');
    $app_name    = $h(APP_NAME);
    $app_short   = $h(APP_SHORT);
    $annee       = date('Y');

    // Palette CRICT
    $blue950 = '#03144a'; $blue800 = '#0e3278'; $blue600 = '#1a5ccc';
    $teal    = '#00c6a2'; $amberBg = '#fef3c7'; $amberBd = '#fde68a'; $amberTx = '#92400e';
    $bg      = '#eef3fb'; $muted = '#6b7fa3'; $text = '#1a2540'; $border = '#e2e8f5';

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{$app_short}</title>
</head>
<body style="margin:0;padding:0;background:{$bg};">
  <!-- Préheader (masqué) -->
  <span style="display:none;max-height:0;overflow:hidden;opacity:0;color:{$bg};">
    Votre compte {$app_short} a été créé. Voici vos identifiants de connexion.
  </span>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{$bg};padding:28px 14px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0"
               style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;
                      box-shadow:0 8px 30px rgba(13,50,120,.12);font-family:'Segoe UI',Helvetica,Arial,sans-serif;">

          <!-- En-tête -->
          <tr>
            <td style="background:linear-gradient(135deg,{$blue950} 0%,{$blue800} 55%,{$blue600} 100%);padding:34px 36px 30px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td>
                    <div style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:.5px;">{$app_short}</div>
                    <div style="font-size:12px;color:#bdd3fd;margin-top:3px;">Centre de Recherche en Informatique &amp; Cyber-Technologie</div>
                  </td>
                  <td align="right">
                    <span style="display:inline-block;width:46px;height:46px;border-radius:12px;
                                 background:rgba(255,255,255,.12);text-align:center;line-height:46px;
                                 font-size:20px;color:{$teal};">&#128274;</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <!-- Filet teal -->
          <tr><td style="height:4px;background:{$teal};font-size:0;line-height:0;">&nbsp;</td></tr>

          <!-- Corps -->
          <tr>
            <td style="padding:34px 36px 10px;">
              <h1 style="margin:0 0 6px;font-size:19px;color:{$text};font-weight:700;">
                Bienvenue, {$prenoms} {$nom} 👋
              </h1>
              <p style="margin:0 0 18px;font-size:14px;line-height:1.65;color:{$muted};">
                Votre compte sur la plateforme <strong style="color:{$text};">{$app_name}</strong>
                vient d'être créé. Voici vos identifiants personnels pour vous connecter :
              </p>

              <!-- Carte identifiants -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                     style="background:#f0f6ff;border:1px solid {$border};border-radius:12px;margin:0 0 22px;">
                <tr>
                  <td style="padding:18px 22px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="padding:8px 0;font-size:12px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;width:42%;">Matricule</td>
                        <td style="padding:8px 0;font-size:15px;color:{$text};font-weight:700;font-family:'Courier New',monospace;">{$matricule}</td>
                      </tr>
                      <tr><td colspan="2" style="border-top:1px solid {$border};font-size:0;line-height:0;">&nbsp;</td></tr>
                      <tr>
                        <td style="padding:8px 0;font-size:12px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;">Identifiant</td>
                        <td style="padding:8px 0;font-size:14px;color:{$text};font-weight:600;word-break:break-all;">{$identifiant}</td>
                      </tr>
                      <tr><td colspan="2" style="border-top:1px solid {$border};font-size:0;line-height:0;">&nbsp;</td></tr>
                      <tr>
                        <td style="padding:8px 0;font-size:12px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;">Mot de passe temporaire</td>
                        <td style="padding:8px 0;">
                          <span style="display:inline-block;background:{$blue600};color:#ffffff;font-family:'Courier New',monospace;
                                       font-size:15px;font-weight:700;padding:6px 14px;border-radius:7px;letter-spacing:.5px;">{$mdp}</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <!-- Avertissement sécurité -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                     style="background:{$amberBg};border:1px solid {$amberBd};border-radius:10px;margin:0 0 26px;">
                <tr>
                  <td style="padding:14px 18px;font-size:13px;line-height:1.6;color:{$amberTx};">
                    <strong>&#9888; Important :</strong> pour des raisons de sécurité, vous devrez
                    <strong>changer ce mot de passe</strong> dès votre première connexion. Ne partagez
                    jamais vos identifiants avec un tiers.
                  </td>
                </tr>
              </table>

              <!-- Bouton -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center" style="padding:2px 0 8px;">
                    <a href="{$login_url}" target="_blank"
                       style="display:inline-block;background:linear-gradient(135deg,{$blue600},{$teal});
                              color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;
                              padding:13px 38px;border-radius:10px;box-shadow:0 6px 18px rgba(26,92,204,.32);">
                      Se connecter &rarr;
                    </a>
                  </td>
                </tr>
              </table>
              <p style="margin:14px 0 0;font-size:12px;line-height:1.5;color:{$muted};text-align:center;">
                Ou copiez ce lien : <a href="{$login_url}" style="color:{$blue600};">{$login_url}</a>
              </p>
            </td>
          </tr>

          <!-- Pied -->
          <tr>
            <td style="padding:24px 36px 30px;">
              <hr style="border:none;border-top:1px solid {$border};margin:0 0 16px;">
              <p style="margin:0;font-size:11.5px;line-height:1.6;color:{$muted};text-align:center;">
                Cet email a été envoyé automatiquement par <strong>{$app_short}</strong>, merci de ne pas y répondre.<br>
                &copy; {$annee} {$app_name}. Tous droits réservés.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}