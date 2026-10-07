<?php
// modules/instructions/index.php — Liste des instructions
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/instruction.php';

session_demarrer();
exiger_permission('INSTRUCTIONS', 'VOIR');

$peut_emettre = _instr_peut_emettre();

$f = [
    'statut'    => clean($_GET['statut']    ?? ''),
    'type'      => clean($_GET['type']      ?? ''),
    'priorite'  => clean($_GET['priorite']  ?? ''),
    'q'         => clean($_GET['q']         ?? ''),
    'a_valider' => clean($_GET['a_valider'] ?? ''),   // '1' = uniquement celles en attente de validation
];

$par_page = 12;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $par_page;
$result   = instruction_lister($f, $par_page, $offset);
$instr    = $result['data'];
$total    = $result['total'];
$pager    = paginer($total, $par_page, $page);
$cpt      = instruction_compteurs();
$nb_a_valider = $peut_emettre ? instruction_nb_a_valider() : 0;   // tâches soumises en attente du DG

$statut_cfg = [
    'EN_COURS' => ['label'=>'En cours','bg'=>'#dbeafe','color'=>'#1e40af','bord'=>'#3b82f6','icon'=>'fa-spinner'],
    'TERMINEE' => ['label'=>'Terminée','bg'=>'#dcfce7','color'=>'#166534','bord'=>'#22c55e','icon'=>'fa-circle-check'],
    'ANNULEE'  => ['label'=>'Annulée','bg'=>'#f3f4f6','color'=>'#6b7280','bord'=>'#9ca3af','icon'=>'fa-ban'],
];
$prio_cfg = ['CRITIQUE'=>['#fee2e2','#991b1b'],'HAUTE'=>['#ffedd5','#9a3412'],'NORMALE'=>['#dbeafe','#1e40af'],'FAIBLE'=>['#f3f4f6','#6b7280']];

$page_titre      = 'Instructions';
$page_breadcrumb = 'Instructions du DG';
$module_actif    = 'INSTRUCTIONS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:24px;}
.sc{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;}
.sc.alerte{border-color:#c4b5fd;background:#faf5ff;}
.sc-icon{font-size:.85rem;margin-bottom:6px;} .sc-val{font-size:1.65rem;font-weight:800;line-height:1;}
.sc-lbl{font-size:.72rem;color:var(--text-muted);margin-top:3px;}
.sc-link{text-decoration:none;display:block;}
.filtres-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;} .fg-label{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;}
.ins-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;margin-bottom:28px;}
.ic{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .2s,transform .15s;}
.ic:hover{box-shadow:0 8px 28px rgba(13,50,120,.13);transform:translateY(-2px);}
.ic-top{height:4px;} .ic-body{padding:16px 18px;flex:1;}
.ic-code{font-size:.67rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.09em;margin-bottom:5px;display:flex;justify-content:space-between;}
.ic-titre{font-size:.95rem;font-weight:800;color:var(--text);line-height:1.3;margin-bottom:8px;}
.ic-badges{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.69rem;font-weight:700;}
.ic-meta{font-size:.75rem;color:var(--text-muted);display:flex;flex-direction:column;gap:4px;}
.ic-meta i{width:14px;text-align:center;color:var(--blue-400);}
.prog-track{height:6px;background:#e5e7eb;border-radius:20px;overflow:hidden;margin:9px 0 3px;}
.prog-fill{height:100%;border-radius:20px;}
.ic-footer{padding:10px 18px;border-top:1px solid var(--border);background:var(--bg);display:flex;align-items:center;gap:7px;}
.empty{text-align:center;padding:60px 24px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);}
.empty-icon{width:64px;height:64px;border-radius:50%;background:var(--blue-100);color:var(--blue-400);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 16px;}
.pager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:24px;}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--text-muted);text-decoration:none;}
.pager span.cur{background:var(--blue-600);color:#fff;border-color:var(--blue-600);}
@media(max-width:640px){.ins-grid{grid-template-columns:1fr;}}
</style>

<div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap;">
  <div style="flex:1;min-width:150px;font-size:.8rem;color:var(--text-muted);">
    <?= $peut_emettre ? 'Émettez et suivez les instructions et leurs tâches.' : 'Les tâches qui vous sont confiées.' ?>
  </div>
  <?php if ($peut_emettre): ?>
    <a href="creer.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle instruction</a>
  <?php endif; ?>
</div>

<?php if ($peut_emettre && $nb_a_valider > 0): ?>
<div class="banner" style="display:flex;align-items:center;gap:10px;background:#faf5ff;border:1.5px solid #c4b5fd;border-radius:12px;padding:12px 16px;margin-bottom:20px;color:#5b21b6;font-size:.86rem;font-weight:600;">
  <i class="fa-solid fa-magnifying-glass"></i>
  <span><?= $nb_a_valider ?> tâche<?= $nb_a_valider>1?'s':'' ?> en attente de votre validation.</span>
  <a href="?a_valider=1" class="btn btn-sm" style="margin-left:auto;background:#7c3aed;color:#fff;"><i class="fa-solid fa-filter"></i> Voir</a>
</div>
<?php endif; ?>

<div class="stats-row">
<?php
$stats_def = [
  ['val'=>(int)$cpt['total'],     'lbl'=>'Total',     'icon'=>'fa-bullhorn',     'color'=>'var(--blue-600)', 'href'=>'index.php'],
  ['val'=>(int)$cpt['en_cours'],  'lbl'=>'En cours',  'icon'=>'fa-spinner',      'color'=>'#3b82f6',         'href'=>'?statut=EN_COURS'],
  ['val'=>(int)$cpt['terminees'], 'lbl'=>'Terminées', 'icon'=>'fa-circle-check', 'color'=>'#22c55e',         'href'=>'?statut=TERMINEE'],
  ['val'=>(int)($cpt['uniques']??0),   'lbl'=>'Tâche unique',    'icon'=>'fa-user',  'color'=>'#8b5cf6', 'href'=>'?type=UNIQUE'],
  ['val'=>(int)($cpt['multiples']??0), 'lbl'=>'Tâches multiples','icon'=>'fa-users', 'color'=>'#0ea5e9', 'href'=>'?type=MULTIPLE'],
];
// Carte "À valider" : visible uniquement pour le DG / émetteur
if ($peut_emettre) {
  array_splice($stats_def, 1, 0, [[
    'val'=>$nb_a_valider, 'lbl'=>'À valider', 'icon'=>'fa-magnifying-glass',
    'color'=>'#7c3aed', 'href'=>'?a_valider=1', 'alerte'=>true,
  ]]);
}
foreach ($stats_def as $s): ?>
<a class="sc-link" href="<?= $s['href'] ?>">
  <div class="sc <?= !empty($s['alerte']) ? 'alerte' : '' ?>">
    <div class="sc-icon" style="color:<?= $s['color'] ?>;"><i class="fa-solid <?= $s['icon'] ?>"></i></div>
    <div class="sc-val" style="color:<?= $s['color'] ?>;"><?= $s['val'] ?></div>
    <div class="sc-lbl"><?= $s['lbl'] ?></div>
  </div>
</a>
<?php endforeach; ?>
</div>

<div class="filtres-bar">
  <div class="fg" style="flex:2;min-width:180px;">
    <span class="fg-label"><i class="fa-solid fa-magnifying-glass"></i> Recherche</span>
    <input type="text" id="fq" class="form-control" placeholder="Objet, code…" value="<?= e($f['q']) ?>">
  </div>
  <div class="fg"><span class="fg-label">Statut</span>
    <select id="fstatut" class="form-control"><option value="">Tous</option>
      <?php foreach ($statut_cfg as $k=>$v): ?><option value="<?= $k ?>" <?= $f['statut']===$k?'selected':'' ?>><?= $v['label'] ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="fg"><span class="fg-label">Type</span>
    <select id="ftype" class="form-control"><option value="">Tous</option>
      <option value="UNIQUE"   <?= $f['type']==='UNIQUE'?'selected':'' ?>>Tâche unique</option>
      <option value="MULTIPLE" <?= $f['type']==='MULTIPLE'?'selected':'' ?>>Tâches multiples</option>
    </select>
  </div>
  <div class="fg"><span class="fg-label">Priorité</span>
    <select id="fpriorite" class="form-control"><option value="">Toutes</option>
      <?php foreach ($prio_cfg as $k=>$_): ?><option value="<?= $k ?>" <?= $f['priorite']===$k?'selected':'' ?>><?= $k ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php if ($peut_emettre): ?>
  <div class="fg"><span class="fg-label">Validation</span>
    <select id="favalider" class="form-control"><option value="">Toutes</option>
      <option value="1" <?= $f['a_valider']==='1'?'selected':'' ?>>À valider</option>
    </select>
  </div>
  <?php endif; ?>
  <div class="fg"><span class="fg-label">&nbsp;</span>
    <div style="display:flex;gap:6px;">
      <button class="btn btn-primary btn-sm" onclick="appF()"><i class="fa-solid fa-search"></i> Filtrer</button>
      <?php if (array_filter($f)): ?><a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
    </div>
  </div>
</div>

<div style="font-size:.8rem;color:var(--text-muted);margin-bottom:14px;">
  <strong style="color:var(--text);"><?= $total ?></strong> instruction<?= $total>1?'s':'' ?>
  <?= $f['a_valider']==='1' ? ' · <span style="color:#7c3aed;font-weight:700;">en attente de validation</span>' : '' ?>
</div>

<?php if (empty($instr)): ?>
<div class="empty">
  <div class="empty-icon"><i class="fa-solid fa-bullhorn"></i></div>
  <h3 style="font-size:1rem;font-weight:700;margin-bottom:8px;">Aucune instruction</h3>
  <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:18px;">
    <?= array_filter($f) ? 'Modifiez vos filtres.' : ($peut_emettre ? 'Émettez votre première instruction.' : 'Aucune tâche en attente.') ?>
  </p>
  <?php if ($peut_emettre): ?><a href="creer.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle instruction</a><?php endif; ?>
</div>
<?php else: ?>
<div class="ins-grid">
<?php foreach ($instr as $i):
  $sc  = $statut_cfg[$i['statut']] ?? $statut_cfg['EN_COURS'];
  $pc  = $prio_cfg[$i['priorite']] ?? $prio_cfg['NORMALE'];
  $nbT = (int)$i['nb_taches']; $nbF = (int)$i['nb_terminees'];
  $nbV = (int)($i['nb_a_valider'] ?? 0);
  $pct = $nbT > 0 ? (int)round($nbF / $nbT * 100) : 0;
  $pfcl = $pct < 34 ? '#f59e0b' : ($pct < 100 ? '#3b82f6' : '#22c55e');
  $ech = $i['prochaine_echeance'] ?? null;
  $retard = $i['statut']==='EN_COURS' && $ech && strtotime($ech) < time();
?>
<div class="ic">
  <div class="ic-top" style="background:<?= $sc['bord'] ?>;"></div>
  <div class="ic-body">
    <div class="ic-code">
      <span><?= e($i['code']) ?></span>
      <span style="color:<?= $i['type']==='MULTIPLE'?'#0ea5e9':'#8b5cf6' ?>;">
        <i class="fa-solid <?= $i['type']==='MULTIPLE'?'fa-users':'fa-user' ?>"></i>
        <?= $i['type']==='MULTIPLE'?'Multiple':'Unique' ?>
      </span>
    </div>
    <div class="ic-titre"><?= e($i['objet']) ?></div>
    <div class="ic-badges">
      <span class="badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;"><i class="fa-solid <?= $sc['icon'] ?>"></i> <?= $sc['label'] ?></span>
      <span class="badge" style="background:<?= $pc[0] ?>;color:<?= $pc[1] ?>;"><?= $i['priorite'] ?></span>
      <?php if ($peut_emettre && $nbV > 0): ?><span class="badge" style="background:#ede9fe;color:#5b21b6;"><i class="fa-solid fa-magnifying-glass"></i> <?= $nbV ?> à valider</span><?php endif; ?>
      <?php if ($retard): ?><span class="badge" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-triangle-exclamation"></i> En retard</span><?php endif; ?>
    </div>
    <div class="ic-meta">
      <div><i class="fa-solid fa-user-tie"></i> <?= e($i['emetteur_nom']) ?></div>
      <div><i class="fa-solid fa-list-check"></i> <?= $nbT ?> tâche<?= $nbT>1?'s':'' ?></div>
      <?php if ($ech): ?><div><i class="fa-regular fa-clock"></i> Prochaine échéance : <?= format_datetime($ech) ?></div><?php endif; ?>
    </div>
    <?php if ($nbT > 0): ?>
    <div class="prog-track"><div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $pfcl ?>;"></div></div>
    <div style="display:flex;justify-content:space-between;font-size:.71rem;color:var(--text-muted);">
      <span><?= $nbF ?>/<?= $nbT ?> validée<?= $nbF>1?'s':'' ?></span><strong style="color:var(--blue-600);"><?= $pct ?>%</strong>
    </div>
    <?php endif; ?>
  </div>
  <div class="ic-footer">
    <div style="flex:1;"></div>
    <a href="voir.php?id=<?= $i['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i> Ouvrir</a>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($pager['nb_pages']>1): $qb = array_filter(array_diff_key($_GET,['page'=>1])); ?>
<div class="pager">
  <?php if ($pager['a_precedente']): ?><a href="?<?= http_build_query(array_merge($qb,['page'=>$page-1])) ?>"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
  <?php for ($pg=1;$pg<=$pager['nb_pages'];$pg++): if ($pg===$page): ?><span class="cur"><?= $pg ?></span><?php else: ?><a href="?<?= http_build_query(array_merge($qb,['page'=>$pg])) ?>"><?= $pg ?></a><?php endif; endfor; ?>
  <?php if ($pager['a_suivante']): ?><a href="?<?= http_build_query(array_merge($qb,['page'=>$page+1])) ?>"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
$extra_scripts = '<script>
function appF(){
  const fav = document.getElementById("favalider");
  const qs=new URLSearchParams({
    q:document.getElementById("fq").value, statut:document.getElementById("fstatut").value,
    type:document.getElementById("ftype").value, priorite:document.getElementById("fpriorite").value,
    a_valider: fav ? fav.value : "", page:1
  });
  for(const [k,v] of [...qs.entries()]) if(!v) qs.delete(k);
  location.href="?"+qs;
}
document.getElementById("fq").addEventListener("keydown",e=>{if(e.key==="Enter")appF();});
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
