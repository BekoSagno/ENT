<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/suivi.php';

session_demarrer();
exiger_permission('SUIVI_QUOTIDIEN', 'VOIR');

$user        = user_courant();
$peut_valider = a_permission('SUIVI_QUOTIDIEN', 'VALIDER');
$peut_voir_tout = $peut_valider || in_array($user['groupe_code'], ['SUPER_ADMIN','DG','SS','CQ']);

// ── Filtres ──
$filtres = [
    'statut'      => clean($_GET['statut']     ?? ''),
    'date_debut'  => clean($_GET['date_debut'] ?? ''),
    'date_fin'    => clean($_GET['date_fin']   ?? ''),
    'departement' => clean($_GET['dept']       ?? ''),
];

// Les non-superviseurs ne voient que leurs propres fiches
if (!$peut_voir_tout) {
    $filtres['utilisateur_id'] = (int)$user['id'];
}

// Par défaut : fiches soumises pour les validateurs, toutes pour les autres
if ($peut_valider && empty($filtres['statut'])) {
    $filtres['statut'] = 'SOUMISE';
}

// ── Pagination ──
$par_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $par_page;

// ── Données ──
try {
    $result     = suivi_lister_fiches($filtres, $par_page, $offset);
    $fiches     = $result['data'];
    $total      = $result['total'];
} catch (Throwable $e) {
    $fiches = []; $total = 0;
    error_log('[CRICT index suivi] ' . $e->getMessage());
}
$pagination = paginer($total, $par_page, $page);

// ── Compteurs par statut (pour les onglets) ──
function compter_par_statut(array $filtres_base): array {
    $db     = get_db();
    $where  = '1=1';
    $params = [];
    if (!empty($filtres_base['utilisateur_id'])) {
        $where .= ' AND f.utilisateur_id = ?';
        $params[] = $filtres_base['utilisateur_id'];
    }
    if (!empty($filtres_base['date_debut'])) {
        $where .= ' AND f.date_fiche >= ?'; $params[] = $filtres_base['date_debut'];
    }
    if (!empty($filtres_base['date_fin'])) {
        $where .= ' AND f.date_fiche <= ?'; $params[] = $filtres_base['date_fin'];
    }
    if (!empty($filtres_base['departement'])) {
        $where .= ' AND g.departement = ?'; $params[] = $filtres_base['departement'];
    }
    try {
        $stmt = $db->prepare("
            SELECT f.statut, COUNT(*) AS nb
            FROM suivi_fiches_journalieres f
            JOIN utilisateurs u ON u.id = f.utilisateur_id
            JOIN groupes_utilisateurs g ON g.id = u.groupe_id
            WHERE $where
            GROUP BY f.statut
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $counts = ['SOUMISE'=>0,'VALIDEE'=>0,'REJETEE'=>0,'BROUILLON'=>0,'TOTAL'=>0];
        foreach ($rows as $r) {
            $counts[$r['statut']] = (int)$r['nb'];
            $counts['TOTAL'] += (int)$r['nb'];
        }
        return $counts;
    } catch (Throwable $e) {
        return ['SOUMISE'=>0,'VALIDEE'=>0,'REJETEE'=>0,'BROUILLON'=>0,'TOTAL'=>0];
    }
}

$filtres_base_compteur = $filtres;
unset($filtres_base_compteur['statut']); // compteur sans filtre statut
$compteurs = compter_par_statut($filtres_base_compteur);

// ── Mois et années pour les filtres rapides ──
$mois_fr = [1=>'Janv.',2=>'Févr.',3=>'Mars',4=>'Avr.',5=>'Mai',6=>'Juin',
            7=>'Juil.',8=>'Août',9=>'Sept.',10=>'Oct.',11=>'Nov.',12=>'Déc.'];



$page_titre      = $peut_valider ? 'Fiches à valider' : 'Mes fiches de suivi';
$page_breadcrumb = 'Suivi & Évaluation · Liste des fiches';
$module_actif    = 'SUIVI_QUOTIDIEN';

include __DIR__ . '/../../views/layout/header.php';
?>

<style>
/* ── Onglets statut ── */
.tabs-bar {
  display: flex; gap: 6px; flex-wrap: wrap;
  margin-bottom: 20px;
}
.tab-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 8px 16px; border-radius: 8px;
  font-size: .82rem; font-weight: 600;
  text-decoration: none; border: 1.5px solid var(--border);
  color: var(--text-muted); background: var(--surface);
  transition: all .15s; cursor: pointer;
  white-space: nowrap;
}
.tab-btn:hover { border-color: var(--blue-400); color: var(--blue-600); background: var(--blue-50); }
.tab-btn.active { background: var(--blue-600); color: #fff; border-color: var(--blue-600); }
.tab-count {
  background: rgba(255,255,255,.25); color: inherit;
  padding: 1px 7px; border-radius: 20px;
  font-size: .72rem; font-weight: 800;
}
.tab-btn:not(.active) .tab-count { background: var(--bg); color: var(--text-muted); }
.tab-btn.active .tab-count { background: rgba(255,255,255,.25); color: #fff; }

/* ── Couleurs onglets ── */
.tab-btn.t-soumise.active  { background:#1e40af; border-color:#1e40af; }
.tab-btn.t-validee.active  { background:#166534; border-color:#166534; }
.tab-btn.t-rejetee.active  { background:#991b1b; border-color:#991b1b; }
.tab-btn.t-brouillon.active{ background:#92400e; border-color:#92400e; }

/* ── Filtres ── */
.filtres-bar {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 14px 18px;
  margin-bottom: 20px;
  display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;
}
.filtre-group { display: flex; flex-direction: column; gap: 5px; min-width: 130px; }
.filtre-label { font-size: .72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
.filtres-bar .form-control { font-size: .84rem; }

/* ── Raccourcis période ── */
.periode-shortcuts {
  display: flex; gap: 6px; flex-wrap: wrap; align-items: center;
}
.shortcut-btn {
  padding: 4px 10px; border-radius: 6px;
  border: 1px solid var(--border); background: var(--bg);
  font-size: .75rem; font-weight: 600; color: var(--text-muted);
  cursor: pointer; transition: all .12s;
}
.shortcut-btn:hover { border-color: var(--blue-400); color: var(--blue-600); }

/* ── Grille fiches ── */
.fiches-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

/* ── Carte fiche ── */
.fiche-card {
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  transition: box-shadow .2s, transform .15s;
  display: flex; flex-direction: column;
}
.fiche-card:hover {
  box-shadow: 0 6px 24px rgba(13,50,120,.10);
  transform: translateY(-1px);
}
.fiche-card.soumise  { border-left: 4px solid #3b82f6; }
.fiche-card.validee  { border-left: 4px solid #22c55e; }
.fiche-card.rejetee  { border-left: 4px solid #ef4444; }
.fiche-card.brouillon{ border-left: 4px solid #f59e0b; }

.fiche-card-top {
  padding: 14px 16px;
  display: flex; align-items: flex-start; gap: 12px;
  border-bottom: 1px solid var(--border);
}
.fiche-avatar {
  width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
  background: var(--blue-100); color: var(--blue-700);
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; font-weight: 800;
}
.fiche-meta { flex: 1; min-width: 0; }
.fiche-nom  { font-size: .88rem; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fiche-sub  { font-size: .73rem; color: var(--text-muted); margin-top: 2px; }
.fiche-date-badge {
  flex-shrink: 0; text-align: right;
}
.fiche-date-badge .jour { font-size: 1.4rem; font-weight: 800; color: var(--blue-700); line-height: 1; }
.fiche-date-badge .mois { font-size: .68rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; }

.fiche-card-body { padding: 12px 16px; flex: 1; }

/* Stats mini de la fiche */
.fiche-stats {
  display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap;
}
.fiche-stat {
  display: flex; align-items: center; gap: 5px;
  background: var(--bg); border-radius: 6px;
  padding: 4px 9px; font-size: .75rem; font-weight: 600; color: var(--text-muted);
}
.fiche-stat i { font-size: .7rem; }
.fiche-stat.green { background: #dcfce7; color: #166534; }
.fiche-stat.red   { background: #fee2e2; color: #991b1b; }
.fiche-stat.blue  { background: var(--blue-100); color: var(--blue-700); }

/* Barre mini progression */
.mini-progress {
  height: 5px; background: #e5e7eb; border-radius: 20px; overflow: hidden; margin-bottom: 10px;
}
.mini-progress-fill {
  height: 100%; border-radius: 20px;
  background: linear-gradient(90deg, var(--blue-500), var(--accent));
}

/* Commentaire validateur */
.fiche-commentaire {
  background: var(--bg); border-radius: 7px;
  padding: 7px 10px; font-size: .77rem; color: var(--text-muted);
  border-left: 3px solid var(--border);
  margin-bottom: 8px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.fiche-commentaire.rejet { border-left-color: var(--danger); color: #991b1b; background: #fff5f5; }
.fiche-commentaire.valid { border-left-color: var(--success); color: #166534; background: #f0fdf4; }

.fiche-card-footer {
  padding: 10px 16px;
  border-top: 1px solid var(--border);
  display: flex; align-items: center; gap: 8px;
  background: var(--bg);
}
.fiche-statut-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 20px;
  font-size: .71rem; font-weight: 700; flex-shrink: 0;
}
.p-soumise  { background: #dbeafe; color: #1e40af; }
.p-validee  { background: #dcfce7; color: #166534; }
.p-rejetee  { background: #fee2e2; color: #991b1b; }
.p-brouillon{ background: #fef3c7; color: #92400e; }

/* ── Empty state ── */
.empty-state {
  text-align: center; padding: 60px 24px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius);
}
.empty-icon {
  width: 64px; height: 64px; border-radius: 50%;
  background: var(--blue-100); color: var(--blue-400);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.6rem; margin: 0 auto 16px;
}
.empty-state h3 { font-size: 1rem; font-weight: 700; color: var(--text); margin-bottom: 6px; }
.empty-state p  { font-size: .85rem; color: var(--text-muted); margin-bottom: 20px; }

/* ── Pagination ── */
.pager {
  display: flex; align-items: center; gap: 5px;
  justify-content: center; flex-wrap: wrap;
}
.pager a, .pager span {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 34px; height: 34px; padding: 0 8px;
  border-radius: 8px; border: 1px solid var(--border);
  background: var(--surface); font-size: .83rem; font-weight: 600;
  color: var(--text-muted); text-decoration: none; transition: all .12s;
}
.pager a:hover { border-color: var(--blue-400); color: var(--blue-600); }
.pager span.active { background: var(--blue-600); color: #fff; border-color: var(--blue-600); }
.pager span.dots { border: none; background: none; cursor: default; }

/* ── Modal validation ── */
.modal-valider { max-width: 480px; }

/* ── Vue liste (alternative) ── */
.vue-toggle { display: flex; gap: 4px; }
.vue-btn {
  width: 32px; height: 32px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-muted); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; transition: all .12s;
}
.vue-btn.active { background: var(--blue-600); color: #fff; border-color: var(--blue-600); }

/* Vue liste */
.fiches-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px; }
.fiche-row {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 10px; padding: 12px 16px;
  display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
  transition: box-shadow .15s;
}
.fiche-row:hover { box-shadow: 0 3px 12px rgba(13,50,120,.08); }
.fiche-row.soumise  { border-left: 4px solid #3b82f6; }
.fiche-row.validee  { border-left: 4px solid #22c55e; }
.fiche-row.rejetee  { border-left: 4px solid #ef4444; }
.fiche-row.brouillon{ border-left: 4px solid #f59e0b; }

@media (max-width: 640px) {
  .fiches-grid { grid-template-columns: 1fr; }
  .filtres-bar { flex-direction: column; }
  .filtre-group { min-width: 100%; }
}
</style>

<!-- En-tête avec stats rapides -->
<div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:22px;flex-wrap:wrap;">
  <div style="flex:1;min-width:200px;">
    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:4px;">
      <?= $peut_voir_tout ? 'Toutes les fiches soumises' : 'Vos fiches de suivi quotidien' ?>
    </div>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <!-- Vue toggle -->
    <div class="vue-toggle">
      <button class="vue-btn active" id="btnGrille" onclick="basculerVue('grille')" title="Vue grille">
        <i class="fa-solid fa-grip"></i>
      </button>
      <button class="vue-btn" id="btnListe" onclick="basculerVue('liste')" title="Vue liste">
        <i class="fa-solid fa-list"></i>
      </button>
    </div>
    <a href="fiche.php" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> Nouvelle fiche
    </a>
  </div>
</div>

<!-- Onglets statut -->
<div class="tabs-bar">
  <?php
  $onglets = [
    ['statut'=>'',         'label'=>'Toutes',    'cls'=>'',          'icon'=>'fa-layer-group', 'cle'=>'TOTAL'],
    ['statut'=>'SOUMISE',  'label'=>'Soumises',  'cls'=>'t-soumise', 'icon'=>'fa-clock',        'cle'=>'SOUMISE'],
    ['statut'=>'VALIDEE',  'label'=>'Validées',  'cls'=>'t-validee', 'icon'=>'fa-circle-check', 'cle'=>'VALIDEE'],
    ['statut'=>'REJETEE',  'label'=>'Rejetées',  'cls'=>'t-rejetee', 'icon'=>'fa-circle-xmark', 'cle'=>'REJETEE'],
    ['statut'=>'BROUILLON','label'=>'Brouillons','cls'=>'t-brouillon','icon'=>'fa-pen',          'cle'=>'BROUILLON'],
  ];
  $qs_base = array_filter(['date_debut'=>$filtres['date_debut'],'date_fin'=>$filtres['date_fin'],'dept'=>$filtres['departement']]);
  foreach ($onglets as $o):
    $actif = $filtres['statut'] === $o['statut'];
    $qs    = http_build_query(array_merge($qs_base, ['statut'=>$o['statut'],'page'=>1]));
  ?>
  <a href="?<?= $qs ?>" class="tab-btn <?= $o['cls'] ?> <?= $actif ? 'active' : '' ?>">
    <i class="fa-solid <?= $o['icon'] ?>"></i>
    <?= $o['label'] ?>
    <span class="tab-count"><?= $compteurs[$o['cle']] ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Barre de filtres -->
<div class="filtres-bar" id="filtresBar">
  <div class="filtre-group">
    <span class="filtre-label"><i class="fa-solid fa-calendar-days"></i> Du</span>
    <input type="date" id="fDateDebut" class="form-control"
           value="<?= e($filtres['date_debut']) ?>"
           onchange="appliquerFiltres()">
  </div>
  <div class="filtre-group">
    <span class="filtre-label"><i class="fa-solid fa-calendar-days"></i> Au</span>
    <input type="date" id="fDateFin" class="form-control"
           value="<?= e($filtres['date_fin']) ?>"
           onchange="appliquerFiltres()">
  </div>
  <?php if ($peut_voir_tout): ?>
  <div class="filtre-group">
    <span class="filtre-label"><i class="fa-solid fa-building"></i> Département</span>
    <select id="fDept" class="form-control" onchange="appliquerFiltres()">
      <option value="">Tous</option>
      <option value="DIRECTION"            <?= $filtres['departement']==='DIRECTION'            ?'selected':'' ?>>Direction</option>
      <option value="ADMIN_GENERALE"       <?= $filtres['departement']==='ADMIN_GENERALE'       ?'selected':'' ?>>Admin. Générale</option>
      <option value="ADMIN_FINANCIERE"     <?= $filtres['departement']==='ADMIN_FINANCIERE'     ?'selected':'' ?>>Admin. Financière</option>
      <option value="RECHERCHE_SCIENTIFIQUE" <?= $filtres['departement']==='RECHERCHE_SCIENTIFIQUE'?'selected':'' ?>>Recherche Scientifique</option>
    </select>
  </div>
  <?php endif; ?>

  <!-- Raccourcis période -->
  <div class="filtre-group" style="min-width:auto;">
    <span class="filtre-label">Période rapide</span>
    <div class="periode-shortcuts">
      <button type="button" class="shortcut-btn" onclick="periodeRapide('semaine')">Cette semaine</button>
      <button type="button" class="shortcut-btn" onclick="periodeRapide('mois')">Ce mois</button>
      <button type="button" class="shortcut-btn" onclick="periodeRapide('mois_prec')">Mois préc.</button>
      <?php if (!empty($filtres['date_debut']) || !empty($filtres['date_fin'])): ?>
      <button type="button" class="shortcut-btn" onclick="reinitialiserDates()" style="color:var(--danger);border-color:#fecaca;">
        <i class="fa-solid fa-xmark"></i> Réinitialiser
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Résumé des résultats -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
  <div style="font-size:.82rem;color:var(--text-muted);">
    <strong style="color:var(--text);"><?= $total ?></strong>
    fiche<?= $total > 1 ? 's' : '' ?> trouvée<?= $total > 1 ? 's' : '' ?>
    <?php if (!empty($filtres['date_debut']) || !empty($filtres['date_fin'])): ?>
      <span style="color:var(--blue-600);">
        <?= !empty($filtres['date_debut']) ? ' du ' . format_date($filtres['date_debut']) : '' ?>
        <?= !empty($filtres['date_fin'])   ? ' au ' . format_date($filtres['date_fin'])   : '' ?>
      </span>
    <?php endif; ?>
  </div>
  <?php if ($pagination['nb_pages'] > 1): ?>
  <div style="font-size:.78rem;color:var(--text-muted);margin-left:auto;">
    Page <?= $pagination['page'] ?> / <?= $pagination['nb_pages'] ?>
  </div>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════
     VUE GRILLE
════════════════════════════════════ -->
<div id="vueGrille">
  <?php if (empty($fiches)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fa-solid fa-clipboard-list"></i></div>
    <h3>Aucune fiche trouvée</h3>
    <p>
      <?php if (!empty($filtres['statut'])): ?>
        Il n'y a aucune fiche avec le statut
        <strong><?= e(strtolower($filtres['statut'])) ?></strong>
        pour les filtres sélectionnés.
      <?php else: ?>
        Aucune fiche ne correspond aux filtres sélectionnés.
      <?php endif; ?>
    </p>
    <a href="fiche.php" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> Créer une fiche
    </a>
  </div>

  <?php else: ?>
  <div class="fiches-grid">
    <?php foreach ($fiches as $f):
      $statut_lc  = strtolower($f['statut']);
      $initiales  = mb_strtoupper(
          mb_substr(explode(' ', $f['nom_complet'])[0] ?? 'U', 0, 1) .
          mb_substr(explode(' ', $f['nom_complet'])[1] ?? 'X', 0, 1)
      );
      $nb_act  = (int)($f['nb_activites'] ?? 0);
      $nb_att  = (int)($f['nb_atteintes'] ?? 0);
      $pct     = $nb_act > 0 ? round($nb_att / $nb_act * 100) : 0;
      $date_d  = new DateTime($f['date_fiche']);
      $mois_court = $mois_fr[(int)$date_d->format('n')] ?? '';

      $statut_icons = ['SOUMISE'=>'fa-clock','VALIDEE'=>'fa-circle-check','REJETEE'=>'fa-circle-xmark','BROUILLON'=>'fa-pen-to-square'];
      $statut_icon  = $statut_icons[$f['statut']] ?? 'fa-circle';
    ?>
    <div class="fiche-card <?= $statut_lc ?>">

      <!-- Top -->
      <div class="fiche-card-top">
        <?php if ($peut_voir_tout): ?>
        <div class="fiche-avatar"><?= e($initiales) ?></div>
        <div class="fiche-meta">
          <div class="fiche-nom"><?= e($f['nom_complet']) ?></div>
          <div class="fiche-sub">
            <?= e($f['matricule']) ?> · <?= e($f['groupe']) ?>
          </div>
        </div>
        <?php else: ?>
        <div class="fiche-meta">
          <div class="fiche-nom" style="font-size:.8rem;color:var(--text-muted);">
            <i class="fa-solid fa-calendar-week"></i>
            Semaine <?= $date_d->format('W') ?> · <?= $date_d->format('Y') ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="fiche-date-badge">
          <div class="jour"><?= $date_d->format('d') ?></div>
          <div class="mois"><?= $mois_court ?> <?= $date_d->format('Y') ?></div>
        </div>
      </div>

      <!-- Body -->
      <div class="fiche-card-body">

        <!-- Stats -->
        <div class="fiche-stats">
          <div class="fiche-stat blue">
            <i class="fa-solid fa-list-check"></i>
            <?= $nb_act ?> activité<?= $nb_act > 1 ? 's' : '' ?>
          </div>
          <?php if ($nb_att > 0): ?>
          <div class="fiche-stat green">
            <i class="fa-solid fa-check"></i>
            <?= $nb_att ?> atteinte<?= $nb_att > 1 ? 's' : '' ?>
          </div>
          <?php endif; ?>
          <?php if ($nb_act - $nb_att > 0 && $f['statut'] !== 'BROUILLON'): ?>
          <div class="fiche-stat red">
            <i class="fa-solid fa-xmark"></i>
            <?= $nb_act - $nb_att ?> non atteinte<?= ($nb_act - $nb_att) > 1 ? 's' : '' ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Barre progression -->
        <?php if ($nb_act > 0 && $f['statut'] !== 'BROUILLON'): ?>
        <div class="mini-progress">
          <div class="mini-progress-fill" style="width:<?= $pct ?>%;"></div>
        </div>
        <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:8px;">
          Taux de réalisation : <strong style="color:var(--blue-600);"><?= $pct ?>%</strong>
        </div>
        <?php endif; ?>

        <!-- Commentaire validateur -->
        <?php if (!empty($f['commentaire_validateur'] ?? '')): ?>
        <div class="fiche-commentaire <?= $statut_lc === 'rejetee' ? 'rejet' : 'valid' ?>">
          <i class="fa-solid fa-quote-left" style="font-size:.65rem;opacity:.5;"></i>
          <?= e($f['commentaire_validateur'] ?? '') ?>
        </div>
        <?php endif; ?>

        <!-- Date validation -->
        <?php if (!empty($f['valide_le'])): ?>
        <div style="font-size:.73rem;color:var(--text-muted);">
          <i class="fa-solid fa-user-check"></i>
          <?= $f['statut'] === 'VALIDEE' ? 'Validée' : 'Traitée' ?>
          le <?= format_datetime($f['valide_le']) ?>
        </div>
        <?php endif; ?>

      </div><!-- /body -->

      <!-- Footer -->
      <div class="fiche-card-footer">
        <!-- Statut pill -->
        <div class="fiche-statut-pill p-<?= $statut_lc ?>">
          <i class="fa-solid <?= $statut_icon ?>"></i>
          <?= ucfirst(strtolower($f['statut'])) ?>
        </div>

        <div style="flex:1;"></div>

        <!-- Actions -->
        <div style="display:flex;gap:6px;">
          <a href="fiche.php?date=<?= e($f['date_fiche']) ?>"
             class="btn btn-secondary btn-sm btn-icon" title="Voir la fiche">
            <i class="fa-solid fa-eye"></i>
          </a>

          <?php if ($peut_valider && $f['statut'] === 'SOUMISE'): ?>
          <button type="button"
                  class="btn btn-sm"
                  style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;"
                  onclick="ouvrirModalValider(<?= $f['id'] ?>, '<?= e(addslashes($f['nom_complet'])) ?>', '<?= e($f['date_fiche']) ?>', 'VALIDEE')"
                  title="Valider">
            <i class="fa-solid fa-check"></i>
          </button>
          <button type="button"
                  class="btn btn-sm"
                  style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;"
                  onclick="ouvrirModalValider(<?= $f['id'] ?>, '<?= e(addslashes($f['nom_complet'])) ?>', '<?= e($f['date_fiche']) ?>', 'REJETEE')"
                  title="Rejeter">
            <i class="fa-solid fa-xmark"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /fiche-card -->
    <?php endforeach; ?>
  </div><!-- /fiches-grid -->
  <?php endif; ?>
</div><!-- /vueGrille -->

<!-- ═══════════════════════════════════
     VUE LISTE
════════════════════════════════════ -->
<div id="vueListe" style="display:none;">
  <?php if (empty($fiches)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fa-solid fa-clipboard-list"></i></div>
    <h3>Aucune fiche trouvée</h3>
    <p>Modifiez les filtres ou créez une nouvelle fiche.</p>
    <a href="fiche.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Créer une fiche</a>
  </div>
  <?php else: ?>
  <div class="fiches-list">
    <?php foreach ($fiches as $f):
      $statut_lc = strtolower($f['statut']);
      $nb_act    = (int)($f['nb_activites'] ?? 0);
      $nb_att    = (int)($f['nb_atteintes'] ?? 0);
      $pct       = $nb_act > 0 ? round($nb_att / $nb_act * 100) : 0;
      $initiales = mb_strtoupper(
          mb_substr(explode(' ', $f['nom_complet'])[0] ?? 'U', 0, 1) .
          mb_substr(explode(' ', $f['nom_complet'])[1] ?? 'X', 0, 1)
      );
    ?>
    <div class="fiche-row <?= $statut_lc ?>">
      <!-- Avatar -->
      <?php if ($peut_voir_tout): ?>
      <div class="fiche-avatar" style="width:34px;height:34px;font-size:.75rem;flex-shrink:0;">
        <?= e($initiales) ?>
      </div>
      <?php endif; ?>

      <!-- Nom -->
      <?php if ($peut_voir_tout): ?>
      <div style="min-width:140px;flex:1.2;">
        <div style="font-size:.85rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($f['nom_complet']) ?></div>
        <div style="font-size:.72rem;color:var(--text-muted);"><?= e($f['matricule']) ?></div>
      </div>
      <?php endif; ?>

      <!-- Date -->
      <div style="flex-shrink:0;min-width:90px;">
        <div style="font-size:.83rem;font-weight:700;color:var(--blue-700);"><?= format_date($f['date_fiche']) ?></div>
        <div style="font-size:.72rem;color:var(--text-muted);">
          <?php
            $d = new DateTime($f['date_fiche']);
            $jours = ['Sunday'=>'Dim','Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mer','Thursday'=>'Jeu','Friday'=>'Ven','Saturday'=>'Sam'];
            echo $jours[$d->format('l')] ?? '';
          ?>
        </div>
      </div>

      <!-- Activités -->
      <div style="flex:1;min-width:80px;">
        <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:3px;"><?= $nb_act ?> activité<?= $nb_act>1?'s':'' ?> · <strong><?= $pct ?>%</strong></div>
        <div class="mini-progress" style="height:4px;margin:0;">
          <div class="mini-progress-fill" style="width:<?= $pct ?>%;"></div>
        </div>
      </div>

      <!-- Statut -->
      <div style="flex-shrink:0;">
        <?php
          $sp = ['SOUMISE'=>'p-soumise','VALIDEE'=>'p-validee','REJETEE'=>'p-rejetee','BROUILLON'=>'p-brouillon'];
          $si = ['SOUMISE'=>'fa-clock','VALIDEE'=>'fa-circle-check','REJETEE'=>'fa-circle-xmark','BROUILLON'=>'fa-pen-to-square'];
        ?>
        <div class="fiche-statut-pill <?= $sp[$f['statut']] ?? '' ?>">
          <i class="fa-solid <?= $si[$f['statut']] ?? 'fa-circle' ?>"></i>
          <?= ucfirst(strtolower($f['statut'])) ?>
        </div>
      </div>

      <!-- Actions -->
      <div style="flex-shrink:0;display:flex;gap:5px;">
        <a href="fiche.php?date=<?= e($f['date_fiche']) ?>"
           class="btn btn-secondary btn-sm btn-icon" title="Voir">
          <i class="fa-solid fa-eye"></i>
        </a>
        <?php if ($peut_valider && $f['statut'] === 'SOUMISE'): ?>
        <button type="button" class="btn btn-sm"
                style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;"
                onclick="ouvrirModalValider(<?= $f['id'] ?>, '<?= e(addslashes($f['nom_complet'])) ?>', '<?= e($f['date_fiche']) ?>', 'VALIDEE')">
          <i class="fa-solid fa-check"></i>
        </button>
        <button type="button" class="btn btn-sm"
                style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;"
                onclick="ouvrirModalValider(<?= $f['id'] ?>, '<?= e(addslashes($f['nom_complet'])) ?>', '<?= e($f['date_fiche']) ?>', 'REJETEE')">
          <i class="fa-solid fa-xmark"></i>
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div><!-- /vueListe -->

<!-- ═══════════════════════════════════
     PAGINATION
════════════════════════════════════ -->
<?php if ($pagination['nb_pages'] > 1): ?>
<div style="margin-top:24px;">
  <div class="pager">
    <?php if ($pagination['a_precedente']): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$pagination['page']-1])) ?>">
        <i class="fa-solid fa-chevron-left"></i>
      </a>
    <?php endif; ?>

    <?php
    $debut_p = max(1, $pagination['page'] - 2);
    $fin_p   = min($pagination['nb_pages'], $pagination['page'] + 2);
    if ($debut_p > 1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>1])) ?>">1</a><?php endif;
    if ($debut_p > 2): ?><span class="dots">…</span><?php endif;
    for ($p = $debut_p; $p <= $fin_p; $p++):
      if ($p === $pagination['page']): ?><span class="active"><?= $p ?></span>
      <?php else: ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"><?= $p ?></a>
      <?php endif;
    endfor;
    if ($fin_p < $pagination['nb_pages'] - 1): ?><span class="dots">…</span><?php endif;
    if ($fin_p < $pagination['nb_pages']): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$pagination['nb_pages']])) ?>"><?= $pagination['nb_pages'] ?></a>
    <?php endif; ?>

    <?php if ($pagination['a_suivante']): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$pagination['page']+1])) ?>">
        <i class="fa-solid fa-chevron-right"></i>
      </a>
    <?php endif; ?>
  </div>
  <div style="text-align:center;font-size:.75rem;color:var(--text-muted);margin-top:8px;">
    <?= $total ?> résultat<?= $total>1?'s':'' ?> · <?= $pagination['par_page'] ?> par page
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════
     MODAL VALIDER / REJETER
════════════════════════════════════ -->
<?php if ($peut_valider): ?>
<div class="modal-overlay" id="modalValider">
  <div class="modal modal-valider">
    <div class="modal-header" id="modalValiderHeader">
      <i class="fa-solid fa-circle-check" id="modalValiderIcon" style="color:var(--success);"></i>
      <h3 id="modalValiderTitre">Valider la fiche</h3>
      <button class="modal-close" onclick="document.getElementById('modalValider').classList.remove('open')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="actions_suivi.php">
      <?= csrf_field() ?>
      <input type="hidden" name="fiche_id" id="validerFicheId">
      <input type="hidden" name="action"   id="validerAction">

      <div class="modal-body">
        <div id="validerInfo" style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:16px;font-size:.85rem;">
          <!-- rempli en JS -->
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" id="validerCommentaireLabel">Commentaire (optionnel)</label>
          <textarea name="commentaire" id="validerCommentaire" class="form-control" rows="3"
                    placeholder="Ajouter un commentaire pour l'agent…"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary"
                onclick="document.getElementById('modalValider').classList.remove('open')">
          Annuler
        </button>
        <button type="submit" class="btn" id="validerSubmitBtn">
          Confirmer
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php
$extra_scripts = '<script>
// ── Bascule vue grille / liste ──
function basculerVue(vue) {
  const grille = document.getElementById("vueGrille");
  const liste  = document.getElementById("vueListe");
  const btnG   = document.getElementById("btnGrille");
  const btnL   = document.getElementById("btnListe");

  if (vue === "grille") {
    grille.style.display = "";
    liste.style.display  = "none";
    btnG.classList.add("active");
    btnL.classList.remove("active");
    localStorage.setItem("crict_suivi_vue", "grille");
  } else {
    grille.style.display = "none";
    liste.style.display  = "";
    btnG.classList.remove("active");
    btnL.classList.add("active");
    localStorage.setItem("crict_suivi_vue", "liste");
  }
}

// Restaurer préférence vue
(function() {
  const vue = localStorage.getItem("crict_suivi_vue") || "grille";
  if (vue === "liste") basculerVue("liste");
})();

// ── Filtres ──
function appliquerFiltres() {
  const debut  = document.getElementById("fDateDebut")?.value || "";
  const fin    = document.getElementById("fDateFin")?.value   || "";
  const dept   = document.getElementById("fDept")?.value      || "";
  const statut = new URLSearchParams(window.location.search).get("statut") || "";
  const qs = new URLSearchParams({ statut, date_debut: debut, date_fin: fin, dept, page: 1 });
  // Nettoyer les params vides
  for (const [k, v] of [...qs.entries()]) { if (!v) qs.delete(k); }
  window.location.href = "?" + qs.toString();
}

// ── Raccourcis période ──
function periodeRapide(type) {
  const now    = new Date();
  let debut, fin;

  if (type === "semaine") {
    const day  = now.getDay() || 7;
    const lundi = new Date(now); lundi.setDate(now.getDate() - day + 1);
    const vendr = new Date(lundi); vendr.setDate(lundi.getDate() + 4);
    debut = fmt(lundi); fin = fmt(vendr);
  } else if (type === "mois") {
    debut = fmt(new Date(now.getFullYear(), now.getMonth(), 1));
    fin   = fmt(new Date(now.getFullYear(), now.getMonth() + 1, 0));
  } else if (type === "mois_prec") {
    debut = fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1));
    fin   = fmt(new Date(now.getFullYear(), now.getMonth(), 0));
  }

  const dd = document.getElementById("fDateDebut");
  const df = document.getElementById("fDateFin");
  if (dd) dd.value = debut;
  if (df) df.value = fin;
  appliquerFiltres();
}

function reinitialiserDates() {
  const dd = document.getElementById("fDateDebut");
  const df = document.getElementById("fDateFin");
  if (dd) dd.value = "";
  if (df) df.value = "";
  appliquerFiltres();
}

function fmt(d) {
  return d.getFullYear() + "-" +
    String(d.getMonth()+1).padStart(2,"0") + "-" +
    String(d.getDate()).padStart(2,"0");
}

// ── Modal valider / rejeter ──
function ouvrirModalValider(ficheId, nomAgent, dateFiche, action) {
  document.getElementById("validerFicheId").value = ficheId;
  document.getElementById("validerAction").value  = action;

  const estValid  = action === "VALIDEE";
  const icon      = document.getElementById("modalValiderIcon");
  const titre     = document.getElementById("modalValiderTitre");
  const btn       = document.getElementById("validerSubmitBtn");
  const lbl       = document.getElementById("validerCommentaireLabel");
  const info      = document.getElementById("validerInfo");
  const ta        = document.getElementById("validerCommentaire");

  // Formater date
  const parts = dateFiche.split("-");
  const dateAff = parts[2] + "/" + parts[1] + "/" + parts[0];

  info.innerHTML = "<strong>" + nomAgent + "</strong> · Fiche du <strong>" + dateAff + "</strong>";

  if (estValid) {
    icon.className  = "fa-solid fa-circle-check";
    icon.style.color = "var(--success)";
    titre.textContent = "Valider la fiche";
    btn.className   = "btn btn-success";
    btn.innerHTML   = "<i class=\'fa-solid fa-check\'></i> Valider";
    lbl.textContent = "Commentaire pour l\'agent (optionnel)";
    ta.placeholder  = "Ex: Bon travail, continuez ainsi…";
    ta.required     = false;
  } else {
    icon.className  = "fa-solid fa-circle-xmark";
    icon.style.color = "var(--danger)";
    titre.textContent = "Rejeter la fiche";
    btn.className   = "btn btn-danger";
    btn.innerHTML   = "<i class=\'fa-solid fa-xmark\'></i> Rejeter";
    lbl.textContent = "Motif du rejet (obligatoire)";
    ta.placeholder  = "Ex: Objectifs insuffisamment détaillés…";
    ta.required     = true;
  }

  ta.value = "";
  document.getElementById("modalValider").classList.add("open");
  setTimeout(() => ta.focus(), 150);
}
</script>';

include __DIR__ . '/../../views/layout/footer.php';
?>
