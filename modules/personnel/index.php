<?php
// modules/personnel/index.php — Liste du personnel (RH)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/personnel.php';
require_once __DIR__ . '/../../models/contrats.php';

session_demarrer();
exiger_permission('PERSONNEL', 'VOIR');

$peut_creer  = a_permission('PERSONNEL', 'CREER');
$peut_modif  = a_permission('PERSONNEL', 'MODIFIER');

// ── Filtres ──
$f = [
    'recherche'    => clean($_GET['q']            ?? ''),
    'statut_rh'    => clean($_GET['statut_rh']     ?? ''),
    'type_contrat' => clean($_GET['type_contrat']  ?? ''),
    'departement'  => clean($_GET['departement']   ?? ''),
];

// ── Pagination ──
$par_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $par_page;
$result   = personnel_lister($f, $par_page, $offset);
$liste    = $result['data'];
$total    = $result['total'];
$pager    = paginer($total, $par_page, $page);
$stats    = personnel_statistiques();
$contrats_alerte = contrats_expirant(30);

// ── Référentiels d'affichage ──
$statut_cfg = [
    'ACTIF'     => ['label' => 'Actif',     'bg' => '#dcfce7', 'color' => '#166534'],
    'CONGE'     => ['label' => 'En congé',  'bg' => '#dbeafe', 'color' => '#1e40af'],
    'MISSION'   => ['label' => 'En mission','bg' => '#e0e7ff', 'color' => '#3730a3'],
    'SUSPENDU'  => ['label' => 'Suspendu',  'bg' => '#fee2e2', 'color' => '#991b1b'],
    'DEMISSION' => ['label' => 'Démission', 'bg' => '#f3f4f6', 'color' => '#6b7280'],
    'RETRAITE'  => ['label' => 'Retraite',  'bg' => '#fef3c7', 'color' => '#92400e'],
];
$contrats = ['CDD' => 'CDD', 'CDI' => 'CDI', 'STAGIAIRE' => 'Stagiaire', 'CONSULTANT' => 'Consultant', 'VACATAIRE' => 'Vacataire'];
$departements = [
    'DIRECTION'              => 'Direction',
    'ADMIN_GENERALE'         => 'Administration générale',
    'ADMIN_FINANCIERE'       => 'Administration financière',
    'RECHERCHE_SCIENTIFIQUE' => 'Recherche scientifique',
    'SYSTEME'                => 'Système',
];

$page_titre      = 'Gestion du Personnel';
$page_breadcrumb = 'RH · Personnel';
$module_actif    = 'PERSONNEL';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:24px;}
.sc{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;transition:box-shadow .15s;}
.sc:hover{box-shadow:var(--shadow);}
.sc-icon{font-size:.85rem;margin-bottom:6px;}
.sc-val{font-size:1.65rem;font-weight:800;line-height:1;}
.sc-lbl{font-size:.72rem;color:var(--text-muted);margin-top:3px;}

.filtres-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;}
.fg-label{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;}

.p-avatar{width:34px;height:34px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;overflow:hidden;flex-shrink:0;}
.p-avatar img{width:100%;height:100%;object-fit:cover;}
.p-name{display:flex;align-items:center;gap:10px;}
.p-name .n{font-weight:600;color:var(--text);}
.p-name .m{font-size:.72rem;color:var(--text-muted);font-family:var(--mono);}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:20px;font-size:.7rem;font-weight:700;}

.pager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:24px;}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--text-muted);text-decoration:none;transition:all .12s;}
.pager a:hover{border-color:var(--blue-400);color:var(--blue-600);}
.pager span.cur{background:var(--blue-600);color:#fff;border-color:var(--blue-600);}
.pager .dots{border:none;background:none;}

.empty{text-align:center;padding:60px 24px;}
.empty-icon{width:64px;height:64px;border-radius:50%;background:var(--blue-100);color:var(--blue-400);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 16px;}
</style>

<!-- Entête -->
<div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap;">
  <div style="flex:1;min-width:150px;font-size:.8rem;color:var(--text-muted);">
    Dossiers du personnel du centre. Chaque dossier est lié à un compte utilisateur.
  </div>
  <a href="<?= APP_URL ?>/modules/demandes/demandes.php" class="btn btn-secondary"><i class="fa-solid fa-inbox"></i> Demandes RH</a>
  <a href="organigramme.php" class="btn btn-secondary"><i class="fa-solid fa-sitemap"></i> Organigramme</a>
  <?php if ($peut_creer): ?>
  <a href="creer.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Nouvel employé</a>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-row">
<?php
$stats_def = [
  ['val'=>$stats['total'],     'lbl'=>'Total',     'icon'=>'fa-users',          'color'=>'var(--blue-600)'],
  ['val'=>$stats['actifs'],    'lbl'=>'Actifs',    'icon'=>'fa-user-check',     'color'=>'#22c55e'],
  ['val'=>$stats['conge'],     'lbl'=>'En congé',  'icon'=>'fa-umbrella-beach', 'color'=>'#3b82f6'],
  ['val'=>$stats['mission'],   'lbl'=>'En mission','icon'=>'fa-plane',          'color'=>'#6366f1'],
  ['val'=>$stats['suspendus'], 'lbl'=>'Suspendus', 'icon'=>'fa-user-lock',      'color'=>'#ef4444'],
  ['val'=>$stats['sortis'],    'lbl'=>'Sortis',    'icon'=>'fa-user-slash',     'color'=>'#9ca3af'],
];
foreach ($stats_def as $s): ?>
<div class="sc">
  <div class="sc-icon" style="color:<?= $s['color'] ?>;"><i class="fa-solid <?= $s['icon'] ?>"></i></div>
  <div class="sc-val" style="color:<?= $s['color'] ?>;"><?= $s['val'] ?></div>
  <div class="sc-lbl"><?= $s['lbl'] ?></div>
</div>
<?php endforeach; ?>
</div>

<?php if (!empty($contrats_alerte)): ?>
<div class="card" style="border-left:4px solid #f59e0b;margin-bottom:20px;">
  <div class="card-body" style="padding:14px 18px;">
    <div style="font-weight:700;font-size:.88rem;color:#92400e;margin-bottom:8px;"><i class="fa-solid fa-triangle-exclamation"></i> Contrats arrivant à échéance (30 jours)</div>
    <?php foreach ($contrats_alerte as $ca): ?>
      <div style="font-size:.82rem;padding:3px 0;">
        <a href="voir.php?id=<?= (int)$ca['pid'] ?>&tab=contrats"><?= e($ca['employe_nom']) ?></a>
        — <?= e($ca['type_contrat']) ?> · échéance <strong><?= e(format_date($ca['date_fin'])) ?></strong>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Filtres -->
<div class="filtres-bar">
  <div class="fg" style="flex:2;min-width:200px;">
    <span class="fg-label"><i class="fa-solid fa-magnifying-glass"></i> Recherche</span>
    <input type="text" id="fq" class="form-control" placeholder="Nom, e-mail, matricule…" value="<?= e($f['recherche']) ?>">
  </div>
  <div class="fg">
    <span class="fg-label">Statut RH</span>
    <select id="fstatut" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($statut_cfg as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['statut_rh']===$k?'selected':'' ?>><?= $v['label'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <span class="fg-label">Contrat</span>
    <select id="fcontrat" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($contrats as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['type_contrat']===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <span class="fg-label">Département</span>
    <select id="fdept" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($departements as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['departement']===$k?'selected':'' ?>><?= $v ?></option>
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
  <strong style="color:var(--text);"><?= $total ?></strong> employé<?= $total>1?'s':'' ?>
  <?php if ($pager['nb_pages']>1): ?> — page <?= $page ?>/<?= $pager['nb_pages'] ?><?php endif; ?>
</div>

<!-- Tableau -->
<?php if (empty($liste)): ?>
<div class="card"><div class="card-body empty">
  <div class="empty-icon"><i class="fa-solid fa-id-card"></i></div>
  <h3 style="font-size:1rem;font-weight:700;margin-bottom:8px;">Aucun employé trouvé</h3>
  <p style="font-size:.85rem;color:var(--text-muted);"><?= array_filter($f) ? 'Modifiez vos filtres.' : 'Commencez par créer un employé.' ?></p>
</div></div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table class="crict-table">
      <thead>
        <tr>
          <th>Employé</th>
          <th>Fonction</th>
          <th>Groupe</th>
          <th>Contrat</th>
          <th>Statut RH</th>
          <th>Compte</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($liste as $p):
        $init = strtoupper(mb_substr($p['prenoms'] ?? 'U',0,1) . mb_substr($p['nom'] ?? 'S',0,1));
        $sc   = $statut_cfg[$p['statut_rh']] ?? $statut_cfg['ACTIF'];
        $compte_actif = ($p['statut_compte'] ?? '') === 'ACTIF';
      ?>
        <tr>
          <td>
            <div class="p-name">
              <span class="p-avatar">
                <?php if (!empty($p['photo'])): ?><img src="<?= e($p['photo']) ?>" alt=""><?php else: ?><?= e($init) ?><?php endif; ?>
              </span>
              <span>
                <div class="n"><?= e($p['prenoms'] . ' ' . $p['nom']) ?></div>
                <div class="m"><?= e($p['matricule']) ?><?= !empty($p['matricule_rh']) ? ' · '.e($p['matricule_rh']) : '' ?></div>
              </span>
            </div>
          </td>
          <td style="font-size:.82rem;"><?= e($p['fonction'] ?: '—') ?></td>
          <td style="font-size:.82rem;"><?= e($p['groupe_libelle'] ?? '—') ?></td>
          <td style="font-size:.82rem;"><?= e($contrats[$p['type_contrat']] ?? '—') ?></td>
          <td><span class="badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;"><?= $sc['label'] ?></span></td>
          <td>
            <span class="badge" style="background:<?= $compte_actif ? '#dcfce7' : '#fee2e2' ?>;color:<?= $compte_actif ? '#166534' : '#991b1b' ?>;">
              <i class="fa-solid fa-circle" style="font-size:.5rem;"></i> <?= $compte_actif ? 'Actif' : 'Bloqué' ?>
            </span>
          </td>
          <td style="text-align:right;white-space:nowrap;">
            <a href="voir.php?id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i> Ouvrir</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
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
    q:           document.getElementById("fq").value,
    statut_rh:   document.getElementById("fstatut").value,
    type_contrat:document.getElementById("fcontrat").value,
    departement: document.getElementById("fdept").value,
    page:1
  });
  for(const [k,v] of [...qs.entries()]) if(!v) qs.delete(k);
  location.href="?"+qs;
}
document.getElementById("fq").addEventListener("keydown",e=>{if(e.key==="Enter")appF();});
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
