<?php
// modules/personnel/organigramme.php — Organigramme & structure
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/personnel.php';
require_once __DIR__ . '/../../models/organigramme.php';

session_demarrer();
exiger_permission('PERSONNEL', 'VOIR');
$peut_gerer = a_permission('PERSONNEL', 'MODIFIER_SENSIBLE');

$types_unite = ['DIRECTION'=>'Direction','DEPARTEMENT'=>'Département','LABORATOIRE'=>'Laboratoire','SERVICE'=>'Service','EQUIPE'=>'Équipe','AUTRE'=>'Autre'];
$cat_grade   = ['ACADEMIQUE'=>'Académique','TECHNIQUE'=>'Technique','ADMINISTRATIF'=>'Administratif','AUTRE'=>'Autre'];

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { set_flash('error','Session expirée.'); redirect('/modules/personnel/organigramme.php'); }
    if (!$peut_gerer)   { set_flash('error','Droits insuffisants.'); redirect('/modules/personnel/organigramme.php'); }
    $action = $_POST['action'] ?? '';
    $tab    = clean($_POST['tab'] ?? 'arbre');

    if ($action === 'unite_add') {
        unite_creer([
            'code'           => clean($_POST['code'] ?? ''),
            'nom'            => clean($_POST['nom'] ?? ''),
            'type'           => clean($_POST['type'] ?? 'SERVICE'),
            'parent_id'      => (int)($_POST['parent_id'] ?? 0),
            'responsable_id' => (int)($_POST['responsable_id'] ?? 0),
            'description'    => clean($_POST['description'] ?? ''),
        ]);
        set_flash('success','Unité créée.');
        redirect('/modules/personnel/organigramme.php?tab=arbre');
    }
    if ($action === 'unite_del') {
        unite_supprimer((int)($_POST['uid'] ?? 0));
        set_flash('success','Unité supprimée (sous-unités et postes détachés).');
        redirect('/modules/personnel/organigramme.php?tab=arbre');
    }
    if ($action === 'poste_add') {
        poste_creer([
            'code'        => clean($_POST['code'] ?? ''),
            'intitule'    => clean($_POST['intitule'] ?? ''),
            'unite_id'    => (int)($_POST['unite_id'] ?? 0),
            'grade_id'    => (int)($_POST['grade_id'] ?? 0),
            'description' => clean($_POST['description'] ?? ''),
        ]);
        set_flash('success','Poste créé.');
        redirect('/modules/personnel/organigramme.php?tab=postes');
    }
    if ($action === 'poste_del') {
        poste_supprimer((int)($_POST['pid'] ?? 0));
        set_flash('success','Poste supprimé.');
        redirect('/modules/personnel/organigramme.php?tab=postes');
    }
    if ($action === 'grade_add') {
        grade_creer([
            'code'      => clean($_POST['code'] ?? ''),
            'libelle'   => clean($_POST['libelle'] ?? ''),
            'categorie' => clean($_POST['categorie'] ?? 'AUTRE'),
            'niveau'    => (int)($_POST['niveau'] ?? 0),
        ]);
        set_flash('success','Grade ajouté.');
        redirect('/modules/personnel/organigramme.php?tab=grades');
    }
    redirect('/modules/personnel/organigramme.php');
}

$tab = $_GET['tab'] ?? 'arbre';
if (!in_array($tab, ['arbre','postes','grades'], true)) $tab = 'arbre';

$arbre        = unites_arbre();
$unites_plat  = unites_lister();
$postes       = postes_lister();
$grades       = grades_lister(false);
$candidats    = responsables_candidats();

$page_titre      = 'Organigramme';
$page_breadcrumb = 'RH · Organisation';
$module_actif    = 'PERSONNEL';
include __DIR__ . '/../../views/layout/header.php';

// Rendu récursif de l'arbre
function rendre_unite(array $u, array $postes, array $types, bool $peut_gerer): void {
    $postes_unite = array_filter($postes, fn($p) => (int)$p['unite_id'] === (int)$u['id']);
    ?>
    <div class="org-node">
      <div class="org-card">
        <div style="flex:1;">
          <div class="org-nom"><?= e($u['nom']) ?>
            <span class="org-type"><?= e($types[$u['type']] ?? $u['type']) ?></span>
            <?php if (!$u['actif']): ?><span class="org-type" style="background:#fee2e2;color:#991b1b;">inactif</span><?php endif; ?>
          </div>
          <?php if (!empty($u['responsable_nom'])): ?>
            <div class="org-resp"><i class="fa-solid fa-user-tie"></i> <?= e($u['responsable_nom']) ?></div>
          <?php endif; ?>
          <?php foreach ($postes_unite as $p): ?>
            <div class="org-poste"><i class="fa-solid fa-briefcase"></i> <?= e($p['intitule']) ?><?= !empty($p['grade_libelle']) ? ' · '.e($p['grade_libelle']) : '' ?></div>
          <?php endforeach; ?>
        </div>
        <?php if ($peut_gerer): ?>
        <form method="post" onsubmit="return confirm('Supprimer cette unité ?');" style="flex-shrink:0;">
          <?= csrf_field() ?><input type="hidden" name="action" value="unite_del"><input type="hidden" name="tab" value="arbre"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
          <button class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button>
        </form>
        <?php endif; ?>
      </div>
      <?php if (!empty($u['enfants'])): ?>
        <div class="org-enfants">
          <?php foreach ($u['enfants'] as $enf) rendre_unite($enf, $postes, $types, $peut_gerer); ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}
?>
<style>
.tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:22px;flex-wrap:wrap;}
.tab-btn{padding:10px 16px;font-size:.85rem;font-weight:600;color:var(--text-muted);background:none;border:none;cursor:pointer;border-bottom:2px solid transparent;display:inline-flex;align-items:center;gap:7px;text-decoration:none;}
.tab-btn:hover{color:var(--blue-600);}.tab-btn.active{color:var(--blue-600);border-bottom-color:var(--blue-600);}
.tab-pane{display:none;}.tab-pane.active{display:block;}
.org-node{margin-bottom:8px;}
.org-enfants{margin-left:26px;padding-left:18px;border-left:2px dashed var(--border);margin-top:8px;}
.org-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px 16px;display:flex;gap:12px;align-items:flex-start;}
.org-nom{font-weight:700;font-size:.92rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.org-type{font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;background:var(--blue-100);color:var(--blue-700);padding:2px 8px;border-radius:10px;}
.org-resp{font-size:.78rem;color:var(--text-muted);margin-top:4px;}
.org-poste{font-size:.78rem;color:var(--text);margin-top:3px;padding-left:2px;}
.crict-table td,.crict-table th{font-size:.84rem;}
</style>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Personnel</a>
  <div style="flex:1;"></div>
</div>

<div class="tabs">
  <a href="?tab=arbre"  class="tab-btn <?= $tab==='arbre'?'active':'' ?>"  data-tab="arbre"><i class="fa-solid fa-sitemap"></i> Organigramme</a>
  <a href="?tab=postes" class="tab-btn <?= $tab==='postes'?'active':'' ?>" data-tab="postes"><i class="fa-solid fa-briefcase"></i> Postes</a>
  <a href="?tab=grades" class="tab-btn <?= $tab==='grades'?'active':'' ?>" data-tab="grades"><i class="fa-solid fa-ranking-star"></i> Grades</a>
</div>

<!-- ===== ARBRE ===== -->
<div class="tab-pane <?= $tab==='arbre'?'active':'' ?>" id="pane-arbre">
  <div class="card"><div class="card-body">
    <?php if (empty($arbre)): ?>
      <p style="font-size:.85rem;color:var(--text-muted);">Aucune unité. <?= $peut_gerer ? 'Créez la première ci-dessous.' : '' ?></p>
    <?php else: foreach ($arbre as $u) rendre_unite($u, $postes, $types_unite, $peut_gerer); endif; ?>
  </div></div>

  <?php if ($peut_gerer): ?>
  <div class="card" style="margin-top:18px;"><div class="card-header"><h2>Nouvelle unité</h2></div><div class="card-body">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="unite_add"><input type="hidden" name="tab" value="arbre">
      <div class="form-row-3">
        <div class="form-group"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Code</label><input type="text" name="code" class="form-control"></div>
        <div class="form-group"><label class="form-label">Type</label>
          <select name="type" class="form-control"><?php foreach ($types_unite as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Unité parente</label>
          <select name="parent_id" class="form-control"><option value="">— Racine —</option>
            <?php foreach ($unites_plat as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['nom']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-group"><label class="form-label">Responsable</label>
          <select name="responsable_id" class="form-control"><option value="">—</option>
            <?php foreach ($candidats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nom']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Créer l'unité</button>
    </form>
  </div></div>
  <?php endif; ?>
</div>

<!-- ===== POSTES ===== -->
<div class="tab-pane <?= $tab==='postes'?'active':'' ?>" id="pane-postes">
  <div class="card"><div class="table-wrap"><table class="crict-table">
    <thead><tr><th>Intitulé</th><th>Unité</th><th>Grade</th><?php if($peut_gerer):?><th></th><?php endif;?></tr></thead>
    <tbody>
      <?php if (empty($postes)): ?><tr><td colspan="4" style="color:var(--text-muted);">Aucun poste.</td></tr><?php endif; ?>
      <?php foreach ($postes as $p): ?>
      <tr>
        <td style="font-weight:600;"><?= e($p['intitule']) ?></td>
        <td><?= e($p['unite_nom'] ?? '—') ?></td>
        <td><?= e($p['grade_libelle'] ?? '—') ?></td>
        <?php if ($peut_gerer): ?>
        <td style="text-align:right;"><form method="post" onsubmit="return confirm('Supprimer ?');"><?= csrf_field() ?><input type="hidden" name="action" value="poste_del"><input type="hidden" name="tab" value="postes"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button></form></td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div></div>

  <?php if ($peut_gerer): ?>
  <div class="card" style="margin-top:18px;"><div class="card-header"><h2>Nouveau poste</h2></div><div class="card-body">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="poste_add"><input type="hidden" name="tab" value="postes">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Intitulé *</label><input type="text" name="intitule" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Code</label><input type="text" name="code" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Unité</label>
          <select name="unite_id" class="form-control"><option value="">—</option>
            <?php foreach ($unites_plat as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['nom']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-group"><label class="form-label">Grade indicatif</label>
          <select name="grade_id" class="form-control"><option value="">—</option>
            <?php foreach ($grades as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['libelle']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Créer le poste</button>
    </form>
  </div></div>
  <?php endif; ?>
</div>

<!-- ===== GRADES ===== -->
<div class="tab-pane <?= $tab==='grades'?'active':'' ?>" id="pane-grades">
  <div class="card"><div class="table-wrap"><table class="crict-table">
    <thead><tr><th>Grade</th><th>Catégorie</th><th>Niveau</th><th>Statut</th></tr></thead>
    <tbody>
      <?php foreach ($grades as $g): ?>
      <tr>
        <td style="font-weight:600;"><?= e($g['libelle']) ?><?= !empty($g['code'])?' <span style="color:var(--text-muted);font-size:.78rem;">('.e($g['code']).')</span>':'' ?></td>
        <td><?= e($cat_grade[$g['categorie']] ?? $g['categorie']) ?></td>
        <td><?= (int)$g['niveau'] ?></td>
        <td><?= $g['actif'] ? 'Actif' : 'Inactif' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div></div>

  <?php if ($peut_gerer): ?>
  <div class="card" style="margin-top:18px;"><div class="card-header"><h2>Nouveau grade</h2></div><div class="card-body">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="grade_add"><input type="hidden" name="tab" value="grades">
      <div class="form-row-3">
        <div class="form-group"><label class="form-label">Libellé *</label><input type="text" name="libelle" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Code</label><input type="text" name="code" class="form-control"></div>
        <div class="form-group"><label class="form-label">Catégorie</label>
          <select name="categorie" class="form-control"><?php foreach ($cat_grade as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="form-group" style="max-width:200px;"><label class="form-label">Niveau (séniorité)</label><input type="number" name="niveau" class="form-control" min="0" value="0"></div>
      <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Ajouter le grade</button>
    </form>
  </div></div>
  <?php endif; ?>
</div>

<?php
$extra_scripts = '<script>
document.querySelectorAll(".tab-btn").forEach(b=>b.addEventListener("click",function(e){
  e.preventDefault();const t=this.dataset.tab;
  document.querySelectorAll(".tab-btn").forEach(x=>x.classList.remove("active"));
  document.querySelectorAll(".tab-pane").forEach(x=>x.classList.remove("active"));
  this.classList.add("active");document.getElementById("pane-"+t).classList.add("active");
  history.replaceState(null,"","?tab="+t);
}));
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
