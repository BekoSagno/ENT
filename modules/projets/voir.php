<?php
// modules/projets/voir.php — Tableau de bord d'un projet
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet.php';

session_demarrer();
exiger_permission('PROJETS', 'VOIR');

$user      = user_courant();
$projet_id = (int)($_GET['id'] ?? 0);
if (!$projet_id) { redirect('/modules/projets/index.php'); }

$projet = projet_charger($projet_id);
if (!$projet) { set_flash('error','Projet introuvable.'); redirect('/modules/projets/index.php'); }

$est_chef  = _projet_est_chef($projet_id, (int)$user['id']);
$est_membre = _projet_est_membre($projet_id, (int)$user['id']);
if (!$est_chef && !$est_membre) { http_response_code(403); exit('Accès refusé.'); }

$taches = tache_lister($projet_id, ['statut'=>clean($_GET['ts']??'')]);
$stats  = projet_stats($projet_id);

// ── Actions POST rapides ──
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_verify()) {
    $pa = clean($_POST['pa'] ?? '');

    if ($pa === 'commentaire') {
        commentaire_ajouter((int)$_POST['tid'], clean($_POST['contenu']??''));
        header('Location: voir.php?id='.$projet_id.'#t'.(int)$_POST['tid']); exit;
    }
    if ($pa === 'avancement' && ($est_chef || (int)($_POST['assigne_a']??0)===(int)$user['id'])) {
        tache_avancement((int)$_POST['tid'], (int)$_POST['pct'], clean($_POST['statut_t']??''));
        header('Location: voir.php?id='.$projet_id.'#t'.(int)$_POST['tid']); exit;
    }
    if ($pa === 'statut_projet' && $est_chef) {
        projet_changer_statut($projet_id, clean($_POST['statut']??''));
        header('Location: voir.php?id='.$projet_id); exit;
    }
    if ($pa === 'del_tache' && $est_chef) {
        $r = tache_supprimer((int)$_POST['tid']);
        set_flash($r['succes']?'success':'error', $r['succes']?'Tâche supprimée.':$r['message']);
        header('Location: voir.php?id='.$projet_id); exit;
    }
    if ($pa === 'del_output') {
        output_supprimer((int)$_POST['oid']);
        header('Location: voir.php?id='.$projet_id.'#t'.(int)$_POST['tid']); exit;
    }
}

// ── Configs d'affichage ──
$sc = [
    'PLANIFIE'=>['Planifié', '#fef3c7','#92400e','#f59e0b','fa-hourglass-start'],
    'EN_COURS'=>['En cours', '#dbeafe','#1e40af','#3b82f6','fa-play'],
    'SUSPENDU'=>['Suspendu', '#fce7f3','#9d174d','#ec4899','fa-pause'],
    'TERMINE' =>['Terminé',  '#dcfce7','#166534','#22c55e','fa-circle-check'],
    'ANNULE'  =>['Annulé',   '#f3f4f6','#6b7280','#9ca3af','fa-ban'],
];
$tc = [
    'A_FAIRE'    =>['À faire',     '#f3f4f6','#4b5563','fa-circle'],
    'EN_COURS'   =>['En cours',    '#dbeafe','#1e40af','fa-spinner'],
    'EN_REVISION'=>['En révision', '#fef3c7','#92400e','fa-magnifying-glass'],
    'TERMINEE'   =>['Terminée',    '#dcfce7','#166534','fa-check'],
    'BLOQUEE'    =>['Bloquée',     '#fee2e2','#991b1b','fa-lock'],
    'ANNULEE'    =>['Annulée',     '#f3f4f6','#9ca3af','fa-ban'],
];
$pc = ['CRITIQUE'=>['#fee2e2','#991b1b'],'HAUTE'=>['#ffedd5','#9a3412'],'NORMALE'=>['#dbeafe','#1e40af'],'FAIBLE'=>['#f3f4f6','#6b7280']];
[$p_lbl,$p_bg,$p_co,$p_bord,$p_ico] = $sc[$projet['statut']] ?? $sc['PLANIFIE'];
$pct_g = (int)($stats['avancement_moyen'] ?? 0);

$gantt_data = array_map(fn($t) => [
    'id'=>$t['id'],'code'=>$t['code'],'titre'=>$t['titre'],
    'statut'=>strtolower(str_replace('_','-',$t['statut'])),
    'avancement'=>$t['avancement'],
    'debut'=>$t['date_debut'],'fin'=>$t['date_fin_prevue'],
    'depend'=>$t['depend_de'],'parent'=>$t['parent_id'],
    'assigne'=>$t['assigne_nom'],
], tache_lister_gantt($projet_id));

$page_titre      = e($projet['titre']);
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Projets</a> · '.e($projet['code']);
$module_actif    = 'PROJETS';
$projet_sous_actif = 'TACHES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
/* ── Header projet ── */
.ph{background:var(--surface);border:1.5px solid var(--border);border-left:5px solid <?= $p_bord ?>;border-radius:14px;padding:20px 24px;margin-bottom:20px;}
.ph-top{display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:12px;}
.ph-titre{font-size:1.18rem;font-weight:800;color:var(--text);margin:5px 0 8px;line-height:1.3;}
.ph-meta{display:flex;gap:14px;flex-wrap:wrap;font-size:.78rem;color:var(--text-muted);}
.ph-meta i{color:var(--blue-400);margin-right:4px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.69rem;font-weight:700;}
.gp-track{height:8px;background:#e5e7eb;border-radius:20px;overflow:hidden;margin-top:14px;}
.gp-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,var(--blue-500),var(--accent));transition:width .5s;}
.gp-lbl{display:flex;justify-content:space-between;font-size:.74rem;color:var(--text-muted);margin-top:3px;}

/* ── Mini stats ── */
.mstats{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:20px;}
.msc{background:var(--surface);border:1.5px solid var(--border);border-radius:10px;padding:12px 15px;}
.msc-val{font-size:1.38rem;font-weight:800;line-height:1;}
.msc-lbl{font-size:.71rem;color:var(--text-muted);margin-top:2px;}

/* ── Onglets ── */
.tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:20px;border-bottom:2px solid var(--border);padding-bottom:0;}
.tab{padding:9px 17px;border-radius:8px 8px 0 0;font-size:.82rem;font-weight:600;text-decoration:none;color:var(--text-muted);border:1.5px solid transparent;border-bottom:none;cursor:pointer;background:none;display:inline-flex;align-items:center;gap:6px;transition:all .14s;}
.tab:hover{color:var(--blue-600);background:var(--blue-50);}
.tab.on{color:var(--blue-600);background:var(--surface);border-color:var(--border);border-bottom:2px solid var(--surface);margin-bottom:-2px;}
.tp{display:none;} .tp.on{display:block;}

/* ── Tâche card ── */
.tc-card{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:10px;transition:box-shadow .14s;}
.tc-card:hover{box-shadow:0 4px 18px rgba(13,50,120,.09);}
.tc-card.en-cours{border-left:4px solid #3b82f6;}
.tc-card.en-revision{border-left:4px solid #f59e0b;}
.tc-card.bloquee{border-left:4px solid var(--danger);}
.tc-card.terminee{opacity:.75;}
.tc-hd{display:flex;align-items:center;gap:9px;padding:11px 16px;cursor:pointer;user-select:none;}
.tc-num{width:26px;height:26px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;flex-shrink:0;}
.tc-titre{flex:1;font-size:.87rem;font-weight:700;}
.tc-code{font-size:.67rem;color:var(--text-muted);font-weight:600;}
.tc-bd{display:none;border-top:1px solid var(--border);padding:15px 16px;}
.tc-bd.on{display:block;}
.tc-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px;}
@media(max-width:580px){.tc-grid{grid-template-columns:1fr;}}
.tcf{background:var(--bg);border-radius:8px;padding:9px 11px;}
.tcf-lbl{font-size:.67rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
.tcf-val{font-size:.83rem;font-weight:600;}
.tc-prog{display:flex;align-items:center;gap:8px;margin-bottom:12px;}
.tc-pt{flex:1;height:6px;background:#e5e7eb;border-radius:20px;overflow:hidden;}
.tc-pf{height:100%;border-radius:20px;background:var(--blue-500);}

/* Outputs */
.out-item{display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--bg);border-radius:8px;margin-bottom:5px;}
.oi{width:27px;height:27px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.78rem;flex-shrink:0;}
.oi.f{background:#fee2e2;color:#dc2626;}.oi.i{background:#fce7f3;color:#9d174d;}.oi.l{background:#dbeafe;color:#1d4ed8;}

/* Commentaires */
.com-item{display:flex;gap:9px;margin-bottom:10px;}
.com-av{width:28px;height:28px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:.63rem;font-weight:700;flex-shrink:0;}
.com-bub{background:var(--bg);border-radius:0 9px 9px 9px;padding:8px 11px;flex:1;}
.com-meta{font-size:.68rem;color:var(--text-muted);margin-bottom:3px;}
.com-form{display:flex;gap:7px;margin-top:8px;}
.com-form textarea{flex:1;resize:none;}

/* Sous-tâches */
.st-list{margin-left:26px;border-left:2px solid var(--blue-200);padding-left:10px;margin-top:8px;}
.st-item{display:flex;align-items:center;gap:7px;padding:5px 0;border-bottom:1px solid var(--border);font-size:.81rem;}
.st-item:last-child{border-bottom:none;}

/* Gantt */
#ganttWrap{overflow-x:auto;background:var(--surface);border:1.5px solid var(--border);border-radius:12px;}
.gantt-inner{min-width:800px;padding:14px 16px;}
.gantt-row{display:flex;align-items:center;margin-bottom:6px;min-height:30px;}
.gantt-lbl{width:260px;flex-shrink:0;font-size:.78rem;font-weight:600;padding-right:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:5px;}
.gantt-bars{flex:1;position:relative;height:26px;background:#f8faff;border-radius:5px;overflow:hidden;}
.gantt-bar{position:absolute;height:18px;top:4px;border-radius:5px;display:flex;align-items:center;padding:0 5px;font-size:.63rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;cursor:default;}
.gantt-bar.a-faire{background:#9ca3af;}
.gantt-bar.en-cours{background:#3b82f6;}
.gantt-bar.en-revision{background:#f59e0b;}
.gantt-bar.terminee{background:#22c55e;}
.gantt-bar.bloquee{background:#ef4444;}
.gantt-today{position:absolute;top:0;bottom:0;width:2px;background:var(--danger);opacity:.6;z-index:5;}

/* Membres & Docs */
.mbr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;}
.mbr-card{background:var(--surface);border:1.5px solid var(--border);border-radius:10px;padding:12px 15px;display:flex;gap:10px;align-items:center;}
.mc-av{width:38px;height:38px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;flex-shrink:0;}
.mc-rol{margin-left:auto;padding:2px 8px;border-radius:20px;font-size:.67rem;font-weight:700;flex-shrink:0;}
.mc-rol.chef{background:#dbeafe;color:#1e40af;}.mc-rol.membre{background:#dcfce7;color:#166534;}.mc-rol.observateur{background:#f3f4f6;color:#6b7280;}
.doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:9px;}
.doc-card{background:var(--surface);border:1.5px solid var(--border);border-radius:9px;padding:11px 13px;display:flex;gap:9px;align-items:center;}
.di{width:34px;height:34px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
.di.pdf{background:#fee2e2;color:#dc2626;}.di.word{background:#dbeafe;color:#1d4ed8;}.di.excel{background:#dcfce7;color:#15803d;}.di.other{background:var(--bg);color:var(--text-muted);}

/* Toolbar filtres tâches */
.t-toolbar{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-bottom:15px;}
</style>

<!-- Header projet -->
<div class="ph">
  <div class="ph-top">
    <div style="flex:1;min-width:0;">
      <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:4px;">
        <span style="font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.09em;"><?= e($projet['code']) ?></span>
        <span class="badge" style="background:<?= $p_bg ?>;color:<?= $p_co ?>;"><i class="fa-solid <?= $p_ico ?>"></i> <?= $p_lbl ?></span>
        <?php [$pb,$pc_] = $pc[$projet['priorite']] ?? $pc['NORMALE']; ?>
        <span class="badge" style="background:<?= $pb ?>;color:<?= $pc_ ?>;"><?= $projet['priorite'] ?></span>
        <?php if ((int)($stats['nb_en_retard']??0) > 0): ?>
          <span class="badge" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-triangle-exclamation"></i> Retard</span>
        <?php endif; ?>
      </div>
      <div class="ph-titre"><?= e($projet['titre']) ?></div>
      <div class="ph-meta">
        <span><i class="fa-solid fa-user-tie"></i><?= e($projet['responsable_nom']) ?></span>
        <span><i class="fa-regular fa-calendar"></i><?= format_date($projet['date_debut']) ?> → <?= format_date($projet['date_fin_prevue']) ?></span>
        <?php if (!empty($projet['budget'])): ?>
          <span><i class="fa-solid fa-coins"></i><?= number_format((float)$projet['budget'],0,',',' ') ?> <?= e($projet['devise']) ?></span>
        <?php endif; ?>
        <?php
        $jr = (int)floor((strtotime($projet['date_fin_prevue']) - time()) / 86400);
        if (!in_array($projet['statut'],['TERMINE','ANNULE'])): ?>
          <span style="<?= $jr<0?'color:var(--danger);font-weight:700;':'' ?>">
            <i class="fa-solid fa-clock"></i><?= $jr<0?abs($jr).'j de retard':$jr.'j restants' ?>
          </span>
        <?php endif; ?>
      </div>
      <?php if (!empty($projet['description'])): ?>
        <div style="margin-top:8px;font-size:.83rem;color:var(--text-muted);line-height:1.55;"><?= nl2br(e($projet['description'])) ?></div>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;flex-shrink:0;">
      <?php if ($est_chef): ?>
        <a href="tache_form.php?projet_id=<?= $projet_id ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Tâche</a>
        <a href="creer.php?id=<?= $projet_id ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier"><i class="fa-solid fa-pen"></i></a>
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('mStat').classList.add('open')" title="Statut"><i class="fa-solid fa-exchange-alt"></i></button>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary btn-sm btn-icon" title="Retour"><i class="fa-solid fa-arrow-left"></i></a>
    </div>
  </div>
  <div class="gp-track"><div class="gp-fill" style="width:<?= $pct_g ?>%;"></div></div>
  <div class="gp-lbl"><span>Avancement global</span><strong style="color:var(--blue-600);"><?= $pct_g ?>%</strong></div>
</div>

<!-- Mini stats -->
<div class="mstats">
<?php
$ms=[
  ['val'=>$stats['nb_total']??0,    'lbl'=>'Tâches',    'c'=>'var(--blue-600)'],
  ['val'=>$stats['nb_en_cours']??0, 'lbl'=>'En cours',  'c'=>'#3b82f6'],
  ['val'=>$stats['nb_terminees']??0,'lbl'=>'Terminées',  'c'=>'#22c55e'],
  ['val'=>$stats['nb_bloquees']??0, 'lbl'=>'Bloquées',   'c'=>'#ef4444'],
  ['val'=>$stats['nb_en_retard']??0,'lbl'=>'En retard',  'c'=>'#f59e0b'],
  ['val'=>count($projet['membres']),'lbl'=>'Membres',   'c'=>'var(--accent)'],
];
foreach($ms as $s): ?>
<div class="msc"><div class="msc-val" style="color:<?= $s['c'] ?>;"><?= $s['val'] ?></div><div class="msc-lbl"><?= $s['lbl'] ?></div></div>
<?php endforeach; ?>
</div>

<!-- Onglets -->
<div class="tabs">
  <button class="tab on" onclick="sw('taches',this)"><i class="fa-solid fa-list-check"></i> Tâches</button>
  <button class="tab" onclick="sw('gantt',this)"><i class="fa-solid fa-chart-gantt"></i> Gantt</button>
  <button class="tab" onclick="sw('membres',this)"><i class="fa-solid fa-users"></i> Membres (<?= count($projet['membres']) ?>)</button>
  <button class="tab" onclick="sw('docs',this)"><i class="fa-solid fa-folder-open"></i> Documents (<?= count($projet['documents']) ?>)</button>
  <?php if (!empty($projet['tdr'])||!empty($projet['cahier_charges'])): ?>
    <button class="tab" onclick="sw('tdr',this)"><i class="fa-solid fa-file-lines"></i> TDR & CDC</button>
  <?php endif; ?>
</div>

<!-- ═══ TAB TÂCHES ═══ -->
<div class="tp on" id="tp-taches">
  <div class="t-toolbar">
    <?php foreach ($tc as $k=>$v): [$tl,$tbg,$tco,$tico]=$v; $act=clean($_GET['ts']??'')===$k; ?>
      <a href="?id=<?= $projet_id ?>&ts=<?= $k ?>"
         style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;border:1.5px solid <?= $act?$tco:'var(--border)' ?>;background:<?= $act?$tbg:'var(--surface)' ?>;color:<?= $act?$tco:'var(--text-muted)' ?>;font-size:.74rem;font-weight:600;text-decoration:none;">
        <i class="fa-solid <?= $tico ?>"></i> <?= $tl ?>
      </a>
    <?php endforeach; ?>
    <?php if (!empty($_GET['ts'])): ?>
      <a href="?id=<?= $projet_id ?>" style="font-size:.74rem;color:var(--danger);text-decoration:none;padding:4px 8px;border:1px solid #fecaca;border-radius:6px;background:#fff5f5;"><i class="fa-solid fa-xmark"></i></a>
    <?php endif; ?>
    <?php if ($est_chef): ?>
      <a href="tache_form.php?projet_id=<?= $projet_id ?>" class="btn btn-primary btn-sm" style="margin-left:auto;"><i class="fa-solid fa-plus"></i> Nouvelle tâche</a>
    <?php endif; ?>
  </div>

  <?php if (empty($taches)): ?>
  <div style="text-align:center;padding:45px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
    <i class="fa-solid fa-clipboard-list" style="font-size:2rem;color:var(--blue-300);display:block;margin-bottom:10px;"></i>
    <div style="font-size:.87rem;color:var(--text-muted);">Aucune tâche trouvée.</div>
    <?php if ($est_chef): ?>
      <a href="tache_form.php?projet_id=<?= $projet_id ?>" class="btn btn-primary" style="margin-top:14px;"><i class="fa-solid fa-plus"></i> Créer la première tâche</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <?php foreach ($taches as $idx => $t):
    [$tl,$tbg,$tco,$tico] = $tc[$t['statut']] ?? $tc['A_FAIRE'];
    [$tpb,$tpc_] = $pc[$t['priorite']] ?? $pc['NORMALE'];
    $pct_t  = (int)$t['avancement'];
    $cls_t  = strtolower(str_replace('_','-',$t['statut']));
    $tard_t = !empty($t['date_fin_prevue']) && $t['date_fin_prevue'] < date('Y-m-d') && !in_array($t['statut'],['TERMINEE','ANNULEE']);
  ?>
  <div class="tc-card <?= $cls_t ?>" id="t<?= $t['id'] ?>">
    <div class="tc-hd" onclick="tog(<?= $t['id'] ?>)">
      <div class="tc-num"><?= $idx+1 ?></div>
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:5px;"><span class="tc-code"><?= e($t['code']) ?></span><?php if(!empty($t['depend_titre'])): ?><span style="font-size:.63rem;color:var(--text-muted);"><i class="fa-solid fa-link"></i> <?= e($t['depend_titre']) ?></span><?php endif; ?></div>
        <div class="tc-titre"><?= e($t['titre']) ?></div>
      </div>
      <div style="display:flex;gap:5px;flex-wrap:wrap;flex-shrink:0;">
        <span class="badge" style="background:<?= $tbg ?>;color:<?= $tco ?>;"><i class="fa-solid <?= $tico ?>"></i> <?= $tl ?></span>
        <?php if ($tard_t): ?><span class="badge" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-triangle-exclamation"></i> Retard</span><?php endif; ?>
        <span class="badge" style="background:<?= $tpb ?>;color:<?= $tpc_ ?>;"><?= $t['priorite'] ?></span>
        <?php if ((int)$t['nb_outputs']>0): ?><span class="badge" style="background:#dcfce7;color:#166534;"><i class="fa-solid fa-box"></i> <?= $t['nb_outputs'] ?></span><?php endif; ?>
        <?php if ((int)$t['nb_commentaires']>0): ?><span class="badge" style="background:#f3f4f6;color:#6b7280;"><i class="fa-solid fa-comment"></i> <?= $t['nb_commentaires'] ?></span><?php endif; ?>
      </div>
      <i class="fa-solid fa-chevron-down" id="chv<?= $t['id'] ?>" style="color:var(--text-muted);font-size:.73rem;transition:transform .2s;margin-left:5px;flex-shrink:0;"></i>
    </div>

    <div class="tc-bd" id="tb<?= $t['id'] ?>">
      <!-- Barre avancement -->
      <div class="tc-prog">
        <div class="tc-pt"><div class="tc-pf" style="width:<?= $pct_t ?>%;"></div></div>
        <span style="font-size:.74rem;font-weight:700;color:var(--blue-600);"><?= $pct_t ?>%</span>
      </div>

      <!-- Détails -->
      <div class="tc-grid">
        <div class="tcf"><div class="tcf-lbl"><i class="fa-solid fa-user"></i> Assigné à</div>
          <div class="tcf-val"><?= !empty($t['assigne_nom'])?e($t['assigne_nom']):'<em style="color:var(--text-muted)">Non assigné</em>' ?></div></div>
        <div class="tcf"><div class="tcf-lbl"><i class="fa-regular fa-calendar"></i> Dates</div>
          <div class="tcf-val"><?= !empty($t['date_debut'])?format_date($t['date_debut']):'—' ?><?= !empty($t['date_fin_prevue'])?' → <strong style="color:'.($tard_t?'var(--danger)':'inherit').'">'.format_date($t['date_fin_prevue']).'</strong>':'' ?></div></div>
        <div class="tcf"><div class="tcf-lbl"><i class="fa-solid fa-link"></i> Dépendance</div>
          <div class="tcf-val"><?= $t['dependance_type']==='INDEPENDANTE'?'<em style="color:var(--text-muted)">Indépendante</em>':e(($t['dependance_type']==='APRES_FIN'?'Après fin : ':'Après début : ').($t['depend_titre']??'—')) ?></div></div>
        <?php if (!empty($t['outputs_attendus'])): ?>
        <div class="tcf" style="grid-column:1/-1;">
          <div class="tcf-lbl"><i class="fa-solid fa-box-open"></i> Livrables attendus</div>
          <div class="tcf-val"><?= nl2br(e($t['outputs_attendus'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($t['description'])): ?>
        <div class="tcf" style="grid-column:1/-1;">
          <div class="tcf-lbl"><i class="fa-solid fa-align-left"></i> Description</div>
          <div class="tcf-val" style="white-space:pre-wrap;"><?= e($t['description']) ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Sous-tâches -->
      <?php if (!empty($t['sous_taches'])): ?>
      <div class="st-list" style="margin-bottom:12px;">
        <div style="font-size:.69rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px;">Sous-tâches (<?= count($t['sous_taches']) ?>)</div>
        <?php foreach ($t['sous_taches'] as $st): [$sl,$sbg,$sco,$sico]=$tc[$st['statut']]??$tc['A_FAIRE']; ?>
        <div class="st-item">
          <span class="badge" style="background:<?= $sbg ?>;color:<?= $sco ?>;font-size:.62rem;"><i class="fa-solid <?= $sico ?>"></i></span>
          <span style="flex:1;"><?= e($st['titre']) ?></span>
          <span style="font-size:.71rem;color:var(--text-muted);"><?= !empty($st['assigne_nom'])?e($st['assigne_nom']):'—' ?></span>
          <strong style="font-size:.71rem;color:var(--blue-600);"><?= $st['avancement'] ?>%</strong>
          <?php if ($est_chef): ?><a href="tache_form.php?id=<?= $st['id'] ?>&projet_id=<?= $projet_id ?>" class="btn btn-secondary btn-sm btn-icon" style="width:21px;height:21px;font-size:.62rem;"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($est_chef): ?>
          <a href="tache_form.php?projet_id=<?= $projet_id ?>&parent_id=<?= $t['id'] ?>" style="font-size:.74rem;color:var(--blue-500);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-top:5px;"><i class="fa-solid fa-plus"></i> Ajouter une sous-tâche</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Outputs déposés -->
      <?php
      $db = get_db();
      $qo = $db->prepare("SELECT o.*,CONCAT(u.prenoms,' ',u.nom) AS dnom FROM projets_taches_outputs o LEFT JOIN utilisateurs u ON u.id=o.depose_par WHERE o.tache_id=? ORDER BY o.cree_le");
      $qo->execute([$t['id']]);
      $outs = $qo->fetchAll();
      if (!empty($outs)): ?>
      <div style="margin-bottom:12px;">
        <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px;"><i class="fa-solid fa-box"></i> Outputs déposés (<?= count($outs) ?>)</div>
        <?php foreach ($outs as $o): $oc=$o['type']==='IMAGE'?'i':($o['type']==='LIEN'?'l':'f'); $oico=$o['type']==='IMAGE'?'fa-image':($o['type']==='LIEN'?'fa-link':'fa-file'); ?>
        <div class="out-item">
          <div class="oi <?= $oc ?>"><i class="fa-solid <?= $oico ?>"></i></div>
          <div style="flex:1;min-width:0;"><div style="font-size:.81rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($o['libelle']?:$o['nom_original']?:$o['url']?:'') ?></div>
          <div style="font-size:.68rem;color:var(--text-muted);"><?= e($o['dnom']??'') ?> · <?= format_datetime($o['cree_le']) ?><?= $o['taille']?' · '.round($o['taille']/1024,1).' Ko':'' ?></div></div>
          <?php if ($o['type']==='LIEN'): ?>
            <a href="<?= e($o['url']) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-icon"><i class="fa-solid fa-external-link"></i></a>
          <?php else: ?>
            <a href="upload.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-secondary btn-sm btn-icon"><i class="fa-solid fa-download"></i></a>
          <?php endif; ?>
          <?php if ($est_chef || (int)($o['depose_par']??0)===(int)$user['id']): ?>
          <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="pa"  value="del_output">
            <input type="hidden" name="oid" value="<?= $o['id'] ?>">
            <input type="hidden" name="tid" value="<?= $t['id'] ?>">
            <button type="submit" style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;border-radius:6px;width:26px;height:26px;cursor:pointer;font-size:.72rem;padding:0;"><i class="fa-solid fa-trash"></i></button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Commentaires -->
      <?php
      $qc = $db->prepare("SELECT c.*,CONCAT(u.prenoms,' ',u.nom) AS anom FROM projets_taches_commentaires c LEFT JOIN utilisateurs u ON u.id=c.auteur_id WHERE c.tache_id=? ORDER BY c.cree_le LIMIT 20");
      $qc->execute([$t['id']]);
      $coms = $qc->fetchAll();
      ?>
      <div style="margin-top:2px;">
        <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:7px;"><i class="fa-solid fa-comments"></i> Commentaires</div>
        <?php foreach ($coms as $c): ?>
        <div class="com-item">
          <div class="com-av"><?= strtoupper(mb_substr($c['anom'],0,2)) ?></div>
          <div class="com-bub">
            <div class="com-meta"><strong><?= e($c['anom']) ?></strong> · <?= format_datetime($c['cree_le']) ?></div>
            <div style="font-size:.82rem;"><?= nl2br(e($c['contenu'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <form method="POST" action="" class="com-form">
          <?= csrf_field() ?>
          <input type="hidden" name="pa"  value="commentaire">
          <input type="hidden" name="tid" value="<?= $t['id'] ?>">
          <textarea name="contenu" class="form-control" rows="2" placeholder="Ajouter un commentaire…" required></textarea>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:6px;margin-top:12px;flex-wrap:wrap;">
        <a href="tache_form.php?id=<?= $t['id'] ?>&projet_id=<?= $projet_id ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>
        <?php if ($est_chef): ?>
          <a href="tache_form.php?projet_id=<?= $projet_id ?>&parent_id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-sitemap"></i> Sous-tâche</a>
          <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Supprimer la tâche ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="pa"  value="del_tache">
            <input type="hidden" name="tid" value="<?= $t['id'] ?>">
            <button type="submit" style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;border-radius:8px;padding:7px 12px;cursor:pointer;font-size:.81rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;"><i class="fa-solid fa-trash"></i> Supprimer</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div><!-- /tab-taches -->

<!-- ═══ TAB GANTT ═══ -->
<div class="tp" id="tp-gantt">
  <div id="ganttWrap"><div class="gantt-inner" id="ganttInner">
    <div style="text-align:center;padding:40px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Chargement…</div>
  </div></div>
</div>

<!-- ═══ TAB MEMBRES ═══ -->
<div class="tp" id="tp-membres">
  <div class="mbr-grid">
    <?php foreach ($projet['membres'] as $m): ?>
    <div class="mbr-card">
      <div class="mc-av"><?= strtoupper(mb_substr($m['nom_complet'],0,1).mb_substr(explode(' ',$m['nom_complet'])[1]??'',0,1)) ?></div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:.87rem;font-weight:700;"><?= e($m['nom_complet']) ?></div>
        <div style="font-size:.71rem;color:var(--text-muted);"><?= e($m['matricule']) ?> · <?= e($m['groupe']) ?></div>
      </div>
      <span class="mc-rol <?= strtolower($m['role']) ?>"><?= $m['role'] ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ═══ TAB DOCUMENTS ═══ -->
<div class="tp" id="tp-docs">
  <?php if (empty($projet['documents'])): ?>
    <div style="text-align:center;padding:30px;color:var(--text-muted);">Aucun document. <a href="creer.php?id=<?= $projet_id ?>" style="color:var(--blue-500);">Ajouter depuis la fiche projet.</a></div>
  <?php else:
    $cats=['TDR'=>'TDR','CAHIER_CHARGES'=>'Cahier des charges','BUDGET'=>'Budget','RAPPORT'=>'Rapport','AUTRE'=>'Autre'];
    $dbc=[];
    foreach($projet['documents'] as $d){$dbc[$d['categorie']][]=$d;}
    foreach($dbc as $cat=>$docs): ?>
    <div style="margin-bottom:16px;">
      <div style="font-size:.73rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:7px;"><i class="fa-solid fa-folder" style="color:var(--blue-400);"></i> <?= $cats[$cat]??$cat ?></div>
      <div class="doc-grid">
        <?php foreach($docs as $d):
          $dc=in_array($d['extension'],['doc','docx'])?'word':(in_array($d['extension'],['xls','xlsx'])?'excel':($d['extension']==='pdf'?'pdf':'other'));
          $dico=in_array($d['extension'],['doc','docx'])?'fa-file-word':(in_array($d['extension'],['xls','xlsx'])?'fa-file-excel':($d['extension']==='pdf'?'fa-file-pdf':'fa-file'));
        ?>
        <div class="doc-card">
          <div class="di <?= $dc ?>"><i class="fa-solid <?= $dico ?>"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.81rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($d['libelle']?:$d['nom_original']) ?></div>
            <div style="font-size:.68rem;color:var(--text-muted);"><?= e($d['deposant']??'') ?> · <?= round($d['taille']/1024,1) ?> Ko</div>
          </div>
          <a href="upload.php?id=<?= $d['id'] ?>&type=projet" target="_blank" class="btn btn-secondary btn-sm btn-icon"><i class="fa-solid fa-download"></i></a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ═══ TAB TDR / CDC ═══ -->
<?php if (!empty($projet['tdr'])||!empty($projet['cahier_charges'])): ?>
<div class="tp" id="tp-tdr">
  <?php if (!empty($projet['tdr'])): ?>
  <div style="background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:20px;margin-bottom:14px;">
    <h3 style="font-size:.88rem;font-weight:700;color:var(--blue-700);margin-bottom:10px;"><i class="fa-solid fa-file-alt"></i> Termes de Référence (TDR)</h3>
    <pre style="font-family:var(--font);font-size:.83rem;white-space:pre-wrap;line-height:1.7;color:var(--text);"><?= e($projet['tdr']) ?></pre>
  </div>
  <?php endif; ?>
  <?php if (!empty($projet['cahier_charges'])): ?>
  <div style="background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:20px;">
    <h3 style="font-size:.88rem;font-weight:700;color:var(--blue-700);margin-bottom:10px;"><i class="fa-solid fa-book"></i> Cahier des charges</h3>
    <pre style="font-family:var(--font);font-size:.83rem;white-space:pre-wrap;line-height:1.7;color:var(--text);"><?= e($projet['cahier_charges']) ?></pre>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal statut projet -->
<?php if ($est_chef): ?>
<div class="modal-overlay" id="mStat">
  <div class="modal" style="max-width:360px;">
    <div class="modal-header">
      <i class="fa-solid fa-exchange-alt" style="color:var(--blue-500);"></i>
      <h3>Changer le statut</h3>
      <button class="modal-close" onclick="document.getElementById('mStat').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="pa" value="statut_projet">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nouveau statut</label>
          <select name="statut" class="form-control">
            <?php foreach(['PLANIFIE'=>'Planifié','EN_COURS'=>'En cours','SUSPENDU'=>'Suspendu','TERMINE'=>'Terminé','ANNULE'=>'Annulé'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $projet['statut']===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('mStat').classList.remove('open')">Annuler</button>
        <button type="submit" class="btn btn-primary">Confirmer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php
$extra_scripts = '<script>
// ── Onglets ──
function sw(id,btn){
  document.querySelectorAll(".tp").forEach(p=>p.classList.remove("on"));
  document.querySelectorAll(".tab").forEach(b=>b.classList.remove("on"));
  document.getElementById("tp-"+id).classList.add("on");
  btn.classList.add("on");
  if(id==="gantt") renderGantt();
}
// ── Accordion tâches ──
function tog(id){
  const bd=document.getElementById("tb"+id);
  const chv=document.getElementById("chv"+id);
  const op=bd.classList.toggle("on");
  if(chv) chv.style.transform=op?"rotate(-180deg)":"";
}
// Ouvrir l\'accordéon depuis l\'ancre URL
(()=>{
  const h=location.hash;
  if(h&&h.startsWith("#t")){
    const id=h.slice(2);
    const el=document.getElementById("t"+id);
    if(el){tog(id);setTimeout(()=>el.scrollIntoView({behavior:"smooth",block:"center"}),100);}
  }
})();
// ── GANTT ──
const GANTT='.json_encode($gantt_data).';
let ganttDone=false;
function renderGantt(){
  if(ganttDone) return; ganttDone=true;
  const rows=GANTT.filter(t=>!t.parent&&t.debut&&t.fin);
  if(!rows.length){document.getElementById("ganttInner").innerHTML="<div style=\'text-align:center;padding:40px;color:var(--text-muted);\'>Aucune tâche planifiée avec des dates.</div>";return;}
  const ds=rows.flatMap(t=>[new Date(t.debut),new Date(t.fin)]);
  let minD=new Date(Math.min(...ds.map(d=>d.getTime())));
  let maxD=new Date(Math.max(...ds.map(d=>d.getTime())));
  minD.setDate(minD.getDate()-2); maxD.setDate(maxD.getDate()+3);
  const span=maxD-minD;
  const pct=d=>Math.max(0,Math.min(100,((new Date(d)-minD)/span*100)));
  const wid=(a,b)=>Math.max(0.5,((new Date(b)-new Date(a))/span*100));
  const today=new Date();
  const todayP=pct(today);
  const mois=["Jan","Fév","Mar","Avr","Mai","Jun","Jul","Aoû","Sep","Oct","Nov","Déc"];
  let heads=""; const cur=new Date(minD);
  while(cur<=maxD){heads+=`<div style="position:absolute;left:${pct(cur)}%;font-size:.61rem;color:var(--text-muted);font-weight:700;">${mois[cur.getMonth()]} ${cur.getFullYear()}</div>`;cur.setMonth(cur.getMonth()+1);}
  const todayLine=todayP>=0&&todayP<=100?`<div class="gantt-today" style="left:${todayP}%;" title="Aujourd\'hui"></div>`:"";
  const rowsHtml=rows.map(t=>{
    const l=pct(t.debut),w=wid(t.debut,t.fin);
    const lbl=w>7?(t.code+" "+t.titre).slice(0,32):"";
    return `<div class="gantt-row">
      <div class="gantt-lbl" title="${t.titre}">
        <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--blue-400);flex-shrink:0;"></span>
        ${t.code} ${t.titre.slice(0,26)}${t.titre.length>26?"…":""}
      </div>
      <div class="gantt-bars" style="position:relative;">
        ${todayLine}
        <div class="gantt-bar ${t.statut}" style="left:${l}%;width:${w}%;" title="${t.titre} ${t.debut}→${t.fin} | ${t.avancement}%">${lbl}</div>
      </div>
    </div>`;
  }).join("");
  const leg=["a-faire","en-cours","terminee","bloquee"].map(s=>`<span style="display:flex;align-items:center;gap:4px;font-size:.71rem;"><span class="gantt-bar ${s}" style="position:static;width:20px;height:12px;border-radius:3px;"></span>${s.replace("-"," ")}</span>`).join("");
  document.getElementById("ganttInner").innerHTML=`<div style="position:relative;height:22px;margin-left:260px;margin-bottom:10px;">${heads}</div>${rowsHtml}<div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">${ leg}<span style="display:flex;align-items:center;gap:4px;font-size:.71rem;"><span style="width:2px;height:14px;background:var(--danger);border-radius:1px;display:inline-block;"></span>Aujourd\'hui</span></div>`;
}
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
