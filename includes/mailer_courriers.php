<?php
// ============================================================
// includes/mailer_courriers.php
// Envoi d'emails liés aux courriers (PHPMailer) + templates
// Dépend de includes/mailer.php (mailer_make / envoyer_email)
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// S'assurer que le mailer de base est chargé
if (!function_exists('mailer_make')) {
    require_once __DIR__ . '/mailer.php';
}

// ──────────────────────────────────────────────────────────
// Construit un PHPMailer dont l'identité expéditrice est la
// boîte institutionnelle info@ (réponses aux correspondants).
// Repli automatique sur la config ent@ si info@ non renseignée.
// ──────────────────────────────────────────────────────────
function _courrier_mailer_institution(): PHPMailer {
    $mail = mailer_make(); // hérite host/port/sécurité/charset

    if (defined('MAIL_INFO_ADDRESS') && MAIL_INFO_ADDRESS !== '') {
        $nom = defined('MAIL_INFO_NAME') && MAIL_INFO_NAME !== '' ? MAIL_INFO_NAME : APP_SHORT;

        // Si la boîte info@ a ses propres identifiants SMTP, on s'authentifie
        // avec (sinon on garde l'auth ent@ et on ne change que l'en-tête From).
        if (defined('MAIL_INFO_PASS') && MAIL_INFO_PASS !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_INFO_ADDRESS;
            $mail->Password = MAIL_INFO_PASS;
        }

        try {
            $mail->setFrom(MAIL_INFO_ADDRESS, $nom);
            $mail->clearReplyTos();
            $mail->addReplyTo(MAIL_INFO_ADDRESS, $nom);
        } catch (Throwable $e) {
            error_log('[CRICT courrier_mailer_institution] '.$e->getMessage());
        }
    }
    return $mail;
}

// ──────────────────────────────────────────────────────────
// Envoi d'une RÉPONSE / transmission d'un courrier au
// correspondant, avec pièces jointes optionnelles.
//   $courrier  = ligne courriers (au minimum reference, objet…)
//   $to        = email destinataire
//   $sujet     = objet de l'email
//   $message   = corps (texte simple, converti en HTML)
//   $fichiers  = [['chemin'=>abs, 'nom'=>str], …]
// Retour : ['succes'=>bool,'message'=>string,'message_id'=>?string]
// ──────────────────────────────────────────────────────────
function courrier_envoyer_reponse(array $courrier, string $to, string $sujet, string $message, array $fichiers = []): array {
    if (!MAIL_ENABLED) {
        return ['succes'=>false,'message'=>'Envoi d\'emails désactivé.'];
    }
    if (!class_exists(PHPMailer::class)) {
        return ['succes'=>false,'message'=>'Librairie d\'envoi indisponible.'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['succes'=>false,'message'=>'Adresse email du destinataire invalide.'];
    }

    try {
        // Réponse à un correspondant externe → expéditeur = adresse
        // institutionnelle info@ (et non le compte technique ent@).
        $mail = _courrier_mailer_institution();
        $mail->addAddress($to, $courrier['correspondant_nom'] ?? '');
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = courrier_template_reponse($courrier, $message);
        $mail->AltBody = trim(strip_tags(str_replace(['<br>','</p>'], "\n", $message)));

        foreach ($fichiers as $f) {
            if (!empty($f['chemin']) && is_file($f['chemin'])) {
                $mail->addAttachment($f['chemin'], $f['nom'] ?? basename($f['chemin']));
            }
        }

        $mail->send();
        $message_id = method_exists($mail, 'getLastMessageID') ? $mail->getLastMessageID() : ($mail->MessageID ?? null);
        return ['succes'=>true,'message'=>'Email transmis avec succès.','message_id'=>$message_id];
    } catch (PHPMailerException $e) {
        error_log('[CRICT courrier_mail] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Échec de l\'envoi : '.$e->getMessage()];
    } catch (Throwable $e) {
        error_log('[CRICT courrier_mail] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de l\'envoi de l\'email.'];
    }
}

// ──────────────────────────────────────────────────────────
// Accusé de réception envoyé à l'expéditeur d'un courrier
// entrant (depuis info@, repli sur ent@).
// ──────────────────────────────────────────────────────────
function courrier_envoyer_accuse_reception(array $courrier): array {
    $to = strtolower(trim((string)($courrier['correspondant_email'] ?? '')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['succes'=>false,'message'=>'Adresse email de l\'expéditeur invalide.'];
    }

    $date_courrier = !empty($courrier['date_courrier']) ? date('d/m/Y', strtotime($courrier['date_courrier'])) : '';
    $date_recep    = !empty($courrier['date_reception']) ? date('d/m/Y', strtotime($courrier['date_reception'])) : date('d/m/Y');

    $message = 'Nous accusons bonne réception de votre courrier'
             . ($date_courrier !== '' ? ' daté du '.$date_courrier : '')
             . ', enregistré le '.$date_recep.' sous la référence indiquée ci-dessous.'
             . "\n\n"
             . 'Il a été transmis aux services compétents pour traitement. '
             . 'Merci de rappeler cette référence dans toute correspondance ultérieure.';

    $sujet = 'Accusé de réception — '.($courrier['reference'] ?? '').' — '.($courrier['objet'] ?? '');
    return courrier_envoyer_reponse($courrier, $to, $sujet, $message);
}

// ──────────────────────────────────────────────────────────
// Notification interne : un courrier vous a été affecté
//   $user     = ['prenoms','nom','email']
//   $courrier = ligne courriers
// ──────────────────────────────────────────────────────────
function courrier_notifier_affectation(array $user, array $courrier): array {
    $email = strtolower(trim($user['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['succes'=>false,'message'=>'Email du destinataire invalide.'];
    }
    $nom   = trim(($user['prenoms'] ?? '').' '.($user['nom'] ?? ''));
    $sujet = 'Courrier à traiter — '.($courrier['reference'] ?? '').' — '.APP_SHORT;
    $html  = courrier_template_affectation($user, $courrier);
    return envoyer_email($email, $nom, $sujet, $html);
}

// ══════════════════════════════════════════════════════════
// TEMPLATES HTML (table-based, styles inline — palette CRICT)
// ══════════════════════════════════════════════════════════

function _crict_mail_wrap(string $titre_entete, string $sous_titre, string $emoji_html, string $corps_html): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $app_name  = $h(APP_NAME);
    $app_short = $h(APP_SHORT);
    $annee     = date('Y');

    $blue950 = '#03144a'; $blue800 = '#0e3278'; $blue600 = '#1a5ccc';
    $teal    = '#00c6a2'; $bg = '#eef3fb'; $muted = '#6b7fa3'; $border = '#e2e8f5';

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$app_short}</title>
</head>
<body style="margin:0;padding:0;background:{$bg};">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{$bg};padding:28px 14px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0"
             style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;
                    box-shadow:0 8px 30px rgba(13,50,120,.12);font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
        <!-- En-tête -->
        <tr>
          <td style="background:linear-gradient(135deg,{$blue950} 0%,{$blue800} 55%,{$blue600} 100%);padding:30px 36px 26px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
              <td>
                <div style="font-size:21px;font-weight:800;color:#ffffff;letter-spacing:.5px;">{$titre_entete}</div>
                <div style="font-size:12px;color:#bdd3fd;margin-top:3px;">{$sous_titre}</div>
              </td>
              <td align="right">
                <span style="display:inline-block;width:46px;height:46px;border-radius:12px;
                             background:rgba(255,255,255,.12);text-align:center;line-height:46px;
                             font-size:20px;color:{$teal};">{$emoji_html}</span>
              </td>
            </tr></table>
          </td>
        </tr>
        <tr><td style="height:4px;background:{$teal};font-size:0;line-height:0;">&nbsp;</td></tr>
        <!-- Corps -->
        <tr><td style="padding:30px 36px 10px;">{$corps_html}</td></tr>
        <!-- Pied -->
        <tr>
          <td style="padding:22px 36px 30px;">
            <hr style="border:none;border-top:1px solid {$border};margin:0 0 16px;">
            <p style="margin:0;font-size:11.5px;line-height:1.6;color:{$muted};text-align:center;">
              Message émis par le service courrier de <strong>{$app_short}</strong>.<br>
              &copy; {$annee} {$app_name}. Tous droits réservés.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

// Template : réponse / transmission au correspondant
function courrier_template_reponse(array $courrier, string $message): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text  = '#1a2540'; $muted = '#6b7fa3'; $blue600 = '#1a5ccc'; $border = '#e2e8f5';

    $reference = $h($courrier['reference'] ?? '');
    $objet     = $h($courrier['objet'] ?? '');
    $nom       = $h($courrier['correspondant_nom'] ?? 'Madame, Monsieur');
    $message_h = nl2br($h($message));

    $corps = <<<HTML
<p style="margin:0 0 16px;font-size:14px;line-height:1.65;color:{$text};">Bonjour {$nom},</p>
<div style="font-size:14px;line-height:1.7;color:{$text};margin:0 0 22px;">{$message_h}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background:#f0f6ff;border:1px solid {$border};border-radius:12px;margin:0 0 8px;">
  <tr><td style="padding:14px 20px;">
    <div style="font-size:11px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;">Référence du courrier</div>
    <div style="font-size:15px;color:{$blue600};font-weight:700;font-family:'Courier New',monospace;margin-top:2px;">{$reference}</div>
    <div style="font-size:12px;color:{$muted};margin-top:8px;"><strong>Objet :</strong> {$objet}</div>
  </td></tr>
</table>

<p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:{$muted};">
  Cordialement,<br>Le Secrétariat de Direction
</p>
HTML;

    return _crict_mail_wrap(
        htmlspecialchars(APP_SHORT, ENT_QUOTES),
        'Centre de Recherche en Informatique &amp; Cyber-Technologie',
        '&#9993;', // ✉
        $corps
    );
}

// Template : notification d'affectation interne
function courrier_template_affectation(array $user, array $courrier): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text  = '#1a2540'; $muted = '#6b7fa3'; $blue600 = '#1a5ccc'; $border = '#e2e8f5';
    $teal  = '#00c6a2';

    $prenoms   = $h($user['prenoms'] ?? '');
    $reference = $h($courrier['reference'] ?? '');
    $objet     = $h($courrier['objet'] ?? '');
    $exp       = $h($courrier['correspondant_nom'] ?? $courrier['correspondant_organisation'] ?? '—');
    $instr     = trim((string)($courrier['instruction'] ?? ''));
    $instr_h   = $instr !== '' ? nl2br($h($instr)) : 'Aucune instruction particulière.';
    $url       = $h(rtrim(APP_URL,'/').'/modules/courriers/voir.php?id='.((int)($courrier['id'] ?? 0)));

    $labels    = function_exists('courrier_priorites') ? courrier_priorites() : [];
    $prio_key  = $courrier['priorite'] ?? 'NORMALE';
    $prio_lbl  = $h($labels[$prio_key]['label'] ?? $prio_key);
    $delai_row = '';
    if (!empty($courrier['delai_pilote'])) {
        $delai = $h(date('d/m/Y', strtotime($courrier['delai_pilote'])));
        $delai_row = <<<HTML
      <tr><td colspan="2" style="border-top:1px solid {$border};font-size:0;line-height:0;">&nbsp;</td></tr>
      <tr>
        <td style="padding:7px 0;font-size:12px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;">Délai</td>
        <td style="padding:7px 0;font-size:14px;color:#b91c1c;font-weight:700;">{$delai}</td>
      </tr>
HTML;
    }

    $corps = <<<HTML
<h1 style="margin:0 0 6px;font-size:18px;color:{$text};font-weight:700;">Bonjour {$prenoms},</h1>
<p style="margin:0 0 18px;font-size:14px;line-height:1.65;color:{$muted};">
  Un courrier vient de vous être <strong style="color:{$text};">affecté</strong> pour traitement.
  Vous en trouverez le détail ci-dessous.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background:#f0f6ff;border:1px solid {$border};border-radius:12px;margin:0 0 18px;">
  <tr><td style="padding:18px 22px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding:7px 0;font-size:12px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;width:38%;">Référence</td>
        <td style="padding:7px 0;font-size:14px;color:{$blue600};font-weight:700;font-family:'Courier New',monospace;">{$reference}</td>
      </tr>
      <tr><td colspan="2" style="border-top:1px solid {$border};font-size:0;line-height:0;">&nbsp;</td></tr>
      <tr>
        <td style="padding:7px 0;font-size:12px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;">Objet</td>
        <td style="padding:7px 0;font-size:14px;color:{$text};font-weight:600;">{$objet}</td>
      </tr>
      <tr><td colspan="2" style="border-top:1px solid {$border};font-size:0;line-height:0;">&nbsp;</td></tr>
      <tr>
        <td style="padding:7px 0;font-size:12px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;">Expéditeur</td>
        <td style="padding:7px 0;font-size:14px;color:{$text};font-weight:600;">{$exp}</td>
      </tr>
      <tr><td colspan="2" style="border-top:1px solid {$border};font-size:0;line-height:0;">&nbsp;</td></tr>
      <tr>
        <td style="padding:7px 0;font-size:12px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;">Priorité</td>
        <td style="padding:7px 0;font-size:14px;color:{$text};font-weight:600;">{$prio_lbl}</td>
      </tr>
      {$delai_row}
    </table>
  </td></tr>
</table>

<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:13px 18px;margin:0 0 22px;">
  <div style="font-size:11px;color:#92400e;text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-bottom:4px;">Instruction</div>
  <div style="font-size:13px;line-height:1.6;color:#7c5e10;">{$instr_h}</div>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
  <td align="center" style="padding:2px 0 8px;">
    <a href="{$url}" target="_blank"
       style="display:inline-block;background:linear-gradient(135deg,{$blue600},{$teal});
              color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;
              padding:13px 38px;border-radius:10px;box-shadow:0 6px 18px rgba(26,92,204,.32);">
      Ouvrir le courrier &rarr;
    </a>
  </td>
</tr></table>
HTML;

    return _crict_mail_wrap(
        htmlspecialchars(APP_SHORT, ENT_QUOTES),
        'Notification — Service Courrier',
        '&#128229;', // 📥
        $corps
    );
}

// ══════════════════════════════════════════════════════════
// DIFFUSION DES ÉVÉNEMENTS DU TRAITEMENT
// Notification ENT (badge « Courriers ») + email, sauf à l'auteur de l'action.
//   $destinataires = [['id' ou 'utilisateur_id','prenoms','nom','email'], …]
// ══════════════════════════════════════════════════════════
function courrier_diffuser(array $destinataires, array $courrier, string $titre, string $intro, string $message = '', bool $email = true): int {
    $moi = function_exists('user_id') ? (int)user_id() : 0;
    $cid = (int)($courrier['id'] ?? 0);
    $vus = [];
    $n   = 0;
    foreach ($destinataires as $u) {
        // Lignes d'affectation : « id » est celui de l'affectation, l'utilisateur est « utilisateur_id »
        $uid = (int)($u['utilisateur_id'] ?? $u['id'] ?? 0);
        if ($uid <= 0 || $uid === $moi || isset($vus[$uid])) continue;
        $vus[$uid] = true;
        courrier_notif_creer($uid, $cid, $titre, $intro);
        if ($email && filter_var($u['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $nom = trim(($u['prenoms'] ?? '') . ' ' . ($u['nom'] ?? ''));
            $r = envoyer_email($u['email'], $nom,
                $titre . ' — ' . ($courrier['reference'] ?? '') . ' — ' . APP_SHORT,
                courrier_template_evenement($u, $courrier, $titre, $intro, $message));
            if (!$r['succes']) error_log('[CRICT courrier_diffuser] ' . $u['email'] . ' : ' . $r['message']);
        }
        $n++;
    }
    return $n;
}

// Nouveaux courriers entrants → le DG est prévenu pour les orienter
function courrier_notifier_nouveaux_entrants(array $courriers): void {
    if (empty($courriers)) return;
    $dgs = courrier_utilisateurs_dg();
    if (count($courriers) === 1) {
        $c = $courriers[0];
        courrier_diffuser($dgs, $c, 'Nouveau courrier à orienter',
            'Un courrier entrant de ' . ($c['correspondant_organisation'] ?: ($c['correspondant_nom'] ?: 'expéditeur inconnu'))
            . ' vient d\'être enregistré : « ' . ($c['objet'] ?? '') . ' ». Il attend votre affectation.');
        return;
    }
    // Plusieurs courriers (relève info@) : une notification ENT chacun, un seul email récapitulatif
    $lignes = [];
    foreach ($courriers as $c) {
        courrier_diffuser($dgs, $c, 'Nouveau courrier à orienter', 'Courrier entrant « ' . ($c['objet'] ?? '') . ' » à affecter.', '', false);
        $lignes[] = ($c['reference'] ?? '') . ' — ' . ($c['objet'] ?? '');
    }
    foreach ($dgs as $u) {
        if (!filter_var($u['email'] ?? '', FILTER_VALIDATE_EMAIL)) continue;
        $html = courrier_template_evenement($u, $courriers[0], count($courriers) . ' nouveaux courriers à orienter',
            count($courriers) . ' courriers entrants viennent d\'être enregistrés et attendent votre affectation.',
            implode("\n", $lignes), rtrim(APP_URL, '/') . '/modules/courriers/index.php?statut=RECU');
        envoyer_email($u['email'], trim($u['prenoms'] . ' ' . $u['nom']), count($courriers) . ' nouveaux courriers à orienter — ' . APP_SHORT, $html);
    }
}

// Template générique : un événement sur un courrier (soumission, validation, message…)
function courrier_template_evenement(array $user, array $courrier, string $titre, string $intro, string $message = '', string $url = ''): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = '#1a2540'; $muted = '#6b7fa3'; $blue600 = '#1a5ccc'; $border = '#e2e8f5'; $teal = '#00c6a2';

    $prenoms   = $h($user['prenoms'] ?? '');
    $titre_h   = $h($titre);
    $intro_h   = nl2br($h($intro));
    $reference = $h($courrier['reference'] ?? '');
    $objet     = $h($courrier['objet'] ?? '');
    $url       = $h($url !== '' ? $url : rtrim(APP_URL, '/') . '/modules/courriers/voir.php?id=' . (int)($courrier['id'] ?? 0) . '&focus=echanges');
    $bloc_msg  = trim($message) !== ''
        ? '<div style="background:#f8fafc;border-left:4px solid ' . $blue600 . ';border-radius:8px;padding:12px 16px;margin:0 0 20px;font-size:13px;line-height:1.6;color:' . $text . ';">'
          . nl2br($h($message)) . '</div>'
        : '';

    $corps = <<<HTML
<h1 style="margin:0 0 6px;font-size:18px;color:{$text};font-weight:700;">Bonjour {$prenoms},</h1>
<p style="margin:0 0 6px;font-size:15px;color:{$blue600};font-weight:700;">{$titre_h}</p>
<p style="margin:0 0 18px;font-size:14px;line-height:1.65;color:{$muted};">{$intro_h}</p>
{$bloc_msg}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background:#f0f6ff;border:1px solid {$border};border-radius:12px;margin:0 0 20px;">
  <tr><td style="padding:14px 20px;">
    <div style="font-size:11px;color:{$muted};text-transform:uppercase;letter-spacing:.5px;">Courrier</div>
    <div style="font-size:15px;color:{$blue600};font-weight:700;font-family:'Courier New',monospace;margin-top:2px;">{$reference}</div>
    <div style="font-size:13px;color:{$text};margin-top:6px;">{$objet}</div>
  </td></tr>
</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
  <td align="center" style="padding:2px 0 8px;">
    <a href="{$url}" target="_blank"
       style="display:inline-block;background:linear-gradient(135deg,{$blue600},{$teal});
              color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;
              padding:13px 38px;border-radius:10px;box-shadow:0 6px 18px rgba(26,92,204,.32);">
      Ouvrir dans l'ENT &rarr;
    </a>
  </td>
</tr></table>
HTML;

    return _crict_mail_wrap(
        htmlspecialchars(APP_SHORT, ENT_QUOTES),
        'Notification — Service Courrier',
        '&#9993;', // ✉
        $corps
    );
}
