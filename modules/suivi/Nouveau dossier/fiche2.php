<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/suivi.php';

session_demarrer();
exiger_permission('SUIVI_QUOTIDIEN', 'VOIR');

$user     = user_courant();
$date_str = clean($_GET['date'] ?? date('Y-m-d'));

try { $date_obj = new DateTime($date_str); }
catch (Exception $e) { $date_str = date('Y-m-d'); $date_obj = new DateTime(); }
$date_str = $date_obj->format('Y-m-d');

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action   = clean($_POST['action'] ?? '');
    $fiche_id = (int)($_POST['fiche_id'] ?? 0);

    if ($action === 'sauvegarder' && a_permission('SUIVI_QUOTIDIEN','CREER')) {
        $fiche_id  = suivi_creer_ou_recuperer_fiche((int)$user['id'], $date_str);
        $activites = $_POST['activites'] ?? [];
        $objectifs = clean($_POST['objectifs_lendemain'] ?? '');
        suivi_sauvegarder_activites($fiche_id, $activites, $objectifs);
        set_flash('success', 'Fiche sauvegardée avec succès.');
        header('Location: fiche.php?date=' . $date_str);
        exit;
    }

    if ($action === 'soumettre' && $fiche_id) {
        $res = suivi_soumettre_fiche($fiche_id);
        set_flash($res['succes'] ? 'success' : 'error', $res['message'] ?? 'Fiche soumise pour validation.');
        header('Location: fiche.php?date=' . $date_str);
        exit;
    }
}

// Charger la fiche
$fiche_id = null;
$fiche    = suivi_fiche_du_jour((int)$user['id'], $date_str);
if ($fiche) { $fiche_id = $fiche['id']; $activites = $fiche['activites']; }
else { $activites = []; }

// Charger les pièces jointes existantes (indexées par activite_id)
$pj_par_activite = [];
$pj_sans_activite = [];   // PJ rattachées à la fiche mais pas encore à une activité (activite_id = NULL)
if ($fiche_id) {
    try {
        $db   = get_db();
        $stmt = $db->prepare('SELECT * FROM suivi_pieces_jointes WHERE fiche_id = ? ORDER BY activite_id, cree_le');
        $stmt->execute([$fiche_id]);
        $icones_ext = [
            'pdf'  => 'fa-file-pdf',
            'doc'  => 'fa-file-word', 'docx' => 'fa-file-word',
            'xls'  => 'fa-file-excel','xlsx' => 'fa-file-excel',
            'odt'  => 'fa-file-lines','ods'  => 'fa-file-lines',
        ];
        foreach ($stmt->fetchAll() as $pj) {
            $t = (int)$pj['taille'];
            $pj['taille_aff'] = $t < 1048576 ? round($t/1024,1).' Ko' : round($t/1048576,2).' Mo';
            $pj['icone']      = $icones_ext[$pj['extension']] ?? 'fa-file';
            if ($pj['activite_id']) {
                $pj_par_activite[(int)$pj['activite_id']][] = $pj;
            } else {
                $pj_sans_activite[] = $pj;
            }
        }
    } catch (Throwable $e) {
        error_log('[CRICT fiche pj] '.$e->getMessage());
    }
}

// Navigation
$date_prev    = (clone $date_obj)->modify('-1 day')->format('Y-m-d');
$date_next    = (clone $date_obj)->modify('+1 day')->format('Y-m-d');
$est_futur    = $date_str > date('Y-m-d');
$est_readonly = ($fiche && $fiche['statut'] === 'VALIDEE') || $est_futur || !a_permission('SUIVI_QUOTIDIEN','CREER');

$jours_fr   = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
$mois_fr    = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
$date_label = $jours_fr[$date_obj->format('l')] . ' ' . $date_obj->format('d') . ' ' . $mois_fr[(int)$date_obj->format('n')] . ' ' . $date_obj->format('Y');

$statut_meta = [
    'BROUILLON' => ['bg'=>'#fef3c7','color'=>'#92400e','icon'=>'fa-pen-to-square'],
    'SOUMISE'   => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'fa-clock'],
    'VALIDEE'   => ['bg'=>'#dcfce7','color'=>'#166534','icon'=>'fa-circle-check'],
    'REJETEE'   => ['bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'fa-circle-xmark'],
];

$page_titre      = 'Fiche Quotidienne';
$page_breadcrumb = 'Suivi & Évaluation · ' . $date_label;
$module_actif    = 'SUIVI_QUOTIDIEN';
function strftime_fr(string $d): string { return $d; }
include __DIR__ . '/../../views/layout/header.php';
?>

<style>
/* ── Navigation dates ── */
.date-nav {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 20px; flex-wrap: wrap;
}
.date-nav-center { flex:1; min-width:0; text-align:center; }
.date-nav-center h3 {
  font-size:.98rem; font-weight:800; color:var(--text);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.statut-pill {
  display:inline-flex; align-items:center; gap:5px;
  padding:3px 12px; border-radius:20px;
  font-size:.72rem; font-weight:700; margin-top:5px;
}

/* ── Barre de progression ── */
.progress-bar-wrap {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius); padding:14px 20px;
  margin-bottom:18px;
  display:flex; align-items:center; gap:14px; flex-wrap:wrap;
}
.progress-track {
  flex:1; min-width:100px;
  height:8px; background:#e5e7eb; border-radius:20px; overflow:hidden;
}
.progress-fill {
  height:100%; border-radius:20px;
  background:linear-gradient(90deg,var(--blue-500),var(--accent));
  transition:width .4s ease;
}

/* ── Cartes activités ── */
.activites-list { display:flex; flex-direction:column; gap:14px; }

.activite-card {
  background:var(--surface);
  border:1.5px solid var(--border);
  border-radius:12px;
  overflow:hidden;
  transition:box-shadow .2s, opacity .2s, transform .2s;
}
.activite-card:focus-within { border-color:var(--blue-300); box-shadow:0 4px 18px rgba(13,50,120,.10); }

.act-card-header {
  display:flex; align-items:center; gap:10px;
  padding:11px 16px;
  background:var(--bg); border-bottom:1px solid var(--border);
}
.act-num {
  width:26px; height:26px; border-radius:50%; flex-shrink:0;
  background:var(--blue-600); color:#fff;
  display:flex; align-items:center; justify-content:center;
  font-size:.72rem; font-weight:800;
}
.act-num.ro { background:var(--blue-200); color:var(--blue-700); }
.act-card-title { flex:1; font-size:.82rem; font-weight:600; color:var(--text-muted); }

.badge-atteint {
  display:inline-flex; align-items:center; gap:5px;
  padding:3px 10px; border-radius:20px;
  font-size:.72rem; font-weight:700;
}
.badge-atteint.oui { background:#dcfce7; color:#166534; }
.badge-atteint.non { background:#fee2e2; color:#991b1b; }
.badge-atteint.nd  { background:var(--bg); color:var(--text-muted); border:1px solid var(--border); }

.btn-del-card {
  width:30px; height:30px; border-radius:7px; flex-shrink:0;
  background:#fee2e2; color:var(--danger);
  border:1px solid #fecaca;
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; font-size:.8rem; transition:all .15s;
}
.btn-del-card:hover { background:var(--danger); color:#fff; }

/* Grid des champs — responsive en 3 niveaux */
.act-card-body {
  padding:16px;
  display:grid;
  grid-template-columns:1fr;
  gap:14px;
}
@media (min-width:600px) {
  .act-card-body {
    grid-template-columns:1fr 1fr;
  }
  .f-objectif  { grid-column:1/-1; }
  .f-documents { grid-column:1/-1; }
}
@media (min-width:900px) {
  .act-card-body {
    grid-template-columns:2fr 110px 140px;
  }
  .f-objectif    { grid-column:1; grid-row:1; }
  .f-duree       { grid-column:2; grid-row:1; }
  .f-atteint     { grid-column:3; grid-row:1; }
  .f-difficultes { grid-column:1; }
  .f-solutions   { grid-column:2/4; }
  .f-documents   { grid-column:1/-1; }
}

.f-label {
  font-size:.71rem; font-weight:700; color:var(--text-muted);
  text-transform:uppercase; letter-spacing:.07em;
  margin-bottom:6px; display:flex; align-items:center; gap:5px;
}
.f-val {
  font-size:.87rem; color:var(--text); line-height:1.55;
  min-height:22px;
}
.f-val.empty { color:var(--text-muted); font-style:italic; }

/* Radio pills Oui/Non */
.radio-pills { display:flex; gap:8px; }
.radio-pill-label { flex:1; cursor:pointer; }
.radio-pill-label input { display:none; }
.pill-btn {
  display:flex; align-items:center; justify-content:center; gap:5px;
  padding:8px 6px; border-radius:8px;
  border:1.5px solid var(--border);
  font-size:.8rem; font-weight:600; color:var(--text-muted);
  cursor:pointer; transition:all .15s; user-select:none;
}
.pill-btn:hover { border-color:var(--blue-300); color:var(--blue-600); }
.pill-btn.active-oui { background:#dcfce7; border-color:#22c55e; color:#166534; }
.pill-btn.active-non { background:#fee2e2; border-color:#ef4444; color:#991b1b; }

/* Bouton ajouter */
.btn-add {
  display:flex; align-items:center; justify-content:center; gap:8px;
  width:100%; padding:13px;
  border:2px dashed var(--blue-300); border-radius:12px;
  background:var(--blue-50); color:var(--blue-600);
  font-size:.87rem; font-weight:600;
  cursor:pointer; transition:all .18s; margin-top:4px;
  font-family:var(--font);
}
.btn-add:hover { background:var(--blue-100); border-color:var(--blue-500); }

/* Section lendemain */
.lendemain-section {
  background:linear-gradient(135deg,#f0f9ff,#e0f2fe);
  border:1.5px solid #bae6fd; border-radius:12px;
  overflow:hidden; margin-bottom:20px;
}
.lendemain-header {
  display:flex; align-items:center; gap:10px;
  padding:13px 18px; border-bottom:1px solid #bae6fd;
}
.lendemain-header h3 { font-size:.9rem; font-weight:700; color:#0c4a6e; }
.lendemain-body { padding:16px 18px; }
.lendemain-body .form-control {
  background:rgba(255,255,255,.75); border-color:#bae6fd;
}
.lendemain-body .form-control:focus {
  background:#fff; border-color:#0ea5e9;
  box-shadow:0 0 0 3px rgba(14,165,233,.12);
}

/* ── Pièces jointes ── */
.pj-zone {
  grid-column: 1 / -1;
  border: 2px dashed var(--border);
  border-radius: 10px;
  overflow: hidden;
  transition: border-color .2s;
}
.pj-zone.dragover { border-color: var(--blue-400); background: var(--blue-50); }

.pj-header {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 14px;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  font-size: .75rem; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .06em;
}
.pj-header .pj-count {
  margin-left: auto;
  background: var(--blue-100); color: var(--blue-700);
  padding: 1px 7px; border-radius: 20px;
  font-size: .7rem; font-weight: 800;
}

.pj-list {
  display: flex; flex-direction: column; gap: 0;
}
.pj-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 14px;
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.pj-item:last-child { border-bottom: none; }
.pj-item:hover { background: var(--bg); }

.pj-icon {
  width: 32px; height: 32px; border-radius: 7px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: .9rem;
}
.pj-icon.pdf   { background: #fee2e2; color: #dc2626; }
.pj-icon.word  { background: #dbeafe; color: #1d4ed8; }
.pj-icon.excel { background: #dcfce7; color: #15803d; }
.pj-icon.other { background: var(--bg); color: var(--text-muted); }

.pj-nom  { flex: 1; min-width: 0; font-size: .82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pj-meta { font-size: .7rem; color: var(--text-muted); white-space: nowrap; }

.pj-btn-dl {
  width: 28px; height: 28px; border-radius: 6px; flex-shrink: 0;
  background: var(--blue-50); color: var(--blue-600);
  border: 1px solid var(--blue-200);
  display: flex; align-items: center; justify-content: center;
  text-decoration: none; font-size: .75rem; transition: all .12s;
}
.pj-btn-dl:hover { background: var(--blue-600); color: #fff; border-color: var(--blue-600); }

.pj-btn-del {
  width: 28px; height: 28px; border-radius: 6px; flex-shrink: 0;
  background: #fff5f5; color: var(--danger);
  border: 1px solid #fecaca;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: .75rem; transition: all .12s;
}
.pj-btn-del:hover { background: var(--danger); color: #fff; }

.pj-upload-area {
  padding: 12px 14px;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.pj-upload-label {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 7px 14px; border-radius: 8px;
  background: var(--blue-50); color: var(--blue-600);
  border: 1px solid var(--blue-200);
  font-size: .8rem; font-weight: 600;
  cursor: pointer; transition: all .15s; user-select: none;
}
.pj-upload-label:hover { background: var(--blue-600); color: #fff; border-color: var(--blue-600); }
.pj-upload-label input { display: none; }

.pj-hint { font-size: .7rem; color: var(--text-muted); }

/* Progress bar upload */
.pj-progress-wrap {
  width: 100%; height: 4px; background: #e5e7eb;
  border-radius: 20px; overflow: hidden; display: none;
}
.pj-progress-fill {
  height: 100%; background: var(--blue-500);
  border-radius: 20px; width: 0;
  transition: width .2s ease;
}

/* Barre d'actions sticky */
.actions-bar {
  position:sticky; bottom:0; z-index:50;
  background:rgba(255,255,255,.96); backdrop-filter:blur(10px);
  border-top:1px solid var(--border);
  padding:13px 0; margin-top:20px;
  display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;
}
@media(max-width:500px){
  .actions-bar { flex-direction:column; }
  .actions-bar .btn { width:100%; justify-content:center; }
}

/* Autosave */
.autosave-toast {
  position:fixed; bottom:76px; right:20px; z-index:300;
  background:var(--blue-900); color:#fff;
  padding:8px 16px; border-radius:20px;
  font-size:.75rem; font-weight:600;
  display:flex; align-items:center; gap:7px;
  opacity:0; pointer-events:none;
  transition:opacity .3s;
}
.autosave-toast.show { opacity:1; }
</style>

<!-- Navigation dates -->
<div class="date-nav">
  <a href="fiche.php?date=<?= $date_prev ?>" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-chevron-left"></i>
  </a>

  <div class="date-nav-center">
    <h3><?= e($date_label) ?></h3>
    <?php if ($fiche):
      $sm = $statut_meta[$fiche['statut']] ?? ['bg'=>'#f3f4f6','color'=>'#6b7280','icon'=>'fa-circle'];
    ?>
      <div class="statut-pill" style="background:<?= $sm['bg'] ?>;color:<?= $sm['color'] ?>;">
        <i class="fa-solid <?= $sm['icon'] ?>"></i>
        <?= ucfirst(strtolower($fiche['statut'])) ?>
      </div>
    <?php else: ?>
      <span style="font-size:.78rem;color:var(--text-muted);">Aucune fiche</span>
    <?php endif; ?>
  </div>

  <input type="date" class="form-control" style="width:auto;max-width:155px;"
         value="<?= $date_str ?>"
         onchange="window.location='fiche.php?date='+this.value"
         max="<?= date('Y-m-d') ?>">

  <a href="fiche.php?date=<?= $date_next ?>" class="btn btn-secondary btn-sm"
     <?= $est_futur ? 'style="opacity:.4;pointer-events:none;"' : '' ?>>
    <i class="fa-solid fa-chevron-right"></i>
  </a>

  <a href="fiche.php" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-calendar-day"></i>
  </a>
</div>

<!-- Alertes -->
<?php if ($fiche && $fiche['statut'] === 'REJETEE' && !empty($fiche['commentaire_validateur'])): ?>
<div class="alert alert-error" style="margin-bottom:16px;">
  <i class="fa-solid fa-circle-xmark" style="flex-shrink:0;margin-top:2px;"></i>
  <div>
    <strong>Fiche rejetée</strong> — <?= e($fiche['commentaire_validateur']) ?>
    <?php if ($fiche['valide_par']): ?>
      <div style="font-size:.77rem;opacity:.75;margin-top:3px;">
        Par <?= e($fiche['validateur_nom'] ?? '') ?> · <?= format_datetime($fiche['valide_le']) ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php elseif ($fiche && $fiche['statut'] === 'VALIDEE'): ?>
<div class="alert alert-success" style="margin-bottom:16px;">
  <i class="fa-solid fa-circle-check" style="flex-shrink:0;"></i>
  <div>
    Validée par <strong><?= e($fiche['validateur_nom'] ?? '—') ?></strong>
    le <?= format_datetime($fiche['valide_le']) ?>
    <?php if (!empty($fiche['commentaire_validateur'])): ?>
      <div style="font-size:.78rem;opacity:.8;margin-top:3px;">«<?= e($fiche['commentaire_validateur']) ?>»</div>
    <?php endif; ?>
  </div>
</div>
<?php elseif ($fiche && $fiche['statut'] === 'SOUMISE'): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <i class="fa-solid fa-clock" style="flex-shrink:0;"></i>
  Fiche soumise — en attente de validation par votre responsable.
</div>
<?php elseif ($est_futur): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <i class="fa-solid fa-calendar-xmark" style="flex-shrink:0;"></i>
  Impossible de créer une fiche pour une date future.
</div>
<?php endif; ?>

<!-- Barre progression (lecture seule) -->
<?php if ($fiche && !empty($activites) && $est_readonly):
  $nb_t = count($activites);
  $nb_o = count(array_filter($activites, fn($a) => ($a['atteint'] ?? null) == 1));
  $nb_n = count(array_filter($activites, fn($a) => ($a['atteint'] ?? null) == 0));
  $pct  = $nb_t > 0 ? round($nb_o / $nb_t * 100) : 0;
?>
<div class="progress-bar-wrap">
  <span style="font-size:.82rem;font-weight:600;color:var(--text-muted);white-space:nowrap;">
    <i class="fa-solid fa-chart-pie" style="color:var(--blue-500);"></i> Taux de réalisation
  </span>
  <div class="progress-track"><div class="progress-fill" style="width:<?= $pct ?>%;"></div></div>
  <span style="font-size:.85rem;font-weight:800;color:var(--blue-600);white-space:nowrap;"><?= $pct ?>%</span>
  <span style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
    <?= $nb_o ?>/<?= $nb_t ?> atteint<?= $nb_o > 1 ? 's' : '' ?>
    <?php if ($nb_n > 0): ?>
      · <span style="color:var(--danger);"><?= $nb_n ?> non atteint<?= $nb_n > 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </span>
</div>
<?php endif; ?>

<!-- FORMULAIRE -->
<form method="POST" action="" id="ficheForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="sauvegarder">
  <?php if ($fiche_id): ?>
    <input type="hidden" name="fiche_id" value="<?= $fiche_id ?>">
  <?php endif; ?>

  <!-- Identité -->
  <div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:13px 18px;">
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <div style="width:40px;height:40px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex-shrink:0;">
          <?= e(mb_strtoupper(mb_substr($user['prenoms'],0,1).mb_substr($user['nom'],0,1))) ?>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:700;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= e($user['prenoms'].' '.$user['nom']) ?>
          </div>
          <div style="font-size:.75rem;color:var(--text-muted);">
            <?= e($user['groupe_libelle'] ?? '') ?> · <?= e($user['matricule'] ?? '') ?>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Date</div>
          <div style="font-weight:700;font-size:.85rem;color:var(--blue-700);"><?= e($date_label) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Activités -->
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header">
      <i class="fa-solid fa-list-check" style="color:var(--blue-500);"></i>
      <h2>Activités de la journée</h2>
      <?php if (!$est_readonly): ?>
        <span id="cpt" style="font-size:.78rem;color:var(--text-muted);margin-left:auto;margin-right:8px;"></span>
      <?php endif; ?>
    </div>
    <div class="card-body">

      <div class="activites-list" id="activitesBody">
        <?php
        $lignes = !empty($activites) ? $activites : [[]];
        foreach ($lignes as $i => $act):
          $at_val = $act['atteint'] ?? null;
          $at_str = $at_val === 1 ? 'oui' : ($at_val === 0 ? 'non' : '');
        ?>
        <div class="activite-card">
          <!-- Header carte -->
          <div class="act-card-header">
            <div class="act-num <?= $est_readonly ? 'ro' : '' ?>"><?= $i+1 ?></div>
            <div class="act-card-title">
              <?php if ($est_readonly && !empty($act['objectif_tache'])): ?>
                <?= e(mb_strimwidth($act['objectif_tache'], 0, 55, '…')) ?>
              <?php else: ?>
                Activité <?= $i+1 ?>
              <?php endif; ?>
            </div>
            <?php if ($est_readonly): ?>
              <?php if ($at_str === 'oui'): ?>
                <span class="badge-atteint oui"><i class="fa-solid fa-check"></i> Atteint</span>
              <?php elseif ($at_str === 'non'): ?>
                <span class="badge-atteint non"><i class="fa-solid fa-xmark"></i> Non atteint</span>
              <?php else: ?>
                <span class="badge-atteint nd">—</span>
              <?php endif; ?>
            <?php else: ?>
              <button type="button" class="btn-del-card" onclick="supprimerCarte(this)">
                <i class="fa-solid fa-trash"></i>
              </button>
            <?php endif; ?>
          </div>

          <!-- Corps carte -->
          <div class="act-card-body">

            <!-- Objectif -->
            <div class="f-objectif">
              <div class="f-label"><i class="fa-solid fa-bullseye"></i> Objectif / Tâche <span style="color:var(--danger);">*</span></div>
              <?php if ($est_readonly): ?>
                <div class="f-val <?= empty($act['objectif_tache']) ? 'empty' : '' ?>">
                  <?= !empty($act['objectif_tache']) ? nl2br(e($act['objectif_tache'])) : 'Non renseigné' ?>
                </div>
              <?php else: ?>
                <textarea name="activites[<?= $i ?>][objectif_tache]" class="form-control"
                          rows="3" placeholder="Décrire précisément l'objectif ou la tâche…"
                          required><?= e($act['objectif_tache'] ?? '') ?></textarea>
              <?php endif; ?>
            </div>

            <!-- Durée -->
            <div class="f-duree">
              <div class="f-label"><i class="fa-regular fa-clock"></i> Durée estimée</div>
              <?php if ($est_readonly): ?>
                <div class="f-val <?= empty($act['duree_estimee']) ? 'empty' : '' ?>">
                  <?= !empty($act['duree_estimee']) ? minutes_en_texte((int)$act['duree_estimee']) : '—' ?>
                </div>
              <?php else: ?>
                <div style="display:flex;align-items:center;gap:7px;">
                  <input type="number" name="activites[<?= $i ?>][duree_estimee]"
                         class="form-control" placeholder="0"
                         value="<?= e($act['duree_estimee'] ?? '') ?>"
                         min="1" max="480" style="max-width:80px;">
                  <span style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">min</span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Atteint -->
            <div class="f-atteint">
              <div class="f-label"><i class="fa-solid fa-circle-check"></i> Atteint ?</div>
              <?php if ($est_readonly): ?>
                <?php if ($at_str === 'oui'): ?>
                  <div class="f-val" style="color:var(--success);font-weight:700;"><i class="fa-solid fa-check-circle"></i> Oui</div>
                <?php elseif ($at_str === 'non'): ?>
                  <div class="f-val" style="color:var(--danger);font-weight:700;"><i class="fa-solid fa-times-circle"></i> Non</div>
                <?php else: ?>
                  <div class="f-val empty">Non renseigné</div>
                <?php endif; ?>
              <?php else: ?>
                <div class="radio-pills">
                  <label class="radio-pill-label">
                    <input type="radio" name="activites[<?= $i ?>][atteint]" value="oui"
                           <?= $at_str === 'oui' ? 'checked' : '' ?> class="r-atteint">
                    <div class="pill-btn <?= $at_str === 'oui' ? 'active-oui' : '' ?>">
                      <i class="fa-solid fa-check"></i> Oui
                    </div>
                  </label>
                  <label class="radio-pill-label">
                    <input type="radio" name="activites[<?= $i ?>][atteint]" value="non"
                           <?= $at_str === 'non' ? 'checked' : '' ?> class="r-atteint">
                    <div class="pill-btn <?= $at_str === 'non' ? 'active-non' : '' ?>">
                      <i class="fa-solid fa-xmark"></i> Non
                    </div>
                  </label>
                </div>
              <?php endif; ?>
            </div>

            <!-- Difficultés -->
            <div class="f-difficultes">
              <div class="f-label"><i class="fa-solid fa-triangle-exclamation"></i> Difficultés</div>
              <?php if ($est_readonly): ?>
                <div class="f-val <?= empty($act['difficultes']) ? 'empty' : '' ?>">
                  <?= !empty($act['difficultes']) ? nl2br(e($act['difficultes'])) : '—' ?>
                </div>
              <?php else: ?>
                <textarea name="activites[<?= $i ?>][difficultes]" class="form-control"
                          rows="2" placeholder="Obstacles rencontrés…"><?= e($act['difficultes'] ?? '') ?></textarea>
              <?php endif; ?>
            </div>

            <!-- Solutions -->
            <div class="f-solutions">
              <div class="f-label"><i class="fa-solid fa-lightbulb"></i> Solutions apportées</div>
              <?php if ($est_readonly): ?>
                <div class="f-val <?= empty($act['solutions']) ? 'empty' : '' ?>">
                  <?= !empty($act['solutions']) ? nl2br(e($act['solutions'])) : '—' ?>
                </div>
              <?php else: ?>
                <textarea name="activites[<?= $i ?>][solutions]" class="form-control"
                          rows="2" placeholder="Comment vous avez résolu ou contourné…"><?= e($act['solutions'] ?? '') ?></textarea>
              <?php endif; ?>
            </div>

            <!-- Documents -->
            <div class="f-documents">
              <div class="f-label"><i class="fa-solid fa-file-lines"></i> Documents produits (Outputs)</div>
              <?php if ($est_readonly): ?>
                <div class="f-val <?= empty($act['documents_sortie']) ? 'empty' : '' ?>">
                  <?= !empty($act['documents_sortie']) ? nl2br(e($act['documents_sortie'])) : '—' ?>
                </div>
              <?php else: ?>
                <textarea name="activites[<?= $i ?>][documents_sortie]" class="form-control"
                          rows="2" placeholder="Livrables, rapports, emails envoyés…"><?= e($act['documents_sortie'] ?? '') ?></textarea>
              <?php endif; ?>
            </div>

            <!-- ── Pièces jointes ── -->
            <?php
              $act_id  = isset($act['id']) ? (int)$act['id'] : 0;
              $pj_list = $act_id ? ($pj_par_activite[$act_id] ?? []) : [];
              $pj_css_id = 'pj-list-act-' . $i;
            ?>
            <div class="pj-zone f-documents" id="pz-<?= $i ?>" data-activite-idx="<?= $i ?>" data-activite-id="<?= $act_id ?>">
              <div class="pj-header">
                <i class="fa-solid fa-paperclip"></i> Pièces jointes
                <span class="pj-count" id="pj-count-<?= $i ?>"><?= count($pj_list) ?></span>
              </div>

              <!-- Liste des PJ existantes -->
              <div class="pj-list" id="<?= $pj_css_id ?>">
                <?php foreach ($pj_list as $pj):
                  $pj_icon_css = in_array($pj['extension'], ['doc','docx']) ? 'word'
                               : (in_array($pj['extension'], ['xls','xlsx','ods']) ? 'excel'
                               : ($pj['extension'] === 'pdf' ? 'pdf' : 'other'));
                  $fa_icon = $pj['icone'];
                ?>
                <div class="pj-item" id="pj-row-<?= $pj['id'] ?>">
                  <div class="pj-icon <?= $pj_icon_css ?>"><i class="fa-solid <?= $fa_icon ?>"></i></div>
                  <div style="flex:1;min-width:0;">
                    <div class="pj-nom" title="<?= e($pj['nom_original']) ?>"><?= e($pj['nom_original']) ?></div>
                    <div class="pj-meta"><?= e($pj['taille_aff']) ?></div>
                  </div>
                  <a href="upload_suivi.php?action=download&id=<?= $pj['id'] ?>"
                     target="_blank" class="pj-btn-dl" title="Télécharger">
                    <i class="fa-solid fa-download"></i>
                  </a>
                  <?php if (!$est_readonly): ?>
                  <button type="button" class="pj-btn-del"
                          onclick="supprimerPJ(<?= $pj['id'] ?>, <?= $i ?>)"
                          title="Supprimer">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>

              <?php if (!$est_readonly): ?>
              <!-- Zone upload -->
              <div class="pj-upload-area">
                <label class="pj-upload-label">
                  <i class="fa-solid fa-cloud-arrow-up"></i> Ajouter un fichier
                  <input type="file"
                         accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods"
                         multiple
                         onchange="uploadPJ(this, <?= $i ?>, <?= $act_id ?>, <?= $fiche_id ?? 'null' ?>)">
                </label>
                <span class="pj-hint">PDF, Word, Excel · max 10 Mo par fichier</span>
                <!-- Barre de progression -->
                <div class="pj-progress-wrap" id="pj-prog-<?= $i ?>">
                  <div class="pj-progress-fill" id="pj-prog-fill-<?= $i ?>"></div>
                </div>
              </div>
              <?php endif; ?>
            </div>

          </div><!-- /act-card-body -->
        </div><!-- /activite-card -->
        <?php endforeach; ?>
      </div><!-- /activites-list -->

      <?php if (!$est_readonly): ?>
      <button type="button" class="btn-add" onclick="ajouterCarte()">
        <i class="fa-solid fa-circle-plus"></i> Ajouter une activité
      </button>
      <?php endif; ?>

    </div>
  </div><!-- /card activités -->

  <!-- Objectifs lendemain -->
  <div class="lendemain-section">
    <div class="lendemain-header">
      <i class="fa-solid fa-forward-fast" style="color:#0ea5e9;"></i>
      <h3>Objectifs du lendemain</h3>
    </div>
    <div class="lendemain-body">
      <?php if ($est_readonly): ?>
        <div class="f-val <?= empty($fiche['objectifs_lendemain']) ? 'empty' : '' ?>" style="white-space:pre-wrap;">
          <?= !empty($fiche['objectifs_lendemain']) ? e($fiche['objectifs_lendemain']) : 'Aucun objectif renseigné.' ?>
        </div>
      <?php else: ?>
        <textarea name="objectifs_lendemain" class="form-control" rows="4"
                  placeholder="Listez les tâches et objectifs prévus pour demain…"><?= e($fiche['objectifs_lendemain'] ?? '') ?></textarea>
      <?php endif; ?>
    </div>
  </div>

  <!-- Actions sticky -->
  <?php if (!$est_readonly): ?>
  <div class="actions-bar">
    <button type="submit" name="action" value="sauvegarder" class="btn btn-secondary" id="btnSave">
      <i class="fa-solid fa-floppy-disk"></i> Enregistrer le brouillon
    </button>
    <?php if ($fiche && in_array($fiche['statut'], ['BROUILLON','REJETEE'])): ?>
    <button type="button" class="btn btn-primary" onclick="soumettreFiche()">
      <i class="fa-solid fa-paper-plane"></i> Soumettre pour validation
    </button>
    <?php elseif (!$fiche): ?>
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid fa-floppy-disk"></i> Créer et enregistrer
    </button>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</form>

<!-- Form soumission caché -->
<?php if ($fiche_id && !$est_readonly): ?>
<form method="POST" action="" id="formSoumettre" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="soumettre">
  <input type="hidden" name="fiche_id" value="<?= $fiche_id ?>">
</form>
<?php endif; ?>

<!-- Toast autosave -->
<div class="autosave-toast" id="toast">
  <i class="fa-solid fa-rotate fa-spin"></i> Sauvegarde automatique…
</div>

<?php
/* ── Template JS pour nouvelle carte ── */
ob_start(); ?>
<div class="activite-card">
  <div class="act-card-header">
    <div class="act-num">__NUM__</div>
    <div class="act-card-title">Activité __NUM__</div>
    <button type="button" class="btn-del-card" onclick="supprimerCarte(this)">
      <i class="fa-solid fa-trash"></i>
    </button>
  </div>
  <div class="act-card-body">
    <div class="f-objectif">
      <div class="f-label"><i class="fa-solid fa-bullseye"></i> Objectif / Tâche <span style="color:var(--danger);">*</span></div>
      <textarea name="activites[__IDX__][objectif_tache]" class="form-control" rows="3" placeholder="Décrire précisément l'objectif ou la tâche…" required></textarea>
    </div>
    <div class="f-duree">
      <div class="f-label"><i class="fa-regular fa-clock"></i> Durée estimée</div>
      <div style="display:flex;align-items:center;gap:7px;">
        <input type="number" name="activites[__IDX__][duree_estimee]" class="form-control" placeholder="0" min="1" max="480" style="max-width:80px;">
        <span style="font-size:.78rem;color:var(--text-muted);">min</span>
      </div>
    </div>
    <div class="f-atteint">
      <div class="f-label"><i class="fa-solid fa-circle-check"></i> Atteint ?</div>
      <div class="radio-pills">
        <label class="radio-pill-label">
          <input type="radio" name="activites[__IDX__][atteint]" value="oui" class="r-atteint">
          <div class="pill-btn"><i class="fa-solid fa-check"></i> Oui</div>
        </label>
        <label class="radio-pill-label">
          <input type="radio" name="activites[__IDX__][atteint]" value="non" class="r-atteint">
          <div class="pill-btn"><i class="fa-solid fa-xmark"></i> Non</div>
        </label>
      </div>
    </div>
    <div class="f-difficultes">
      <div class="f-label"><i class="fa-solid fa-triangle-exclamation"></i> Difficultés</div>
      <textarea name="activites[__IDX__][difficultes]" class="form-control" rows="2" placeholder="Obstacles rencontrés…"></textarea>
    </div>
    <div class="f-solutions">
      <div class="f-label"><i class="fa-solid fa-lightbulb"></i> Solutions apportées</div>
      <textarea name="activites[__IDX__][solutions]" class="form-control" rows="2" placeholder="Comment vous avez résolu ou contourné…"></textarea>
    </div>
    <div class="f-documents">
      <div class="f-label"><i class="fa-solid fa-file-lines"></i> Documents produits (Outputs)</div>
      <textarea name="activites[__IDX__][documents_sortie]" class="form-control" rows="2" placeholder="Livrables, rapports, emails envoyés…"></textarea>
    </div>
    <div class="pj-zone f-documents" id="pz-__IDX__" data-activite-idx="__IDX__" data-activite-id="0">
      <div class="pj-header">
        <i class="fa-solid fa-paperclip"></i> Pièces jointes
        <span class="pj-count" id="pj-count-__IDX__">0</span>
      </div>
      <div class="pj-list" id="pj-list-act-__IDX__"></div>
      <div class="pj-upload-area">
        <label class="pj-upload-label">
          <i class="fa-solid fa-cloud-arrow-up"></i> Ajouter un fichier
          <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods" multiple
                 onchange="uploadPJ(this, __IDX__, 0, <?= (int)($fiche_id ?? 0) ?>)">
        </label>
        <span class="pj-hint">PDF, Word, Excel · max 10 Mo par fichier</span>
        <div class="pj-progress-wrap" id="pj-prog-__IDX__">
          <div class="pj-progress-fill" id="pj-prog-fill-__IDX__"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $tpl = trim(ob_get_clean());

$extra_scripts = '<script>
const TPL = ' . json_encode($tpl) . ';
let rowCount = document.querySelectorAll(".activite-card").length;

/* ── CSRF token pour les appels AJAX ── */
const CSRF_TOKEN = document.querySelector(\'[name="csrf_token"]\')?.value || "";

/* ── Ajouter une carte ── */
function ajouterCarte() {
  const html = TPL
    .replaceAll("__IDX__", rowCount)
    .replaceAll("__NUM__", rowCount + 1);
  const tmp = document.createElement("div");
  tmp.innerHTML = html;
  const carte = tmp.firstElementChild;
  document.getElementById("activitesBody").appendChild(carte);
  bindRadios(carte);
  bindDragDrop(carte);
  rowCount++;
  majCpt();
  carte.scrollIntoView({ behavior:"smooth", block:"center" });
  carte.querySelector("textarea").focus();
}

/* ── Supprimer une carte ── */
function supprimerCarte(btn) {
  if (document.querySelectorAll(".activite-card").length <= 1) {
    alert("La fiche doit contenir au moins une activité.");
    return;
  }
  const carte = btn.closest(".activite-card");
  carte.style.opacity = "0";
  carte.style.transform = "translateX(18px)";
  setTimeout(() => { carte.remove(); renumerotation(); majCpt(); }, 200);
}

/* ── Renumérotation ── */
function renumerotation() {
  document.querySelectorAll(".activite-card").forEach((c, i) => {
    const num   = c.querySelector(".act-num");
    const titre = c.querySelector(".act-card-title");
    if (num)   num.textContent   = i + 1;
    if (titre && titre.textContent.startsWith("Activité")) titre.textContent = "Activité " + (i + 1);
    c.querySelectorAll("[name]").forEach(el => {
      el.name = el.name.replace(/activites\[\d+\]/, "activites[" + i + "]");
    });

    const pz = c.querySelector(".pj-zone");
    if (pz) pz.dataset.activiteIdx = i;
  });
  rowCount = document.querySelectorAll(".activite-card").length;
}

/* ── Compteur ── */
function majCpt() {
  const el = document.getElementById("cpt");
  if (!el) return;
  const n = document.querySelectorAll(".activite-card").length;
  el.textContent = n + " activité" + (n > 1 ? "s" : "");
}

/* ── Radios visuels ── */
function bindRadios(scope) {
  scope.querySelectorAll(".r-atteint").forEach(radio => {
    if (radio.checked) appliquerPill(radio);
    radio.addEventListener("change", () => {
      const name = radio.name;
      scope.querySelectorAll(\'[name="\' + name + \'"]\').forEach(r => resetPill(r));
      appliquerPill(radio);
    });
    const pill = radio.closest("label").querySelector(".pill-btn");
    if (pill) pill.addEventListener("click", () => {
      radio.checked = true;
      radio.dispatchEvent(new Event("change", { bubbles:true }));
    });
  });
}

function appliquerPill(radio) {
  const pill = radio.closest("label").querySelector(".pill-btn");
  if (!pill) return;
  if (radio.value === "oui") pill.classList.add("active-oui");
  else pill.classList.add("active-non");
}

function resetPill(radio) {
  const pill = radio.closest("label").querySelector(".pill-btn");
  if (!pill) return;
  pill.classList.remove("active-oui", "active-non");
}

/* ── Soumission ── */
function soumettreFiche() {
  if (confirm("Soumettre cette fiche pour validation ?\\nVous ne pourrez plus la modifier.")) {
    document.getElementById("formSoumettre").submit();
  }
}

/* ── Auto-save ── */
function showToast() {
  const t = document.getElementById("toast");
  t.classList.add("show");
  setTimeout(() => t.classList.remove("show"), 2500);
}

setInterval(() => {
  showToast();
  const btn = document.getElementById("btnSave");
  if (btn) btn.click();
}, 180000);

/* ════════════════════════════════════════════
   PIÈCES JOINTES – Upload AJAX
════════════════════════════════════════════ */

const ICONES_EXT = {
  pdf : { cls:"pdf",   fa:"fa-file-pdf"   },
  doc : { cls:"word",  fa:"fa-file-word"  },
  docx: { cls:"word",  fa:"fa-file-word"  },
  xls : { cls:"excel", fa:"fa-file-excel" },
  xlsx: { cls:"excel", fa:"fa-file-excel" },
  odt : { cls:"other", fa:"fa-file-lines" },
  ods : { cls:"other", fa:"fa-file-lines" },
};

/**
 * Appelé par onchange sur <input type="file">
 * @param {HTMLInputElement} input
 * @param {number}  idx      – index de la carte (pour retrouver les éléments DOM)
 * @param {number}  actId    – activite_id en base (0 si nouvelle)
 * @param {number|null} ficheId – fiche_id en base (null si fiche pas encore sauvegardée)
 */
async function uploadPJ(input, idx, actId, ficheId) {
  if (!ficheId) {
    alert("Veuillez d\'abord enregistrer le brouillon avant d\'ajouter des fichiers.");
    input.value = "";
    return;
  }

  const fichiers = Array.from(input.files);
  if (!fichiers.length) return;

  const prog     = document.getElementById("pj-prog-" + idx);
  const progFill = document.getElementById("pj-prog-fill-" + idx);
  if (prog) prog.style.display = "block";

  let ok = 0;
  for (let f = 0; f < fichiers.length; f++) {
    const fichier = fichiers[f];
    if (progFill) progFill.style.width = Math.round(((f) / fichiers.length) * 100) + "%";

    const fd = new FormData();
    fd.append("action",      "upload");
    fd.append("csrf_token",  CSRF_TOKEN);
    fd.append("fiche_id",    ficheId);
    fd.append("activite_id", actId);
    fd.append("fichier",     fichier);

    try {
      const resp = await fetch("upload_suivi.php", { method:"POST", body:fd });
      const data = await resp.json();
      if (data.succes) {
        ajouterPJItem(idx, data.pj, ficheId !== null);
        ok++;
      } else {
        alert("Erreur : " + data.message + " (" + fichier.name + ")");
      }
    } catch (e) {
      alert("Erreur réseau lors de l\'envoi de " + fichier.name);
    }
  }

  if (progFill) progFill.style.width = "100%";
  setTimeout(() => { if (prog) prog.style.display = "none"; if (progFill) progFill.style.width = "0"; }, 600);
  input.value = "";
}

/**
 * Ajoute une ligne PJ dans la liste DOM
 */
function ajouterPJItem(idx, pj, editable) {
  const liste = document.getElementById("pj-list-act-" + idx);
  if (!liste) return;

  const info = ICONES_EXT[pj.extension] || { cls:"other", fa:"fa-file" };

  const div = document.createElement("div");
  div.className = "pj-item";
  div.id = "pj-row-" + pj.id;
  div.innerHTML = `
    <div class="pj-icon ${info.cls}"><i class="fa-solid ${info.fa}"></i></div>
    <div style="flex:1;min-width:0;">
      <div class="pj-nom" title="${pj.nom}">${pj.nom}</div>
      <div class="pj-meta">${pj.taille}</div>
    </div>
    <a href="${pj.url_dl}" target="_blank" class="pj-btn-dl" title="Télécharger">
      <i class="fa-solid fa-download"></i>
    </a>
    ${editable ? `<button type="button" class="pj-btn-del"
        onclick="supprimerPJ(${pj.id}, ${idx})" title="Supprimer">
      <i class="fa-solid fa-trash"></i>
    </button>` : ""}
  `;
  liste.appendChild(div);
  majCompteurPJ(idx);
}

/**
 * Supprime une PJ via AJAX
 */
async function supprimerPJ(pjId, idx) {
  if (!confirm("Supprimer ce fichier ?")) return;

  const fd = new FormData();
  fd.append("action",     "supprimer");
  fd.append("csrf_token", CSRF_TOKEN);
  fd.append("pj_id",      pjId);

  try {
    const resp = await fetch("upload_suivi.php", { method:"POST", body:fd });
    const data = await resp.json();
    if (data.succes) {
      const row = document.getElementById("pj-row-" + pjId);
      if (row) {
        row.style.opacity = "0";
        setTimeout(() => { row.remove(); majCompteurPJ(idx); }, 200);
      }
    } else {
      alert("Erreur : " + data.message);
    }
  } catch (e) {
    alert("Erreur réseau.");
  }
}

/**
 * Met à jour le compteur de PJ affiché dans le header
 */
function majCompteurPJ(idx) {
  const liste = document.getElementById("pj-list-act-" + idx);
  const badge = document.getElementById("pj-count-" + idx);
  if (!liste || !badge) return;
  badge.textContent = liste.querySelectorAll(".pj-item").length;
}

/* ── Drag & Drop sur la zone PJ ── */
function bindDragDrop(scope) {
  const zone = scope.querySelector(".pj-zone");
  if (!zone) return;

  zone.addEventListener("dragover", e => { e.preventDefault(); zone.classList.add("dragover"); });
  zone.addEventListener("dragleave", () => zone.classList.remove("dragover"));
  zone.addEventListener("drop", e => {
    e.preventDefault();
    zone.classList.remove("dragover");
    const fileInput = zone.querySelector("input[type=file]");
    if (!fileInput) return;
    // Simuler la sélection
    const dt = new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
    fileInput.dispatchEvent(new Event("change"));
  });
}

/* ── Init ── */
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".activite-card").forEach(c => {
    bindRadios(c);
    bindDragDrop(c);
  });
  majCpt();
});
</script>';

include __DIR__ . '/../../views/layout/footer.php';
?>