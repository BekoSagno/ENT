<?php
// ============================================================
// includes/mailer_instructions.php
//   Notifie chaque responsable de SA tâche d'instruction.
//   Dépendances : config.php, database.php, includes/mailer.php
// ============================================================

require_once __DIR__ . '/mailer.php';

// Notifier les responsables des tâches d'une instruction.
//   $only_tache_ids : si fourni, n'envoie que pour ces tâches.
function notifier_instruction(int $instruction_id, ?array $only_tache_ids = null): array {
    if (!MAIL_ENABLED) return ['succes'=>false,'envoyes'=>0,'echecs'=>0];

    $db = get_db();
    $si = $db->prepare("SELECT i.id, i.code, i.objet, CONCAT(ue.prenoms,' ',ue.nom) AS emetteur_nom
                        FROM instructions i JOIN utilisateurs ue ON ue.id=i.emetteur_id WHERE i.id=?");
    $si->execute([$instruction_id]);
    $inst = $si->fetch();
    if (!$inst) return ['succes'=>false,'envoyes'=>0,'echecs'=>0];

    $sql = "
        SELECT t.id, t.code, t.titre, t.description, t.priorite, t.statut,
               t.date_echeance, t.delai_valeur, t.delai_unite, t.dependance_type,
               u.email, u.prenoms, u.nom,
               td.titre AS depend_titre, td.code AS depend_code
        FROM instructions_taches t
        JOIN utilisateurs u           ON u.id = t.assigne_a
        LEFT JOIN instructions_taches td ON td.id = t.depend_de
        WHERE t.instruction_id=?
    ";
    $params = [$instruction_id];
    if ($only_tache_ids) {
        $ids = array_values(array_filter(array_map('intval', $only_tache_ids)));
        if (empty($ids)) return ['succes'=>true,'envoyes'=>0,'echecs'=>0];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql .= " AND t.id IN ($in)";
        $params = array_merge($params, $ids);
    }
    $st = $db->prepare($sql);
    $st->execute($params);

    $envoyes = 0; $echecs = 0;
    foreach ($st->fetchAll() as $t) {
        if (!filter_var($t['email'], FILTER_VALIDATE_EMAIL)) { $echecs++; continue; }
        $nom   = trim($t['prenoms'] . ' ' . $t['nom']);
        $sujet = 'Nouvelle tâche — ' . $inst['objet'] . ' (' . $inst['code'] . ') — ' . APP_SHORT;
        $html  = email_template_instruction_tache($inst, $t);
        envoyer_email($t['email'], $nom, $sujet, $html)['succes'] ? $envoyes++ : $echecs++;
    }

    log_activite(user_id(), 'INSTRUCTIONS', 'NOTIF',
        "Instruction {$inst['code']} : $envoyes envoi(s), $echecs échec(s)",
        ($echecs > 0 && $envoyes === 0) ? 'ECHEC' : 'SUCCES');
    return ['succes'=>($envoyes>0), 'envoyes'=>$envoyes, 'echecs'=>$echecs];
}

function email_template_instruction_tache(array $inst, array $t): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $blue950='#03144a'; $blue800='#0e3278'; $blue600='#1a5ccc'; $teal='#00c6a2';
    $bg='#eef3fb'; $muted='#6b7fa3'; $text='#1a2540'; $border='#e2e8f5';

    $prenom    = $h($t['prenoms']);
    $emetteur  = $h(trim($inst['emetteur_nom'] ?? '') ?: 'La Direction');
    $inst_obj  = $h($inst['objet']);
    $inst_code = $h($inst['code']);
    $t_titre   = $h($t['titre']);
    $t_desc    = trim((string)($t['description'] ?? ''));
    $app_name  = $h(APP_NAME); $app_short = $h(APP_SHORT); $annee = date('Y');
    $url = $h(rtrim(APP_URL, '/') . '/modules/instructions/voir.php?id=' . (int)$inst['id'] . '#t' . (int)$t['id']);

    // Délai / échéance
    $delai = (int)$t['delai_valeur'];
    $u_lbl = ['MINUTE'=>'minute','HEURE'=>'heure','JOUR'=>'jour'][$t['delai_unite'] ?? 'HEURE'] ?? 'heure';
    $delai_txt = $h($delai . ' ' . $u_lbl . ($delai > 1 ? 's' : ''));

    $bloquee = $t['statut'] === 'BLOQUEE';
    if ($bloquee) {
        $dep = $h(($t['depend_code'] ? $t['depend_code'].' — ' : '') . ($t['depend_titre'] ?? ''));
        $ech_html = '<span style="color:#9a3412;font-weight:700;">En attente</span> — démarre après : ' . $dep
                  . ' <span style="color:'.$muted.';">(puis ' . $delai_txt . ')</span>';
    } else {
        $dl = new DateTime($t['date_echeance']);
        $ech_html = '<strong>' . $h($dl->format('d/m/Y à H:i')) . '</strong> <span style="color:'.$muted.';">(sous ' . $delai_txt . ')</span>';
    }

    $prio_map = ['CRITIQUE'=>['#fee2e2','#991b1b','Critique'],'HAUTE'=>['#ffedd5','#9a3412','Haute'],
                 'NORMALE'=>['#dbeafe','#1e40af','Normale'],'FAIBLE'=>['#f3f4f6','#6b7280','Faible']];
    $pr = $prio_map[$t['priorite'] ?? 'NORMALE'] ?? $prio_map['NORMALE'];

    $desc_bloc = $t_desc !== ''
        ? '<p style="margin:0 0 16px;font-size:14px;line-height:1.65;color:'.$muted.';">'.nl2br($h($t_desc)).'</p>' : '';

    return <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{$app_short}</title></head>
<body style="margin:0;padding:0;background:{$bg};">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{$bg};padding:28px 14px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0"
             style="max-width:600px;width:100%;background:#fff;border-radius:16px;overflow:hidden;
                    box-shadow:0 8px 30px rgba(13,50,120,.12);font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
        <tr><td style="background:linear-gradient(135deg,{$blue950} 0%,{$blue800} 55%,{$blue600} 100%);padding:34px 36px 30px;">
          <table role="presentation" width="100%"><tr>
            <td><div style="font-size:22px;font-weight:800;color:#fff;letter-spacing:.5px;">{$app_short}</div>
                <div style="font-size:12px;color:#bdd3fd;margin-top:3px;">Instruction de la Direction</div></td>
            <td align="right"><span style="display:inline-block;width:46px;height:46px;border-radius:12px;
                 background:rgba(255,255,255,.12);text-align:center;line-height:46px;font-size:20px;color:{$teal};">&#128227;</span></td>
          </tr></table>
        </td></tr>
        <tr><td style="height:4px;background:{$teal};font-size:0;line-height:0;">&nbsp;</td></tr>

        <tr><td style="padding:34px 36px 10px;">
          <h1 style="margin:0 0 6px;font-size:19px;color:{$text};font-weight:700;">Bonjour {$prenom} 👋</h1>
          <p style="margin:0 0 6px;font-size:14px;line-height:1.65;color:{$muted};">
            <strong style="color:{$text};">{$emetteur}</strong> vous confie une tâche dans le cadre de l'instruction :
          </p>
          <div style="font-size:13px;color:{$muted};margin-bottom:18px;">
            <strong style="color:{$text};">{$inst_obj}</strong> &nbsp;·&nbsp; {$inst_code}
          </div>

          <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                 style="background:#f0f6ff;border:1px solid {$border};border-radius:12px;margin:0 0 16px;">
            <tr><td style="padding:18px 22px;">
              <div style="font-size:11px;font-weight:700;color:{$muted};letter-spacing:.08em;margin-bottom:4px;">VOTRE TÂCHE</div>
              <div style="font-size:16px;font-weight:800;color:{$text};line-height:1.35;margin-bottom:6px;">{$t_titre}</div>
            </td></tr>
          </table>
          {$desc_bloc}

          <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:13px;color:{$text};margin-bottom:6px;">
            <tr><td style="padding:3px 0;color:{$muted};width:100px;">&#9201; Échéance</td><td style="padding:3px 0;">{$ech_html}</td></tr>
            <tr><td style="padding:3px 0;color:{$muted};">&#9873; Priorité</td>
                <td style="padding:3px 0;"><span style="display:inline-block;background:{$pr[0]};color:{$pr[1]};
                    padding:2px 12px;border-radius:20px;font-size:12px;font-weight:700;">{$pr[2]}</span></td></tr>
          </table>

          <table role="presentation" width="100%"><tr><td align="center" style="padding:14px 0 8px;">
            <a href="{$url}" target="_blank" style="display:inline-block;background:linear-gradient(135deg,{$blue600},{$teal});
               color:#fff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 38px;border-radius:10px;
               box-shadow:0 6px 18px rgba(26,92,204,.32);">Voir ma tâche &rarr;</a>
          </td></tr></table>
        </td></tr>

        <tr><td style="padding:24px 36px 30px;">
          <hr style="border:none;border-top:1px solid {$border};margin:0 0 16px;">
          <p style="margin:0;font-size:11.5px;line-height:1.6;color:{$muted};text-align:center;">
            Email envoyé automatiquement par <strong>{$app_short}</strong>, merci de ne pas y répondre.<br>
            &copy; {$annee} {$app_name}. Tous droits réservés.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
}
