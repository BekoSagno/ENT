<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/suivi.php';

session_demarrer();
exiger_connexion();

$user = user_courant();

/* ────────────────────────────────────────────────
   ACCÈS RÉSERVÉ : Direction (DG) et Super Administrateur
──────────────────────────────────────────────── */
$est_gestionnaire = in_array($user['groupe_code'] ?? '', ['SUPER_ADMIN', 'DG'], true);
if (!$est_gestionnaire) {
    set_flash('error', "Accès réservé à la Direction. Cette page de statistiques n'est pas disponible pour votre profil.");
    redirect('/dashboard.php');
}

/* ────────────────────────────────────────────────
   PÉRIODE
──────────────────────────────────────────────── */
$periodes = [
    '7'   => '7 derniers jours',
    '30'  => '30 derniers jours',
    '90'  => '90 derniers jours',
    '365' => '12 derniers mois',
    'all' => 'Tout l\'historique',
];
$periode = (string)($_GET['periode'] ?? '30');
if (!array_key_exists($periode, $periodes)) $periode = '30';
$date_debut = ($periode === 'all') ? null : date('Y-m-d', strtotime('-' . ((int)$periode - 1) . ' days'));

/**
 * Construit la condition de période pour un alias de table donné (qui possède une colonne date_fiche).
 * @return array [string $sql, array $params]
 */
function periode_cond(string $alias, ?string $date_debut): array {
    if ($date_debut === null) return ['1=1', []];
    return ["$alias.date_fiche >= ?", [$date_debut]];
}

$db = get_db();

/* ────────────────────────────────────────────────
   REQUÊTES D'AGRÉGATION
──────────────────────────────────────────────── */
[$cf, $pf] = periode_cond('f', $date_debut);

// 1) Répartition par statut
$par_statut = ['BROUILLON' => 0, 'SOUMISE' => 0, 'VALIDEE' => 0, 'REJETEE' => 0];
try {
    $st = $db->prepare("SELECT f.statut, COUNT(*) n FROM suivi_fiches_journalieres f WHERE $cf GROUP BY f.statut");
    $st->execute($pf);
    foreach ($st->fetchAll() as $r) { $par_statut[$r['statut']] = (int)$r['n']; }
} catch (Throwable $e) { error_log('[stats suivi statut] ' . $e->getMessage()); }

$total_fiches    = array_sum($par_statut);
$total_finalisees = $par_statut['SOUMISE'] + $par_statut['VALIDEE'] + $par_statut['REJETEE'];
$taux_validation = $total_finalisees > 0 ? round($par_statut['VALIDEE'] / $total_finalisees * 100) : 0;

// 2) Activités (atteinte des objectifs)
$act = ['total' => 0, 'renseignees' => 0, 'atteintes' => 0];
try {
    $st = $db->prepare("
        SELECT COUNT(*) total,
               SUM(a.atteint IS NOT NULL) renseignees,
               SUM(a.atteint = 1) atteintes
        FROM suivi_activites_journalieres a
        JOIN suivi_fiches_journalieres f ON f.id = a.fiche_id
        WHERE $cf
    ");
    $st->execute($pf);
    $row = $st->fetch();
    $act = [
        'total'       => (int)($row['total'] ?? 0),
        'renseignees' => (int)($row['renseignees'] ?? 0),
        'atteintes'   => (int)($row['atteintes'] ?? 0),
    ];
} catch (Throwable $e) { error_log('[stats suivi activites] ' . $e->getMessage()); }
$taux_atteinte = $act['renseignees'] > 0 ? round($act['atteintes'] / $act['renseignees'] * 100) : 0;

// 3) Agents ayant rempli au moins une fiche
$nb_agents_actifs = 0;
try {
    $st = $db->prepare("SELECT COUNT(DISTINCT f.utilisateur_id) FROM suivi_fiches_journalieres f WHERE $cf");
    $st->execute($pf);
    $nb_agents_actifs = (int)$st->fetchColumn();
} catch (Throwable $e) { error_log('[stats suivi agents] ' . $e->getMessage()); }

// 4) Pièces jointes
$nb_pj = 0;
try {
    $st = $db->prepare("
        SELECT COUNT(*) FROM suivi_pieces_jointes pj
        JOIN suivi_fiches_journalieres f ON f.id = pj.fiche_id
        WHERE $cf
    ");
    $st->execute($pf);
    $nb_pj = (int)$st->fetchColumn();
} catch (Throwable $e) { error_log('[stats suivi pj] ' . $e->getMessage()); }

// 5) Évolution dans le temps (jusqu'à 30 derniers jours de la période)
$nb_jours_courbe = ($periode === 'all') ? 30 : min((int)$periode, 30);
$debut_courbe    = date('Y-m-d', strtotime('-' . ($nb_jours_courbe - 1) . ' days'));
$courbe = [];
for ($d = 0; $d < $nb_jours_courbe; $d++) {
    $jour = date('Y-m-d', strtotime("$debut_courbe +$d days"));
    $courbe[$jour] = ['total' => 0, 'validees' => 0];
}
try {
    $st = $db->prepare("
        SELECT f.date_fiche, COUNT(*) total, SUM(f.statut='VALIDEE') validees
        FROM suivi_fiches_journalieres f
        WHERE f.date_fiche >= ?
        GROUP BY f.date_fiche
    ");
    $st->execute([$debut_courbe]);
    foreach ($st->fetchAll() as $r) {
        if (isset($courbe[$r['date_fiche']])) {
            $courbe[$r['date_fiche']] = ['total' => (int)$r['total'], 'validees' => (int)$r['validees']];
        }
    }
} catch (Throwable $e) { error_log('[stats suivi courbe] ' . $e->getMessage()); }
$max_courbe = 1;
foreach ($courbe as $v) { $max_courbe = max($max_courbe, $v['total']); }

// 6) Répartition par département / groupe
$par_groupe = [];
try {
    $st = $db->prepare("
        SELECT g.libelle, g.code, COUNT(f.id) n
        FROM suivi_fiches_journalieres f
        JOIN utilisateurs u ON u.id = f.utilisateur_id
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE $cf
        GROUP BY g.id
        ORDER BY n DESC
    ");
    $st->execute($pf);
    $par_groupe = $st->fetchAll();
} catch (Throwable $e) { error_log('[stats suivi groupe] ' . $e->getMessage()); }
$max_groupe = 1;
foreach ($par_groupe as $g) { $max_groupe = max($max_groupe, (int)$g['n']); }

// 7) Classement des agents
$agents = [];
try {
    $st = $db->prepare("
        SELECT u.id, u.nom, u.prenoms, u.matricule, g.libelle groupe,
               COUNT(*) nb_fiches,
               SUM(f.statut='VALIDEE') nb_validees,
               SUM(f.statut='SOUMISE') nb_soumises,
               MAX(f.date_fiche) derniere
        FROM suivi_fiches_journalieres f
        JOIN utilisateurs u ON u.id = f.utilisateur_id
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE $cf
        GROUP BY u.id
        ORDER BY nb_fiches DESC
    ");
    $st->execute($pf);
    foreach ($st->fetchAll() as $r) { $agents[(int)$r['id']] = $r; }

    // Statistiques d'activités par agent (fusion en PHP)
    $st2 = $db->prepare("
        SELECT f.utilisateur_id uid,
               SUM(a.atteint = 1) atteintes,
               SUM(a.atteint IS NOT NULL) renseignees,
               COUNT(*) total_act
        FROM suivi_activites_journalieres a
        JOIN suivi_fiches_journalieres f ON f.id = a.fiche_id
        WHERE $cf
        GROUP BY f.utilisateur_id
    ");
    $st2->execute($pf);
    foreach ($st2->fetchAll() as $r) {
        $uid = (int)$r['uid'];
        if (isset($agents[$uid])) {
            $agents[$uid]['atteintes']   = (int)$r['atteintes'];
            $agents[$uid]['renseignees'] = (int)$r['renseignees'];
            $agents[$uid]['total_act']   = (int)$r['total_act'];
        }
    }
} catch (Throwable $e) { error_log('[stats suivi agents-detail] ' . $e->getMessage()); }
$agents = array_values($agents);

// 8) Agents (hors Direction) n'ayant soumis aucune fiche sur la période
$absents = [];
try {
    $sub_cond = $date_debut === null ? '1=1' : 'f.date_fiche >= ?';
    $sub_par  = $date_debut === null ? [] : [$date_debut];
    $st = $db->prepare("
        SELECT u.id, u.nom, u.prenoms, u.matricule, g.libelle groupe, u.derniere_connexion
        FROM utilisateurs u
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE u.statut = 'ACTIF'
          AND g.code NOT IN ('SUPER_ADMIN','DG')
          AND NOT EXISTS (
              SELECT 1 FROM suivi_fiches_journalieres f
              WHERE f.utilisateur_id = u.id AND $sub_cond
          )
        ORDER BY g.libelle, u.nom
    ");
    $st->execute($sub_par);
    $absents = $st->fetchAll();
} catch (Throwable $e) { error_log('[stats suivi absents] ' . $e->getMessage()); }

/* ────────────────────────────────────────────────
   EXPORT CSV (avant tout rendu HTML)
──────────────────────────────────────────────── */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="statistiques_suivi_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // BOM UTF-8 (Excel)
    fputcsv($out, ['Agent', 'Matricule', 'Groupe', 'Fiches', 'Validées', 'Soumises', 'Activités', 'Objectifs atteints', 'Taux atteinte (%)', 'Dernière fiche'], ';');
    foreach ($agents as $a) {
        $rens = (int)($a['renseignees'] ?? 0);
        $att  = (int)($a['atteintes'] ?? 0);
        $tx   = $rens > 0 ? round($att / $rens * 100) : 0;
        fputcsv($out, [
            $a['prenoms'] . ' ' . $a['nom'],
            $a['matricule'],
            $a['groupe'],
            (int)$a['nb_fiches'],
            (int)$a['nb_validees'],
            (int)$a['nb_soumises'],
            (int)($a['total_act'] ?? 0),
            $att,
            $tx,
            $a['derniere'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

/* ────────────────────────────────────────────────
   DÉGRADÉ CONIQUE POUR LE DONUT (répartition statut)
──────────────────────────────────────────────── */
$couleurs_statut = [
    'VALIDEE'   => '#22c55e',
    'SOUMISE'   => '#f59e0b',
    'REJETEE'   => '#ef4444',
    'BROUILLON' => '#94a3b8',
];
$donut_segments = [];
$cumul = 0;
foreach ($couleurs_statut as $statut => $couleur) {
    $val = $par_statut[$statut];
    if ($val <= 0) continue;
    $pct = $total_fiches > 0 ? ($val / $total_fiches * 100) : 0;
    $donut_segments[] = sprintf('%s %.2f%% %.2f%%', $couleur, $cumul, $cumul + $pct);
    $cumul += $pct;
}
$donut_gradient = empty($donut_segments)
    ? 'var(--border) 0% 100%'
    : implode(', ', $donut_segments);

$page_titre      = 'Statistiques de suivi';
$page_breadcrumb = 'Suivi & Évaluation · Statistiques';
$module_actif    = 'STATS_SUIVI';

include __DIR__ . '/../../views/layout/header.php';
?>

<style>
  .stats-period { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px; }
  .stats-period a {
    padding:7px 14px; border-radius:8px; font-size:.8rem; font-weight:600;
    text-decoration:none; border:1px solid var(--border); color:var(--text-muted);
    background:#fff; transition:all .15s;
  }
  .stats-period a:hover { border-color:var(--blue-200); color:var(--blue-600); }
  .stats-period a.active { background:var(--blue-600); border-color:var(--blue-600); color:#fff; }

  .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:22px; }
  @media(max-width:1100px){ .kpi-grid{ grid-template-columns:repeat(2,1fr);} }
  @media(max-width:560px){ .kpi-grid{ grid-template-columns:1fr;} }
  .kpi {
    background:#fff; border:1px solid var(--border); border-radius:var(--radius);
    padding:18px 20px; display:flex; align-items:center; gap:14px;
  }
  .kpi-ic { width:46px; height:46px; border-radius:12px; display:flex; align-items:center;
            justify-content:center; font-size:1.2rem; flex-shrink:0; }
  .kpi-ic.blue{ background:#e0edff; color:#1a5ccc; }
  .kpi-ic.green{ background:#dcfce7; color:#16a34a; }
  .kpi-ic.amber{ background:#fef3c7; color:#d97706; }
  .kpi-ic.red{ background:#fee2e2; color:#dc2626; }
  .kpi-ic.violet{ background:#ede9fe; color:#7c3aed; }
  .kpi-ic.slate{ background:#f1f5f9; color:#475569; }
  .kpi-val { font-size:1.55rem; font-weight:800; line-height:1; color:var(--text); }
  .kpi-lbl { font-size:.76rem; color:var(--text-muted); margin-top:5px; }

  .grid-2 { display:grid; grid-template-columns:1.4fr 1fr; gap:20px; margin-bottom:22px; }
  @media(max-width:900px){ .grid-2{ grid-template-columns:1fr; } }

  .barchart { display:flex; align-items:flex-end; gap:4px; height:180px; padding:10px 4px 0; }
  .barchart .bar-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; height:100%; justify-content:flex-end; }
  .barchart .bar { width:100%; max-width:22px; border-radius:5px 5px 0 0; background:var(--blue-500);
                   position:relative; min-height:2px; transition:height .4s; }
  .barchart .bar .bv { position:absolute; top:-17px; left:50%; transform:translateX(-50%);
                       font-size:.66rem; font-weight:700; color:var(--text-muted); }
  .barchart .bl { font-size:.6rem; color:var(--text-muted); white-space:nowrap; }

  .donut-wrap { display:flex; align-items:center; gap:22px; flex-wrap:wrap; justify-content:center; }
  .donut { width:150px; height:150px; border-radius:50%;
           background:conic-gradient(<?= $donut_gradient ?>);
           display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .donut::after { content:''; width:96px; height:96px; background:#fff; border-radius:50%; }
  .donut-center { position:absolute; text-align:center; }
  .legend { display:flex; flex-direction:column; gap:9px; }
  .legend-row { display:flex; align-items:center; gap:9px; font-size:.8rem; }
  .legend-dot { width:11px; height:11px; border-radius:3px; flex-shrink:0; }

  .gbar-row { margin-bottom:13px; }
  .gbar-head { display:flex; justify-content:space-between; font-size:.8rem; margin-bottom:5px; }
  .gbar-track { background:var(--bg); border-radius:20px; height:8px; overflow:hidden; }
  .gbar-fill { height:100%; border-radius:20px; background:var(--blue-500); }

  .mini-tag { display:inline-block; padding:2px 8px; border-radius:6px; font-size:.72rem; font-weight:700; }
</style>

<!-- Sélecteur de période -->
<div class="stats-period">
  <?php foreach ($periodes as $p => $lbl): ?>
    <a href="?periode=<?= e($p) ?>" class="<?= $periode === $p ? 'active' : '' ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
  <a href="?periode=<?= e($periode) ?>&export=csv" style="margin-left:auto;border-color:var(--blue-200);color:var(--blue-600);">
    <i class="fa-solid fa-file-csv"></i> Exporter (CSV)
  </a>
</div>

<!-- KPIs -->
<div class="kpi-grid">
  <div class="kpi">
    <div class="kpi-ic blue"><i class="fa-solid fa-file-lines"></i></div>
    <div><div class="kpi-val"><?= $total_fiches ?></div><div class="kpi-lbl">Fiches au total</div></div>
  </div>
  <div class="kpi">
    <div class="kpi-ic amber"><i class="fa-solid fa-hourglass-half"></i></div>
    <div><div class="kpi-val"><?= $par_statut['SOUMISE'] ?></div><div class="kpi-lbl">En attente de validation</div></div>
  </div>
  <div class="kpi">
    <div class="kpi-ic green"><i class="fa-solid fa-circle-check"></i></div>
    <div><div class="kpi-val"><?= $taux_validation ?>%</div><div class="kpi-lbl">Taux de validation</div></div>
  </div>
  <div class="kpi">
    <div class="kpi-ic violet"><i class="fa-solid fa-bullseye"></i></div>
    <div><div class="kpi-val"><?= $taux_atteinte ?>%</div><div class="kpi-lbl">Objectifs atteints</div></div>
  </div>
  <div class="kpi">
    <div class="kpi-ic slate"><i class="fa-solid fa-users"></i></div>
    <div><div class="kpi-val"><?= $nb_agents_actifs ?></div><div class="kpi-lbl">Agents ayant rapporté</div></div>
  </div>
  <div class="kpi">
    <div class="kpi-ic blue"><i class="fa-solid fa-list-check"></i></div>
    <div><div class="kpi-val"><?= $act['total'] ?></div><div class="kpi-lbl">Activités rapportées</div></div>
  </div>
  <div class="kpi">
    <div class="kpi-ic red"><i class="fa-solid fa-circle-xmark"></i></div>
    <div><div class="kpi-val"><?= $par_statut['REJETEE'] ?></div><div class="kpi-lbl">Fiches rejetées</div></div>
  </div>
  <div class="kpi">
    <div class="kpi-ic slate"><i class="fa-solid fa-paperclip"></i></div>
    <div><div class="kpi-val"><?= $nb_pj ?></div><div class="kpi-lbl">Pièces jointes</div></div>
  </div>
</div>

<div class="grid-2">
  <!-- Évolution -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-chart-column" style="color:var(--blue-500);"></i>
      <h2>Fiches déposées (<?= $nb_jours_courbe ?> derniers jours)</h2>
    </div>
    <div class="card-body" style="padding:18px 22px;">
      <?php if ($total_fiches === 0): ?>
        <div style="text-align:center;color:var(--text-muted);padding:40px 0;">
          <i class="fa-regular fa-calendar-xmark" style="font-size:2rem;opacity:.4;display:block;margin-bottom:8px;"></i>
          Aucune donnée sur cette période
        </div>
      <?php else: ?>
        <div class="barchart">
          <?php foreach ($courbe as $jour => $v):
            $h = round($v['total'] / $max_courbe * 100);
          ?>
            <div class="bar-col" title="<?= e(format_date($jour, 'd/m/Y')) ?> — <?= $v['total'] ?> fiche(s)">
              <div style="flex:1;display:flex;align-items:flex-end;width:100%;justify-content:center;">
                <div class="bar" style="height:<?= max(2, $h) ?>%;">
                  <?php if ($v['total'] > 0): ?><span class="bv"><?= $v['total'] ?></span><?php endif; ?>
                </div>
              </div>
              <div class="bl"><?= e(date('d/m', strtotime($jour))) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Répartition par statut -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-chart-pie" style="color:var(--blue-500);"></i>
      <h2>Répartition par statut</h2>
    </div>
    <div class="card-body" style="padding:22px;">
      <?php if ($total_fiches === 0): ?>
        <div style="text-align:center;color:var(--text-muted);padding:30px 0;">Aucune donnée</div>
      <?php else: ?>
      <div class="donut-wrap">
        <div style="position:relative;display:flex;align-items:center;justify-content:center;">
          <div class="donut"></div>
          <div class="donut-center">
            <div style="font-size:1.5rem;font-weight:800;color:var(--text);"><?= $total_fiches ?></div>
            <div style="font-size:.68rem;color:var(--text-muted);">fiches</div>
          </div>
        </div>
        <div class="legend">
          <?php
            $libelles_statut = ['VALIDEE'=>'Validées','SOUMISE'=>'Soumises','REJETEE'=>'Rejetées','BROUILLON'=>'Brouillons'];
            foreach ($couleurs_statut as $s => $c):
              $val = $par_statut[$s];
              $pct = $total_fiches > 0 ? round($val / $total_fiches * 100) : 0;
          ?>
            <div class="legend-row">
              <span class="legend-dot" style="background:<?= $c ?>;"></span>
              <span style="flex:1;color:var(--text);"><?= $libelles_statut[$s] ?></span>
              <span style="font-weight:700;color:var(--text);"><?= $val ?></span>
              <span style="color:var(--text-muted);font-size:.74rem;">(<?= $pct ?>%)</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Classement agents -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-ranking-star" style="color:var(--blue-500);"></i>
      <h2>Activité des agents</h2>
      <span style="margin-left:auto;font-size:.75rem;color:var(--text-muted);"><?= count($agents) ?> agent<?= count($agents) > 1 ? 's' : '' ?></span>
    </div>
    <div class="table-wrap">
      <table class="crict-table">
        <thead><tr>
          <th>Agent</th><th>Groupe</th><th style="text-align:center;">Fiches</th>
          <th style="text-align:center;">Validées</th><th style="text-align:center;">Objectifs atteints</th>
          <th>Dernière</th>
        </tr></thead>
        <tbody>
          <?php if (empty($agents)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">Aucune fiche sur cette période</td></tr>
          <?php else: foreach (array_slice($agents, 0, 12) as $a):
            $rens = (int)($a['renseignees'] ?? 0);
            $att  = (int)($a['atteintes'] ?? 0);
            $tx   = $rens > 0 ? round($att / $rens * 100) : 0;
            $cl   = $tx >= 80 ? '#16a34a' : ($tx >= 50 ? '#d97706' : '#dc2626');
          ?>
            <tr>
              <td>
                <strong><?= e($a['prenoms'] . ' ' . $a['nom']) ?></strong>
                <div style="font-size:.72rem;color:var(--text-muted);"><?= e($a['matricule']) ?></div>
              </td>
              <td style="font-size:.78rem;"><?= e($a['groupe']) ?></td>
              <td style="text-align:center;font-weight:700;"><?= (int)$a['nb_fiches'] ?></td>
              <td style="text-align:center;"><?= (int)$a['nb_validees'] ?></td>
              <td style="text-align:center;">
                <?php if ($rens > 0): ?>
                  <span class="mini-tag" style="background:<?= $cl ?>1a;color:<?= $cl ?>;"><?= $tx ?>%</span>
                <?php else: ?>
                  <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:.76rem;color:var(--text-muted);"><?= $a['derniere'] ? e(format_date($a['derniere'], 'd/m/Y')) : '—' ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Répartition par département -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-sitemap" style="color:var(--blue-500);"></i>
      <h2>Fiches par groupe</h2>
    </div>
    <div class="card-body" style="padding:18px 22px;">
      <?php if (empty($par_groupe)): ?>
        <div style="text-align:center;color:var(--text-muted);padding:30px 0;">Aucune donnée</div>
      <?php else: foreach ($par_groupe as $g):
        $pct = round((int)$g['n'] / $max_groupe * 100);
      ?>
        <div class="gbar-row">
          <div class="gbar-head">
            <span style="font-weight:600;color:var(--text);"><?= e($g['libelle']) ?></span>
            <span style="color:var(--text-muted);"><?= (int)$g['n'] ?></span>
          </div>
          <div class="gbar-track"><div class="gbar-fill" style="width:<?= max(3, $pct) ?>%;"></div></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- Agents sans fiche -->
<div class="card">
  <div class="card-header">
    <i class="fa-solid fa-user-clock" style="color:var(--warn);"></i>
    <h2>Agents sans aucune fiche sur la période</h2>
    <span style="margin-left:auto;background:<?= empty($absents) ? '#22c55e' : 'var(--warn)' ?>;color:#fff;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;"><?= count($absents) ?></span>
  </div>
  <?php if (empty($absents)): ?>
    <div class="card-body" style="padding:22px;text-align:center;color:var(--text-muted);">
      <i class="fa-solid fa-circle-check" style="color:#22c55e;margin-right:6px;"></i>
      Tous les agents concernés ont déposé au moins une fiche.
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="crict-table">
        <thead><tr><th>Agent</th><th>Matricule</th><th>Groupe</th><th>Dernière connexion</th></tr></thead>
        <tbody>
          <?php foreach ($absents as $a): ?>
          <tr>
            <td><strong><?= e($a['prenoms'] . ' ' . $a['nom']) ?></strong></td>
            <td><code style="font-size:.76rem;background:var(--bg);padding:2px 6px;border-radius:4px;"><?= e($a['matricule']) ?></code></td>
            <td style="font-size:.8rem;"><?= e($a['groupe']) ?></td>
            <td style="font-size:.78rem;color:var(--text-muted);"><?= $a['derniere_connexion'] ? e(format_date($a['derniere_connexion'], 'd/m/Y')) : 'Jamais' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
