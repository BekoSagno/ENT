<?php
// modules/marches/index.php — Liste des dossiers DAF
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/marche.php';

session_demarrer();
exiger_permission('MARCHES', 'VOIR');

$user    = user_courant();
$is_chef = in_array($user['groupe_code']??'', ['SUPER_ADMIN','DG','DAF','PRMP']);
$peut_creer = a_permission('MARCHES','CREER');

$f = [
    'statut' => clean($_GET['statut'] ?? ''),
    'annee'  => clean($_GET['annee']  ?? ''),
    'q'      => clean($_GET['q']      ?? ''),
];
$par_page = 15;
$page     = max(1,(int)($_GET['page']??1));
$offset   = ($page-1)*$par_page;
$result   = marche_lister($f, $par_page, $offset);
$dossiers = $result['data'];
$total    = $result['total'];
$pager    = paginer($total, $par_page, $page);
$cpt      = marche_compteurs();

$st_cfg = [
    'brouillon'             => ['En cours',        '#fef3c7','#92400e','fa-pen'],
    'en_attente_execution'  => ['Prêt à exécuter', '#dbeafe','#1e40af','fa-clock'],
    'execute'               => ['Exécuté',         '#dcfce7','#166534','fa-check'],
];

$flash = $_SESSION['marche_flash'] ?? null;
unset($_SESSION['marche_flash']);

$page_titre      = 'Marchés & Exécution des dépenses';
$page_breadcrumb = 'Marchés Publics';
$module_actif    = 'MARCHES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700;}
.kpi-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:22px;}
.kcard{background:var(--surface);border:1.5px solid var(--border);border-radius:10px;padding:12px 15px;cursor:pointer;transition:box-shadow .15s;}
.kcard:hover{box-shadow:var(--shadow);}
.kcard .v{font-size:1.4rem;font-weight:800;line-height:1;margin-bottom:3px;}
.kcard .l{font-size:.72rem;color:var(--text-muted);}
.filtres-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:13px 18px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;}
.fg label{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;}
.pager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:18px;}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--text-muted);text-decoration:none;transition:all .12s;}
.pager a:hover{border-color:var(--blue-400);color:var(--blue-600);}
.pager span.cur{background:var(--blue-600);color:#fff;border-color:var(--blue-600);}
.pager .dots{border:none;background:none;}
</style>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type']==='success'?'success':($flash['type']==='error'?'error':'info') ?>" style="margin-bottom:16px;">
  <i class="fa-solid <?= $flash['type']==='success'?'fa-circle-check':'fa-circle-info' ?>"></i>
  <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
  <div style="flex:1;font-size:.8rem;color:var(--text-muted);">
    Gestion du workflow DAF : consultation des offres, sélection fournisseur et exécution budgétaire.
  </div>
  <?php if ($peut_creer): ?>
    <a href="wizard.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle exécution</a>
  <?php endif; ?>
</div>

<!-- KPI -->
<div class="kpi-row">
  <?php
  $kk = [
    ['val'=>$cpt['TOTAL'],                 'lbl'=>'Total',          'c'=>'var(--blue-600)', 'st'=>''],
    ['val'=>$cpt['brouillon'],             'lbl'=>'En cours',       'c'=>'#f59e0b',          'st'=>'brouillon'],
    ['val'=>$cpt['en_attente_execution'],  'lbl'=>'Prêts',          'c'=>'#3b82f6',          'st'=>'en_attente_execution'],
    ['val'=>$cpt['execute'],               'lbl'=>'Exécutés',       'c'=>'#22c55e',          'st'=>'execute'],
  ];
  foreach ($kk as $k):
    $qs = http_build_query(array_merge($f,['statut'=>$k['st'],'page'=>1]));
  ?>
  <div class="kcard" onclick="location.href='?<?= $qs ?>'">
    <div class="v" style="color:<?= $k['c'] ?>;"><?= $k['val'] ?></div>
    <div class="l"><?= $k['lbl'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtres -->
<div class="filtres-bar">
  <div class="fg" style="flex:2;min-width:160px;">
    <label><i class="fa-solid fa-magnifying-glass"></i> Recherche</label>
    <input type="text" id="fq" class="form-control" placeholder="N°, libellé, fournisseur…" value="<?= e($f['q']) ?>">
  </div>
  <div class="fg">
    <label>Statut</label>
    <select id="fstatut" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($st_cfg as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['statut']===$k?'selected':'' ?>><?= $v[0] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Année</label>
    <select id="fannee" class="form-control">
      <option value="">Toutes</option>
      <?php for ($y=date('Y');$y>=date('Y')-5;$y--): ?>
        <option value="<?= $y ?>" <?= $f['annee']==(string)$y?'selected':'' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="fg">
    <label>&nbsp;</label>
    <div style="display:flex;gap:6px;">
      <button class="btn btn-primary btn-sm" onclick="appF()"><i class="fa-solid fa-search"></i></button>
      <?php if (array_filter(array_values($f))): ?>
        <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div style="font-size:.8rem;color:var(--text-muted);margin-bottom:12px;">
  <strong style="color:var(--text);"><?= $total ?></strong> dossier<?= $total>1?'s':'' ?> trouvé<?= $total>1?'s':'' ?>
</div>

<!-- Tableau -->
<?php if (empty($dossiers)): ?>
<div style="text-align:center;padding:60px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
  <i class="fa-solid fa-file-contract" style="font-size:2rem;color:var(--blue-300);display:block;margin-bottom:10px;"></i>
  <div style="color:var(--text-muted);font-size:.88rem;margin-bottom:14px;">Aucun dossier trouvé.</div>
  <?php if ($peut_creer): ?>
    <a href="wizard.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Créer le premier dossier</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="crict-table">
  <thead><tr>
    <th>N° Dossier</th>
    <th>Libellé</th>
    <?php if ($is_chef): ?><th>Créateur</th><?php endif; ?>
    <th>Fournisseur retenu</th>
    <th>Montant TTC</th>
    <th>Période</th>
    <th>Statut</th>
    <th>Actions</th>
  </tr></thead>
  <tbody>
  <?php foreach ($dossiers as $d):
    $sc  = $st_cfg[$d['statut']] ?? $st_cfg['brouillon'];
    $fav = (int)($d['fournisseur_favori']??0);
    $nom_retenu = $fav >= 1 && $fav <= 3 ? trim($d['fournisseur_'.$fav.'_nom']??'') : '';
  ?>
  <tr>
    <td>
      <code style="font-size:.78rem;background:var(--bg);padding:2px 7px;border-radius:5px;font-weight:700;"><?= e($d['numero_dossier']??'—') ?></code>
    </td>
    <td>
      <div style="font-size:.84rem;font-weight:600;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($d['libelle']??'—') ?></div>
      <?php if ($d['code_categorie']??null): ?>
        <div style="font-size:.7rem;color:var(--text-muted);"><?= e($d['code_categorie']) ?></div>
      <?php endif; ?>
    </td>
    <?php if ($is_chef): ?><td style="font-size:.82rem;"><?= e($d['createur_nom']??'—') ?></td><?php endif; ?>
    <td style="font-size:.83rem;">
      <?= $nom_retenu ? e($nom_retenu) : '<span style="color:var(--text-muted);">—</span>' ?>
    </td>
    <td style="font-weight:700;font-size:.88rem;white-space:nowrap;">
      <?= $d['montant_ttc'] ? marche_fmt((float)$d['montant_ttc']) : '<span style="color:var(--text-muted);">—</span>' ?>
    </td>
    <td style="font-size:.78rem;white-space:nowrap;">
      <?= $d['periode_annee'] ? 'T'.$d['periode_trimestre'].' / '.$d['periode_annee'] : '—' ?>
    </td>
    <td>
      <span class="badge" style="background:<?= $sc[1] ?>;color:<?= $sc[2] ?>;">
        <i class="fa-solid <?= $sc[3] ?>"></i> <?= $sc[0] ?>
      </span>
    </td>
    <td>
      <div style="display:flex;gap:5px;">
        <?php if ($d['statut'] !== 'execute'): ?>
          <a href="wizard.php?id=<?= $d['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-play"></i> Continuer</a>
        <?php else: ?>
          <a href="voir.php?id=<?= $d['id'] ?>"   class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i> Voir</a>
        <?php endif; ?>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
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
