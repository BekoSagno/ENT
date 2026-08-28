<?php
// modules/subventions/depenses_print.php — Journal des dépenses (impression / PDF navigateur)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/subvention.php';
require_once __DIR__ . '/../../models/depense.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'VOIR');

$f = [
    'type'   => clean($_GET['type'] ?? ''),
    'q'      => clean($_GET['q']    ?? ''),
    'caisse' => (int)($_GET['caisse'] ?? 0),
];

// Journal complet (sans pagination) correspondant aux filtres courants.
$result   = depense_lister($f, 100000, 0);
$depenses = $result['data'];

$type_lbl = [
    'biens_services' => 'Biens & services',
    'personnel'      => 'Personnel',
];

$regimes_lbl = ['tva'=>'TVA 18 %','precompte'=>'Précompte 20 %','sans_tva'=>'Sans taxe'];

$total_montant = 0.0;
foreach ($depenses as $d) { $total_montant += (float)$d['montant']; }

$filtres_actifs = [];
if ($f['type'] !== '')  { $filtres_actifs[] = 'Type : ' . ($type_lbl[$f['type']] ?? $f['type']); }
if ($f['q'] !== '')     { $filtres_actifs[] = 'Recherche : « ' . $f['q'] . ' »'; }
if ($f['caisse'] > 0 && !empty($depenses[0]['code_caisse'])) {
    $filtres_actifs[] = 'Caisse : ' . $depenses[0]['code_caisse'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Journal des dépenses — CRICT</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Segoe UI',Arial,sans-serif;color:#111;padding:34px;max-width:1000px;margin:0 auto;font-size:13px;line-height:1.45;}
    .head{text-align:center;border-bottom:3px solid #1a5ccc;padding-bottom:18px;margin-bottom:22px;}
    .head h1{font-size:1.25rem;color:#1a5ccc;margin-bottom:4px;}
    .head .sub{font-size:.82rem;color:#555;}
    .head .doc{margin-top:14px;font-size:1.05rem;font-weight:800;letter-spacing:.05em;}
    .meta{display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:.78rem;color:#555;margin-bottom:14px;}
    .meta .filtres{font-style:italic;}
    table{width:100%;border-collapse:collapse;margin-bottom:16px;}
    th,td{border:1px solid #ccc;padding:7px 9px;text-align:left;vertical-align:top;font-size:.78rem;}
    th{background:#f0f6ff;font-weight:700;}
    td.num{font-weight:700;}
    td.montant{text-align:right;font-weight:700;white-space:nowrap;}
    td.caisse{white-space:nowrap;}
    td.num{white-space:nowrap;}
    tr.total td{background:#f0f6ff;font-weight:800;font-size:.85rem;border-top:2px solid #1a5ccc;}
    .empty{text-align:center;padding:40px;color:#777;font-style:italic;}
    .footer{margin-top:26px;font-size:.72rem;color:#666;text-align:center;}
    @media print{
      body{padding:14px;}
      .no-print{display:none !important;}
    }
  </style>
</head>
<body>
  <div class="no-print" style="margin-bottom:20px;">
    <button onclick="window.print()" style="padding:10px 20px;background:#1a5ccc;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:700;">
      Imprimer / Enregistrer en PDF
    </button>
    <a href="depenses.php?<?= e(http_build_query(array_filter($f))) ?>" style="margin-left:12px;color:#1a5ccc;">← Retour à l'historique</a>
  </div>

  <div class="head">
    <h1>CENTRE DE RECHERCHE EN INFORMATIQUE ET CYBER-TECHNOLOGIE</h1>
    <div class="sub">CRICT — République de Guinée</div>
    <div class="doc">JOURNAL DES DÉPENSES EXÉCUTÉES</div>
  </div>

  <div class="meta">
    <div>Édité le <?= e(format_datetime(date('Y-m-d H:i:s'))) ?></div>
    <div><?= count($depenses) ?> dépense(s)</div>
  </div>
  <?php if ($filtres_actifs): ?>
  <div class="meta"><div class="filtres">Filtres appliqués — <?= e(implode(' · ', $filtres_actifs)) ?></div></div>
  <?php endif; ?>

  <?php if (empty($depenses)): ?>
  <div class="empty">Aucune dépense ne correspond aux critères.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>N°</th>
        <th>Type</th>
        <th>Bénéficiaire</th>
        <th>Libellé</th>

        <th>Caisse</th>
        <th style="text-align:right;">Montant HT</th>
        <th>Calcul</th>
        <th style="text-align:right;">Total payé</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($depenses as $d): $ht = ($d['montant_ht']!==null && $d['montant_ht']!=='') ? (float)$d['montant_ht'] : null; ?>
      <tr>
        <td class="num"><?= e($d['numero']) ?></td>
        <td><?= e($type_lbl[$d['type_depense']] ?? $d['type_depense']) ?></td>
        <td><?= e($d['type_depense']==='personnel' ? ($d['employe_nom'] ?? '—') : ($d['fournisseur_nom'] ?? '—')) ?></td>
        <td class="libelle"><?= e(trim((string)($d['motif'] ?? '')) ?: '—') ?></td>
        <td class="caisse"><?= e($d['code_caisse'] ?? '—') ?></td>
        <td class="montant"><?= $ht!==null ? e(sub_fmt($ht)) : '—' ?></td>
        <td><?= e($regimes_lbl[$d['regime_fiscal']] ?? '—') ?></td>
        <td class="montant"><?= e(sub_fmt((float)$d['montant'])) ?></td>
        <td><?= e(format_datetime($d['date_execution'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="total">
        <td colspan="7">TOTAL PAYÉ</td>
        <td class="montant"><?= e(sub_fmt($total_montant)) ?></td>
        <td></td>
      </tr>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="footer">
    Document généré par le système CRICT-ENT — <?= e(format_datetime(date('Y-m-d H:i:s'))) ?>
  </div>
</body>
</html>
