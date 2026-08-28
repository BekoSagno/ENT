<?php
// modules/subventions/rdb/print.php — RDB (impression / PDF navigateur)
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../models/rdb.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'RAPPORT');

$id = (int)($_GET['id'] ?? 0);
$rap = $id > 0 ? rdb_charger($id) : null;
if (!$rap) { http_response_code(404); exit('Rapport introuvable.'); }

$meta = rdb_tableaux_meta();
$lpt  = $rap['lignes_par_tableau'];
$money = fn($v) => ($v !== null && $v !== '' && is_numeric($v)) ? number_format((float)$v, 0, ',', ' ') : '';
$etats = rdb_etats_activite();

function rdb_print_date($v): string {
    $v = trim((string)$v);
    if ($v === '') return '';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $v;
}

function rdb_print_tableau(int $tn, array $meta, array $lpt, array $etats, callable $money): void {
    if (empty($lpt[$tn])) return;
    $cols = $meta[$tn]['colonnes'] ?? [];
    $with_obs = ($tn !== 9);
    $has_rows = false;
    foreach ($lpt[$tn] as $l) {
        if (trim((string)($l['libelle'] ?? '')) !== '' || !empty($l['donnees'])) {
            $has_rows = true;
            break;
        }
    }
    if (!$has_rows) return;
    ?>
    <div class="tbl-block">
      <div class="tbl-title"><?= e($meta[$tn]['titre']) ?></div>
      <table>
        <thead><tr>
          <th class="col-n">N°</th>
          <th class="col-lib">Libellé</th>
          <?php foreach ($cols as $cl): ?><th><?= e($cl) ?></th><?php endforeach; ?>
          <?php if ($with_obs): ?><th class="col-obs">Observations</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php $n = 1; foreach ($lpt[$tn] as $l):
          $d = $l['donnees'] ?? [];
          if (trim((string)($l['libelle'] ?? '')) === '' && empty(array_filter($d, fn($x) => $x !== '' && $x !== null))) {
              continue;
          }
        ?>
          <tr>
            <td class="c"><?= $n++ ?></td>
            <td class="lib"><?= nl2br(e($l['libelle'] ?? '')) ?></td>
            <?php foreach ($cols as $ck => $cl):
              $v = $d[$ck] ?? '';
              if ($ck === 'etat') $v = $etats[$v] ?? $v;
              elseif ($ck === 'taux') $v = ($v !== '' && $v !== null) ? $v . ' %' : '';
              elseif (in_array($ck, ['date_debut', 'date_fin'], true)) $v = rdb_print_date($v);
              elseif (is_numeric($v)) $v = $money($v);
              $is_num = in_array($ck, ['credit','paye','montant_budgete','montant_depense','t1','t2','t3','t4','taux'], true);
            ?>
            <td class="<?= $is_num ? 'num' : '' ?>"><?= e((string)$v) ?></td>
            <?php endforeach; ?>
            <?php if ($with_obs): ?><td class="obs"><?= nl2br(e($l['commentaire'] ?? '')) ?></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>RDB T<?= (int)$rap['trimestre'] ?>-<?= (int)$rap['annee'] ?> — CRICT</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    @page{
      size:A4 landscape;
      margin:12mm 10mm 14mm 10mm;
    }
    body{
      font-family:Georgia,'Times New Roman',Times,serif;
      color:#111;
      background:#e8eef5;
      font-size:11pt;
      line-height:1.4;
    }
    .sheet{
      max-width:1100px;margin:20px auto;padding:28px 32px;
      background:#fff;box-shadow:0 2px 20px rgba(0,0,0,.08);
    }
    .no-print{
      display:flex;align-items:center;gap:12px;flex-wrap:wrap;
      margin-bottom:18px;padding:12px 14px;
      background:#f0f6ff;border:1px solid #bdd3fd;border-radius:10px;
      font-family:'Segoe UI',Arial,sans-serif;font-size:14px;
    }
    .no-print button{
      padding:10px 22px;background:#1347a8;color:#fff;border:none;
      border-radius:8px;cursor:pointer;font-weight:700;font-size:14px;
    }
    .no-print button:hover{background:#0e3278;}
    .no-print a{color:#1347a8;font-weight:600;text-decoration:none;}
    .no-print .tip{margin-left:auto;color:#475569;font-size:12px;}

    /* En-tête institutionnel */
    .letterhead{text-align:center;margin-bottom:8px;}
    .letterhead .gov{
      font-size:12pt;font-weight:700;text-transform:uppercase;
      letter-spacing:.04em;line-height:1.35;
    }
    .letterhead .dg{
      font-size:10.5pt;margin-top:2px;font-weight:600;line-height:1.35;
    }
    .letterhead .rule{
      width:220px;height:0;border-top:1.5px solid #111;
      margin:8px auto 4px;
    }
    .letterhead .epa{
      font-size:10pt;font-style:italic;margin-top:2px;
    }

    .doc-title{
      text-align:center;font-weight:700;font-size:13pt;
      text-transform:uppercase;text-decoration:underline;
      text-underline-offset:3px;margin:18px 0 4px;
      letter-spacing:.03em;line-height:1.3;
    }
    .doc-sub{
      text-align:center;font-size:11pt;font-weight:600;
      margin-bottom:20px;
    }
    .doc-meta{
      text-align:center;font-size:9.5pt;color:#333;
      margin:-12px 0 18px;font-family:'Segoe UI',Arial,sans-serif;
    }

    h3.sec{
      font-size:11.5pt;font-weight:700;margin:22px 0 8px;
      padding-bottom:3px;border-bottom:1.5px solid #222;
      page-break-after:avoid;
    }
    .subsec{
      font-weight:700;font-size:10.5pt;margin:14px 0 6px;
      page-break-after:avoid;
    }
    .note{font-size:9.5pt;margin:0 0 8px;font-style:italic;color:#333;}

    .info-table{width:100%;border-collapse:collapse;margin-bottom:12px;}
    .info-table td{
      border:1px solid #333;padding:8px 10px;
      vertical-align:top;font-size:10pt;
    }
    .info-table td.k{
      width:26%;font-weight:700;background:#f3f3f3;
      white-space:nowrap;
    }

    .tbl-block{margin:10px 0 14px;page-break-inside:auto;}
    .tbl-title{
      font-family:'Segoe UI',Arial,sans-serif;
      font-size:9.5pt;font-weight:700;color:#fff;
      background:#1a3a6b;padding:6px 10px;
      page-break-after:avoid;
    }
    table{
      width:100%;border-collapse:collapse;margin:0;
      table-layout:fixed;
    }
    th,td{
      border:1px solid #333;padding:5px 6px;
      text-align:left;vertical-align:top;
      font-size:8.5pt;word-wrap:break-word;overflow-wrap:anywhere;
    }
    th{
      background:#d9e2f0;font-weight:700;text-align:center;
      font-family:'Segoe UI',Arial,sans-serif;
      font-size:8pt;line-height:1.25;
    }
    td.lib{font-weight:600;}
    td.num{
      text-align:center;white-space:nowrap;
      font-family:'Segoe UI',Arial,sans-serif;font-variant-numeric:tabular-nums;
    }
    td.c{text-align:center;width:28px;font-family:'Segoe UI',Arial,sans-serif;}
    td.obs{font-size:8pt;color:#222;}
    tbody tr{page-break-inside:avoid;}

    .txt{
      margin:4px 0 12px;padding:8px 10px;
      border:1px solid #999;min-height:48px;
      white-space:pre-wrap;font-size:10pt;
    }

    .sign{
      display:flex;justify-content:space-between;gap:40px;
      margin-top:40px;page-break-inside:avoid;
    }
    .sign .box{flex:1;text-align:center;font-size:10pt;}
    .sign .box strong{display:block;margin-bottom:4px;}
    .sign .line{
      margin-top:70px;border-top:1px solid #111;
      padding-top:6px;font-size:9pt;font-style:italic;
    }

    .footer{
      margin-top:28px;padding-top:8px;border-top:1px solid #bbb;
      font-size:8pt;color:#555;text-align:center;
      font-family:'Segoe UI',Arial,sans-serif;
    }
    .annexes{margin:8px 0 16px 20px;font-size:10pt;}
    .annexes li{margin-bottom:4px;}
    .annexes a{color:#1347a8;font-weight:600;}

    @media print{
      body{background:#fff;}
      .sheet{max-width:none;margin:0;padding:0;box-shadow:none;}
      .no-print{display:none!important;}
      .tbl-title{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
      th{background:#d9e2f0 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
      .info-table td.k{background:#f3f3f3 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
      a{color:#111;text-decoration:none;}
      .annexes a{text-decoration:underline;}
    }
  </style>
</head>
<body>
  <div class="sheet">
  <div class="no-print">
    <button type="button" onclick="window.print()">Imprimer / PDF</button>
    <a href="form.php?id=<?= $id ?>">← Retour au formulaire</a>
    <span class="tip">Conseil : choisissez « Enregistrer au format PDF » dans la fenêtre d’impression.</span>
  </div>

  <header class="letterhead">
    <div class="gov">Ministère de l'Économie, des Finances et du Budget</div>
    <div class="rule"></div>
    <div class="dg">Direction Générale du Patrimoine de l'État et des Investissements Privés</div>
    <div class="epa">Établissement Public Administratif — CRICT</div>
  </header>

  <div class="doc-title">Canevas du Rapport d'Exécution Budgétaire</div>
  <div class="doc-sub">Trimestre <?= (int)$rap['trimestre'] ?> — Année <?= (int)$rap['annee'] ?></div>
  <div class="doc-meta">
    Année N-1 : <?= (int)$rap['annee_n1'] ?>
    · Trimestre de référence des réalisations : T<?= (int)$rap['trim_prec'] ?> <?= (int)$rap['trim_prec_annee'] ?>
  </div>

  <!-- §1 -->
  <h3 class="sec">1. Présentation sommaire de l'EPA</h3>
  <p class="note">Préciser les références de l'acte de création de la structure.</p>
  <table class="info-table">
    <tr><td class="k">Références acte de création</td><td><?= nl2br(e($rap['acte_creation'] ?? '')) ?: '—' ?></td></tr>
    <tr><td class="k">Missions</td><td><?= nl2br(e($rap['missions'] ?? '')) ?: '—' ?></td></tr>
    <tr><td class="k">Organisation</td><td><?= nl2br(e($rap['organisation'] ?? '')) ?: '—' ?></td></tr>
  </table>

  <!-- §2 -->
  <h3 class="sec">2. Plan d'actions annuel budgétisé — Année N-1 (<?= (int)$rap['annee_n1'] ?>)</h3>
  <p class="subsec">a) Activités programmées</p>
  <?php rdb_print_tableau(1, $meta, $lpt, $etats, $money); ?>
  <?php rdb_print_tableau(2, $meta, $lpt, $etats, $money); ?>

  <p class="subsec">b) Activités réalisées — Trimestre précédent : T<?= (int)$rap['trim_prec'] ?> <?= (int)$rap['trim_prec_annee'] ?></p>
  <?php rdb_print_tableau(3, $meta, $lpt, $etats, $money); ?>
  <?php rdb_print_tableau(4, $meta, $lpt, $etats, $money); ?>

  <!-- §3 -->
  <h3 class="sec">3. Situation d'exécution détaillée par nature de dépenses</h3>
  <?php if (!empty(trim((string)($rap['commentaire_personnel'] ?? '')))): ?>
  <p class="subsec">Commentaire — dépenses de personnel</p>
  <div class="txt"><?= e($rap['commentaire_personnel']) ?></div>
  <?php endif; ?>
  <?php for ($tn = 5; $tn <= 8; $tn++) rdb_print_tableau($tn, $meta, $lpt, $etats, $money); ?>
  <?php rdb_print_tableau(9, $meta, $lpt, $etats, $money); ?>

  <!-- §4-6 -->
  <h3 class="sec">4. Suggestions</h3>
  <div class="txt"><?= e($rap['suggestions'] ?? '') ?: '—' ?></div>

  <h3 class="sec">5. Recommandations</h3>
  <div class="txt"><?= e($rap['recommandations'] ?? '') ?: '—' ?></div>

  <h3 class="sec">6. Conclusion</h3>
  <div class="txt"><?= e($rap['conclusion'] ?? '') ?: '—' ?></div>

  <?php if (!empty($rap['documents'])): ?>
  <h3 class="sec">Annexes</h3>
  <ol class="annexes">
    <?php foreach ($rap['documents'] as $doc):
      $href = rtrim(APP_URL, '/') . '/modules/subventions/rdb/download.php?id=' . (int)$doc['id'];
    ?>
    <li>
      <a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($doc['nom_original'] ?? 'Document') ?></a>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <div class="sign">
    <div class="box">
      <strong>Le Directeur / Responsable de l'EPA</strong>
      <div class="line">Nom, signature et cachet</div>
    </div>
    <div class="box">
      <strong>Visa de la tutelle</strong>
      <div class="line">Date et visa</div>
    </div>
  </div>

  <div class="footer">
    <?php if ($rap['statut'] === 'finalise' && !empty($rap['date_finalisation'])): ?>
      Document finalisé le <?= e(format_datetime($rap['date_finalisation'])) ?>
    <?php else: ?>
      Brouillon — dernière mise à jour <?= e(format_datetime($rap['date_maj'] ?? date('Y-m-d H:i:s'))) ?>
    <?php endif; ?>
    · CRICT — Rapport d'exécution budgétaire
  </div>
  </div>
</body>
</html>
