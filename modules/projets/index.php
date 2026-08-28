<?php
// modules/projets/index.php — Liste des projets
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet.php';

session_demarrer();
exiger_permission('PROJETS', 'VOIR');

$peut_creer = a_permission('PROJETS', 'CREER');

// Filtres
$f = [
    'statut'   => clean($_GET['statut']   ?? ''),
    'type'     => clean($_GET['type']     ?? ''),
    'priorite' => clean($_GET['priorite'] ?? ''),
    'q'        => clean($_GET['q']        ?? ''),
];

// Pagination
$par_page = 12;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $par_page;
$result   = projet_lister($f, $par_page, $offset);
$projets  = $result['data'];
$total    = $result['total'];
$pager    = paginer($total, $par_page, $page);
$cpt      = projet_compteurs_statuts();

// ── Config affichage ──
$statut_cfg = [
    'PLANIFIE' => ['label'=>'Planifié',  'bg'=>'#fef3c7','color'=>'#92400e','bord'=>'#f59e0b','icon'=>'fa-hourglass-start'],
    'EN_COURS' => ['label'=>'En cours',  'bg'=>'#dbeafe','color'=>'#1e40af','bord'=>'#3b82f6','icon'=>'fa-play'],
    'SUSPENDU' => ['label'=>'Suspendu',  'bg'=>'#fce7f3','color'=>'#9d174d','bord'=>'#ec4899','icon'=>'fa-pause'],
    'TERMINE'  => ['label'=>'Terminé',   'bg'=>'#dcfce7','color'=>'#166534','bord'=>'#22c55e','icon'=>'fa-circle-check'],
    'ANNULE'   => ['label'=>'Annulé',    'bg'=>'#f3f4f6','color'=>'#6b7280','bord'=>'#9ca3af','icon'=>'fa-ban'],
];
$priorite_cfg = [
    'CRITIQUE'=>['#fee2e2','#991b1b'],
    'HAUTE'   =>['#ffedd5','#9a3412'],
    'NORMALE' =>['#dbeafe','#1e40af'],
    'FAIBLE'  =>['#f3f4f6','#6b7280'],
];
$type_labels = ['ADMINISTRATIF'=>'Administratif','INFORMATIQUE'=>'Informatique','TERRAIN'=>'Terrain','RECHERCHE'=>'Recherche','AUTRE'=>'Autre'];

$page_titre      = 'Projets & Tâches';
$page_breadcrumb = 'Gestion des Projets';
$module_actif    = 'PROJETS';
$projet_sous_actif = 'TACHES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
/* ── Stats ── */
.stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:24px;}
.sc{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;transition:box-shadow .15s;}
.sc:hover{box-shadow:var(--shadow);}
.sc-icon{font-size:.85rem;margin-bottom:6px;}
.sc-val{font-size:1.65rem;font-weight:800;line-height:1;}
.sc-lbl{font-size:.72rem;color:var(--text-muted);margin-top:3px;}

/* ── Filtres ── */
.filtres-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;}
.fg-label{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;}

/* ── Grille projets ── */
.projets-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;margin-bottom:28px;}
.pc{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .2s,transform .15s;}
.pc:hover{box-shadow:0 8px 28px rgba(13,50,120,.13);transform:translateY(-2px);}
.pc-top-bar{height:4px;}
.pc-body{padding:16px 18px;flex:1;}
.pc-code{font-size:.67rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.09em;margin-bottom:5px;}
.pc-titre{font-size:.95rem;font-weight:800;color:var(--text);line-height:1.3;margin-bottom:9px;}
.pc-badges{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.69rem;font-weight:700;}
.pc-meta{font-size:.75rem;color:var(--text-muted);display:flex;flex-direction:column;gap:4px;margin-bottom:12px;}
.pc-meta-r{display:flex;align-items:center;gap:6px;}
.pc-meta-r i{width:14px;text-align:center;color:var(--blue-400);}
.prog-track{height:6px;background:#e5e7eb;border-radius:20px;overflow:hidden;margin-bottom:3px;}
.prog-fill{height:100%;border-radius:20px;transition:width .4s;}
.prog-label{display:flex;justify-content:space-between;font-size:.71rem;color:var(--text-muted);}
.pc-footer{padding:10px 18px;border-top:1px solid var(--border);background:var(--bg);display:flex;align-items:center;gap:7px;}
.pc-avatars{display:flex;}
.pca{width:26px;height:26px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;border:2px solid #fff;margin-left:-6px;flex-shrink:0;}
.pca:first-child{margin-left:0;}
.pca.more{background:var(--bg);color:var(--text-muted);}

/* ── Pagination ── */
.pager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:24px;}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--text-muted);text-decoration:none;transition:all .12s;}
.pager a:hover{border-color:var(--blue-400);color:var(--blue-600);}
.pager span.cur{background:var(--blue-600);color:#fff;border-color:var(--blue-600);}
.pager .dots{border:none;background:none;}

/* ── Empty ── */
.empty{text-align:center;padding:60px 24px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);}
.empty-icon{width:64px;height:64px;border-radius:50%;background:var(--blue-100);color:var(--blue-400);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 16px;}
@media(max-width:640px){.projets-grid{grid-template-columns:1fr;}}
</style>

<!-- Entête -->
<div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap;">
  <div style="flex:1;min-width:150px;">
    <div style="font-size:.8rem;color:var(--text-muted);">
      <?= $peut_creer ? 'Gérez tous les projets de l\'organisation.' : 'Vos projets en cours.' ?>
    </div>
  </div>
  <?php if ($peut_creer): ?>
  <a href="creer.php" class="btn btn-primary">
    <i class="fa-solid fa-plus"></i> Nouveau projet
  </a>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-row">
<?php
$stats_def = [
  ['val'=>$cpt['TOTAL'],    'lbl'=>'Total',       'icon'=>'fa-diagram-project','color'=>'var(--blue-600)'],
  ['val'=>$cpt['EN_COURS'], 'lbl'=>'En cours',    'icon'=>'fa-play',            'color'=>'#3b82f6'],
  ['val'=>$cpt['PLANIFIE'], 'lbl'=>'Planifiés',   'icon'=>'fa-hourglass-start', 'color'=>'#f59e0b'],
  ['val'=>$cpt['TERMINE'],  'lbl'=>'Terminés',    'icon'=>'fa-circle-check',    'color'=>'#22c55e'],
  ['val'=>$cpt['SUSPENDU'], 'lbl'=>'Suspendus',   'icon'=>'fa-pause',           'color'=>'#ec4899'],
  ['val'=>$cpt['ANNULE'],   'lbl'=>'Annulés',     'icon'=>'fa-ban',             'color'=>'#9ca3af'],
];
foreach ($stats_def as $s): ?>
<div class="sc">
  <div class="sc-icon" style="color:<?= $s['color'] ?>;"><i class="fa-solid <?= $s['icon'] ?>"></i></div>
  <div class="sc-val" style="color:<?= $s['color'] ?>;"><?= $s['val'] ?></div>
  <div class="sc-lbl"><?= $s['lbl'] ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- Filtres -->
<div class="filtres-bar">
  <div class="fg" style="flex:2;min-width:180px;">
    <span class="fg-label"><i class="fa-solid fa-magnifying-glass"></i> Recherche</span>
    <input type="text" id="fq" class="form-control" placeholder="Titre, code…"
           value="<?= e($f['q']) ?>">
  </div>
  <div class="fg">
    <span class="fg-label">Statut</span>
    <select id="fstatut" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($statut_cfg as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['statut']===$k?'selected':'' ?>><?= $v['label'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <span class="fg-label">Type</span>
    <select id="ftype" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($type_labels as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['type']===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <span class="fg-label">Priorité</span>
    <select id="fpriorite" class="form-control">
      <option value="">Toutes</option>
      <?php foreach ($priorite_cfg as $k=>$_): ?>
        <option value="<?= $k ?>" <?= $f['priorite']===$k?'selected':'' ?>><?= $k ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <span class="fg-label">&nbsp;</span>
    <div style="display:flex;gap:6px;">
      <button class="btn btn-primary btn-sm" onclick="appF()"><i class="fa-solid fa-search"></i> Filtrer</button>
      <?php if (array_filter($f)): ?>
        <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div style="font-size:.8rem;color:var(--text-muted);margin-bottom:14px;">
  <strong style="color:var(--text);"><?= $total ?></strong> projet<?= $total>1?'s':'' ?> trouvé<?= $total>1?'s':'' ?>
  <?php if ($pager['nb_pages']>1): ?> — page <?= $page ?>/<?= $pager['nb_pages'] ?><?php endif; ?>
</div>

<!-- Grille projets -->
<?php if (empty($projets)): ?>
<div class="empty">
  <div class="empty-icon"><i class="fa-solid fa-diagram-project"></i></div>
  <h3 style="font-size:1rem;font-weight:700;margin-bottom:8px;">Aucun projet trouvé</h3>
  <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:18px;">
    <?= array_filter($f) ? 'Modifiez vos filtres.' : 'Commencez par créer votre premier projet.' ?>
  </p>
  <?php if ($peut_creer): ?><a href="creer.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouveau projet</a><?php endif; ?>
</div>
<?php else: ?>
<div class="projets-grid">
<?php foreach ($projets as $p):
  $sc   = $statut_cfg[$p['statut']] ?? $statut_cfg['PLANIFIE'];
  $pc   = $priorite_cfg[$p['priorite']] ?? $priorite_cfg['NORMALE'];
  $pct  = (int)$p['avancement_global'];
  $pfcl = $pct < 30 ? 'background:#f59e0b' : ($pct < 70 ? 'background:#3b82f6' : 'background:#22c55e');
  $retard = (int)$p['jours_restants'] < 0 && !in_array($p['statut'],['TERMINE','ANNULE']);
?>
<div class="pc">
  <div class="pc-top-bar" style="background:<?= $sc['bord'] ?>;"></div>
  <div class="pc-body">
    <div class="pc-code"><?= e($p['code']) ?> · <?= $type_labels[$p['type_projet']] ?? $p['type_projet'] ?></div>
    <div class="pc-titre"><?= e($p['titre']) ?></div>
    <div class="pc-badges">
      <span class="badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
        <i class="fa-solid <?= $sc['icon'] ?>"></i> <?= $sc['label'] ?>
      </span>
      <span class="badge" style="background:<?= $pc[0] ?>;color:<?= $pc[1] ?>;"><?= $p['priorite'] ?></span>
      <?php if ($retard): ?>
        <span class="badge" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-triangle-exclamation"></i> Retard <?= abs((int)$p['jours_restants']) ?>j</span>
      <?php elseif (!in_array($p['statut'],['TERMINE','ANNULE']) && (int)$p['jours_restants'] <= 7 && (int)$p['jours_restants'] >= 0): ?>
        <span class="badge" style="background:#fef3c7;color:#92400e;"><i class="fa-solid fa-clock"></i> <?= (int)$p['jours_restants'] ?>j</span>
      <?php endif; ?>
    </div>
    <div class="pc-meta">
      <div class="pc-meta-r"><i class="fa-solid fa-user-tie"></i> <?= e($p['responsable_nom']) ?></div>
      <div class="pc-meta-r"><i class="fa-regular fa-calendar"></i> <?= format_date($p['date_debut']) ?> → <?= format_date($p['date_fin_prevue']) ?></div>
      <?php if (!empty($p['budget'])): ?>
        <div class="pc-meta-r"><i class="fa-solid fa-coins"></i> <?= number_format((float)$p['budget'],0,',',' ') ?> <?= e($p['devise']) ?></div>
      <?php endif; ?>
    </div>
    <?php if ((int)$p['nb_taches'] > 0): ?>
    <div>
      <div class="prog-track"><div class="prog-fill" style="width:<?= $pct ?>%;<?= $pfcl ?>;"></div></div>
      <div class="prog-label">
        <span><?= (int)$p['nb_terminees'] ?>/<?= (int)$p['nb_taches'] ?> tâche<?= $p['nb_taches']>1?'s':'' ?></span>
        <strong style="color:var(--blue-600);"><?= $pct ?>%</strong>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="pc-footer">
    <?php
    $sm = get_db()->prepare("
        SELECT CONCAT(LEFT(u.prenoms,1),LEFT(u.nom,1)) AS init
        FROM projets_membres pm JOIN utilisateurs u ON u.id=pm.utilisateur_id
        WHERE pm.projet_id=? ORDER BY pm.role LIMIT 5
    ");
    $sm->execute([$p['id']]);
    $mb = $sm->fetchAll();
    $nb_m = (int)$p['nb_membres'];
    if ($nb_m > 0): ?>
    <div class="pc-avatars">
      <?php foreach (array_slice($mb,0,4) as $m): ?>
        <div class="pca" title="<?= e(strtoupper($m['init'])) ?>"><?= e(strtoupper($m['init'])) ?></div>
      <?php endforeach; ?>
      <?php if ($nb_m > 4): ?><div class="pca more">+<?= $nb_m-4 ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <div style="flex:1;"></div>
    <a href="voir.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i> Ouvrir</a>
    <?php if ($peut_creer): ?>
      <a href="creer.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier"><i class="fa-solid fa-pen"></i></a>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($pager['nb_pages']>1):
  $qb = array_filter(array_diff_key($_GET,['page'=>1]));
?>
<div class="pager">
  <?php if ($pager['a_precedente']): ?>
    <a href="?<?= http_build_query(array_merge($qb,['page'=>$page-1])) ?>"><i class="fa-solid fa-chevron-left"></i></a>
  <?php endif; ?>
  <?php for ($pg=1;$pg<=$pager['nb_pages'];$pg++):
    if ($pg===$page): ?><span class="cur"><?= $pg ?></span>
    <?php elseif ($pg===1||$pg===$pager['nb_pages']||abs($pg-$page)<=1): ?>
      <a href="?<?= http_build_query(array_merge($qb,['page'=>$pg])) ?>"><?= $pg ?></a>
    <?php elseif (abs($pg-$page)===2): ?><span class="dots">…</span>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($pager['a_suivante']): ?>
    <a href="?<?= http_build_query(array_merge($qb,['page'=>$page+1])) ?>"><i class="fa-solid fa-chevron-right"></i></a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
$extra_scripts = '<script>
function appF(){
  const qs=new URLSearchParams({
    q:      document.getElementById("fq").value,
    statut: document.getElementById("fstatut").value,
    type:   document.getElementById("ftype").value,
    priorite:document.getElementById("fpriorite").value,
    page:1
  });
  for(const [k,v] of [...qs.entries()]) if(!v) qs.delete(k);
  location.href="?"+qs;
}
document.getElementById("fq").addEventListener("keydown",e=>{if(e.key==="Enter")appF();});
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
