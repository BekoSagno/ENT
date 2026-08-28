<?php
// modules/subventions/index.php — Tableau de bord Subventions
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/subvention.php';
require_once __DIR__ . '/../../models/depense.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'VOIR');

$peut_creer    = a_permission('SUBVENTIONS', 'CREER');
$peut_executer = a_permission('SUBVENTIONS', 'EXECUTER');
$docs_manquants = depense_documents_fournisseur_manquants();

$f = [
    'statut' => clean($_GET['statut'] ?? ''),
    'annee'  => clean($_GET['annee']  ?? ''),
    'q'      => clean($_GET['q']      ?? ''),
];
$par_page = 12;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $par_page;
$result   = caisse_lister($f, $par_page, $offset);
$caisses  = $result['data'];
$total    = $result['total'];
$pager    = paginer($total, $par_page, $page);
$cpt      = caisse_compteurs();
$stats_g  = caisse_stats_globales();
$stats_d  = caisse_stats_depenses();
$par_cat  = caisse_repartition_par_categorie();
$recents  = caisse_dossiers_recents_global(6);

$sc_cfg = [
    'active'   => ['Active',   '#dcfce7','#166534','#22c55e','fa-circle-play'],
    'epuisee'  => ['Épuisée',  '#fee2e2','#991b1b','#ef4444','fa-circle-xmark'],
    'cloturee' => ['Clôturée', '#f3f4f6','#6b7280','#9ca3af','fa-lock'],
    'suspendue'=> ['Suspendue','#fef3c7','#92400e','#f59e0b','fa-pause'],
];

$annees = [];
for ($y = date('Y'); $y >= date('Y') - 5; $y--) $annees[] = $y;

$page_titre      = 'Subventions';
$page_breadcrumb = 'Gestion des Subventions';
$module_actif    = 'SUBVENTIONS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
/* KPI */
.kpi-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:24px;}
.kpi{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:18px 20px;transition:box-shadow .15s;cursor:pointer;}
.kpi:hover{box-shadow:var(--shadow);}
.kpi-val{font-size:1.5rem;font-weight:800;line-height:1.1;margin-bottom:4px;}
.kpi-lbl{font-size:.74rem;color:var(--text-muted);}
.kpi-sub{font-size:.72rem;margin-top:5px;font-weight:600;}

/* Grille caisses */
.caisses-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;margin-bottom:24px;}
.cc{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;transition:box-shadow .2s,transform .15s;}
.cc:hover{box-shadow:0 6px 22px rgba(13,50,120,.1);transform:translateY(-2px);}
.cc-top-bar{height:4px;}
.cc-body{padding:16px 18px;}
.cc-code{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.09em;margin-bottom:4px;}
.cc-titre{font-size:.93rem;font-weight:800;color:var(--text);margin-bottom:10px;line-height:1.3;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.69rem;font-weight:700;}
.prog{height:7px;background:#e5e7eb;border-radius:20px;overflow:hidden;margin:9px 0 4px;}
.prog-f{height:100%;border-radius:20px;transition:width .4s;}
.cc-montants{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px;}
.cm-block{background:var(--bg);border-radius:8px;padding:9px 11px;}
.cm-lbl{font-size:.67rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;}
.cm-val{font-size:.83rem;font-weight:800;}
.cc-footer{padding:10px 18px;border-top:1px solid var(--border);background:var(--bg);display:flex;align-items:center;gap:7px;}

/* Filtres */
.filtres-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:13px 18px;margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;}
.fg label{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;}

/* Pagination */
.pager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:18px;}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--text-muted);text-decoration:none;transition:all .12s;}
.pager a:hover{border-color:var(--blue-400);color:var(--blue-600);}
.pager span.cur{background:var(--blue-600);color:#fff;border-color:var(--blue-600);}
.pager .dots{border:none;background:none;}
</style>

<!-- En-tête -->
<div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap;">
  <div style="flex:1;font-size:.8rem;color:var(--text-muted);">
    Suivi des caisses de subvention et de leur consommation par le workflow DAF.
  </div>
  <?php if ($peut_creer): ?>
    <a href="caisse_form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle caisse</a>
  <?php endif; ?>
</div>

<?php if (!empty($docs_manquants)): ?>
<div style="background:#fff7ed;border:1.5px solid #fed7aa;border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
  <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;font-size:1.2rem;"></i>
  <div style="flex:1;min-width:200px;font-size:.88rem;color:#9a3412;font-weight:700;">
    <?= count($docs_manquants) ?> dépense(s) attendent leurs documents fournisseur.
  </div>
  <a href="depenses.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-cloud-arrow-up"></i> Compléter maintenant</a>
</div>
<?php endif; ?>

<!-- KPI globaux -->
<div class="kpi-row">
  <?php
  $taux_g = (float)($stats_g['budget_total']??0) > 0
          ? round((float)($stats_g['consomme_total']??0)/(float)($stats_g['budget_total']??1)*100,1) : 0;
  $kpis = [
    ['val'=>sub_fmt((float)($stats_g['budget_total']??0)),       'lbl'=>'Budget total alloué',     'sub'=>(int)($stats_g['nb_caisses']??0).' caisse(s)',  'c'=>'var(--blue-600)','st'=>''],
    ['val'=>sub_fmt((float)($stats_g['consomme_total']??0)),     'lbl'=>'Consommé (exécuté)',      'sub'=>$taux_g.'% du budget',                          'c'=>'#ef4444',        'st'=>''],
    ['val'=>sub_fmt((float)($stats_g['disponible_total']??0)),   'lbl'=>'Solde disponible',        'sub'=>(int)($stats_g['nb_actives']??0).' active(s)',  'c'=>'#22c55e',        'st'=>'active'],
    ['val'=>(int)($stats_d['nb_operations']??0).' opération(s)', 'lbl'=>'Opérations exécutées', 'sub'=>sub_fmt((float)($stats_d['montant_operations']??0)),'c'=>'var(--accent)', 'st'=>''],
  ];
  foreach ($kpis as $k):
    $qs = $k['st'] ? '?statut='.$k['st'] : '';
  ?>
  <div class="kpi" <?= $k['st']?"onclick=\"location.href='$qs'\"":'' ?>>
    <div class="kpi-val" style="color:<?= $k['c'] ?>;"><?= $k['val'] ?></div>
    <div class="kpi-lbl"><?= $k['lbl'] ?></div>
    <div class="kpi-sub" style="color:var(--text-muted);"><?= $k['sub'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Graphiques rapides -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

  <!-- Répartition par catégorie -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-chart-pie" style="color:var(--blue-500);"></i>
      <h2>Consommation par catégorie</h2>
    </div>
    <div class="card-body" style="padding:14px 20px;">
      <?php if (empty($par_cat)): ?>
        <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:.83rem;">Aucun dossier exécuté.</div>
      <?php else:
        $total_cat = array_sum(array_column($par_cat,'total'));
        foreach ($par_cat as $cat):
          $pct = $total_cat > 0 ? round($cat['total']/$total_cat*100) : 0;
      ?>
      <div style="margin-bottom:10px;">
        <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:3px;">
          <span><strong><?= e($cat['code_categorie']) ?></strong> — <?= e(mb_strimwidth($cat['libelle'],0,35,'…')) ?></span>
          <span style="font-weight:700;color:var(--blue-600);"><?= $pct ?>%</span>
        </div>
        <div style="background:#e5e7eb;border-radius:20px;height:6px;overflow:hidden;">
          <div style="width:<?= $pct ?>%;height:100%;border-radius:20px;background:var(--blue-500);"></div>
        </div>
        <div style="font-size:.7rem;color:var(--text-muted);margin-top:2px;">
          <?= sub_fmt((float)$cat['total']) ?> — <?= (int)$cat['nb'] ?> dossier<?= $cat['nb']>1?'s':'' ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Derniers dossiers exécutés -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-clock-rotate-left" style="color:var(--blue-500);"></i>
      <h2>Dernières exécutions</h2>
      <a href="<?= APP_URL ?>/modules/marches/index.php?statut=execute" class="btn btn-secondary btn-sm" style="margin-left:auto;">Voir tout</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($recents)): ?>
        <div style="text-align:center;padding:24px;color:var(--text-muted);font-size:.83rem;">Aucune exécution.</div>
      <?php else: ?>
        <?php foreach ($recents as $r): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:11px 18px;border-bottom:1px solid var(--border);">
          <div style="width:32px;height:32px;border-radius:7px;background:var(--blue-50);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;flex-shrink:0;">
            <?= e($r['code_categorie']??'—') ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.81rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($r['libelle']??'—') ?></div>
            <div style="font-size:.69rem;color:var(--text-muted);">
              <?php if (!empty($r['origine_lbl'])): ?><span style="background:var(--blue-50);color:var(--blue-600);padding:1px 6px;border-radius:10px;font-weight:700;"><?= e($r['origine_lbl']) ?></span> · <?php endif; ?>
              <?= e($r['numero_dossier']??'') ?> · <?= e($r['code_caisse']??'') ?>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:.83rem;font-weight:800;"><?= sub_fmt((float)($r['montant_ttc']??0)) ?></div>
            <div style="font-size:.68rem;color:var(--text-muted);"><?= $r['date_execution']?format_date(substr($r['date_execution'],0,10)):'—' ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Filtres caisses -->
<div class="filtres-bar">
  <div class="fg" style="flex:2;min-width:160px;">
    <label><i class="fa-solid fa-magnifying-glass"></i> Recherche</label>
    <input type="text" id="fq" class="form-control" placeholder="Libellé, code caisse…" value="<?= e($f['q']) ?>">
  </div>
  <div class="fg">
    <label>Statut</label>
    <select id="fstatut" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($sc_cfg as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['statut']===$k?'selected':'' ?>><?= $v[0] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Année</label>
    <select id="fannee" class="form-control">
      <option value="">Toutes</option>
      <?php foreach ($annees as $y): ?>
        <option value="<?= $y ?>" <?= $f['annee']==(string)$y?'selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>&nbsp;</label>
    <div style="display:flex;gap:6px;">
      <button class="btn btn-primary btn-sm" onclick="appF()"><i class="fa-solid fa-search"></i></button>
      <?php if (array_filter($f)): ?>
        <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Résumé + compteurs statut -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
  <div style="font-size:.8rem;color:var(--text-muted);">
    <strong style="color:var(--text);"><?= $total ?></strong> caisse<?= $total>1?'s':'' ?> trouvée<?= $total>1?'s':'' ?>
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap;">
    <?php foreach ($sc_cfg as $k=>$v): [$lbl,$bg,$co,$bord,$ico]=$v; ?>
      <span style="background:<?= $bg ?>;color:<?= $co ?>;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;">
        <i class="fa-solid <?= $ico ?>"></i> <?= $lbl ?> <?= $cpt[$k] ?>
      </span>
    <?php endforeach; ?>
  </div>
</div>

<!-- Grille caisses -->
<?php if (empty($caisses)): ?>
<div style="text-align:center;padding:60px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
  <i class="fa-solid fa-piggy-bank" style="font-size:2rem;color:var(--blue-300);display:block;margin-bottom:10px;"></i>
  <div style="font-size:.88rem;color:var(--text-muted);margin-bottom:14px;">Aucune caisse trouvée.</div>
  <?php if ($peut_creer): ?>
    <a href="caisse_form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Créer la première caisse</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="caisses-grid">
<?php foreach ($caisses as $c):
  [$lbl,$bg,$co,$bord,$ico] = $sc_cfg[$c['statut']] ?? $sc_cfg['active'];
  $taux = (float)($c['montant_initial']??0) > 0
        ? min(100, round((float)($c['montant_utilise']??0)/(float)($c['montant_initial']??1)*100,1)) : 0;
  $pfill = $taux>=90?'#ef4444':($taux>=70?'#f59e0b':'#22c55e');
?>
<div class="cc">
  <div class="cc-top-bar" style="background:<?= $bord ?>;"></div>
  <div class="cc-body">
    <div class="cc-code"><?= e($c['code_caisse']) ?> · T<?= $c['trimestre'] ?>-<?= $c['annee'] ?></div>
    <div class="cc-titre"><?= e($c['libelle']) ?></div>
    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px;">
      <span class="badge" style="background:<?= $bg ?>;color:<?= $co ?>;"><i class="fa-solid <?= $ico ?>"></i> <?= $lbl ?></span>
      <?php if (!empty($c['source_financement'])): ?>
        <span class="badge" style="background:var(--blue-50);color:var(--blue-600);"><i class="fa-solid fa-building-columns"></i> <?= e(mb_strimwidth($c['source_financement'],0,18,'…')) ?></span>
      <?php endif; ?>
    </div>
    <div class="prog"><div class="prog-f" style="width:<?= $taux ?>%;background:<?= $pfill ?>;"></div></div>
    <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-muted);margin-bottom:10px;">
      <span>Consommé : <strong style="color:<?= $pfill ?>;"><?= $taux ?>%</strong></span>
      <?php if ((int)($c['nb_dossiers']??0) > 0): ?>
        <span><?= (int)$c['nb_dossiers'] ?> dossier<?= $c['nb_dossiers']>1?'s':'' ?> exécuté<?= $c['nb_dossiers']>1?'s':'' ?></span>
      <?php endif; ?>
    </div>
    <div class="cc-montants">
      <div class="cm-block">
        <div class="cm-lbl">Montant initial</div>
        <div class="cm-val" style="font-size:.78rem;"><?= sub_fmt((float)$c['montant_initial']) ?></div>
      </div>
      <div class="cm-block">
        <div class="cm-lbl">Disponible</div>
        <div class="cm-val" style="font-size:.78rem;color:<?= (float)$c['montant_disponible']<0?'var(--danger)':'#22c55e' ?>;">
          <?= sub_fmt((float)$c['montant_disponible']) ?>
        </div>
      </div>
    </div>
  </div>
  <div class="cc-footer">
    <a href="caisse_voir.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i> Détail</a>
    <?php if ($peut_creer): ?>
      <a href="caisse_form.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier"><i class="fa-solid fa-pen"></i></a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/modules/marches/index.php?caisse=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" style="margin-left:auto;">
      <i class="fa-solid fa-file-contract"></i> Dossiers
    </a>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($pager['nb_pages']>1): $qb=array_filter(array_diff_key($_GET,['page'=>1])); ?>
<div class="pager">
  <?php if ($pager['a_precedente']): ?><a href="?<?= http_build_query(array_merge($qb,['page'=>$page-1])) ?>"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
  <?php for($pg=1;$pg<=$pager['nb_pages'];$pg++):
    if($pg===$page): ?><span class="cur"><?= $pg ?></span>
    <?php elseif($pg===1||$pg===$pager['nb_pages']||abs($pg-$page)<=1): ?><a href="?<?= http_build_query(array_merge($qb,['page'=>$pg])) ?>"><?= $pg ?></a>
    <?php elseif(abs($pg-$page)===2): ?><span class="dots">…</span>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($pager['a_suivante']): ?><a href="?<?= http_build_query(array_merge($qb,['page'=>$page+1])) ?>"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
$extra_scripts = '<script>
function appF(){
  const qs=new URLSearchParams({q:document.getElementById("fq").value,statut:document.getElementById("fstatut").value,annee:document.getElementById("fannee").value,page:1});
  for(const[k,v]of[...qs.entries()])if(!v)qs.delete(k);
  location.href="?"+qs;
}
document.getElementById("fq").addEventListener("keydown",e=>{if(e.key==="Enter")appF();});
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
