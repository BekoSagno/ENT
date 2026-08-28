<?php
// modules/parametres/index.php — Gestion des Privilèges
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/privilege.php';

session_demarrer();
exiger_permission('PARAMETRES','VOIR');

$groupes = priv_groupes_lister(true);
$stats   = priv_stats();

$dept_labels = [
    'DIRECTION'           => ['Direction',         'var(--blue-600)', 'fa-star'],
    'ADMIN_GENERALE'      => ['Admin. Générale',   '#7c3aed',         'fa-building'],
    'ADMIN_FINANCIERE'    => ['Admin. Financière',  '#0891b2',         'fa-coins'],
    'RECHERCHE_SCIENTIFIQUE'=>['Recherche',         '#059669',         'fa-flask'],
    'SYSTEME'             => ['Système',            '#dc2626',         'fa-gear'],
];

$page_titre      = 'Gestion des Privilèges';
$page_breadcrumb = 'Paramètres Système · Privilèges';
$module_actif    = 'PARAMETRES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.kpi-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:24px;}
.kcard{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;}
.kcard .v{font-size:1.5rem;font-weight:800;line-height:1;margin-bottom:3px;}
.kcard .l{font-size:.72rem;color:var(--text-muted);}

.groupes-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-bottom:24px;}
.gc{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;transition:box-shadow .18s,transform .15s;}
.gc:hover{box-shadow:0 6px 22px rgba(13,50,120,.1);transform:translateY(-2px);}
.gc-top{height:4px;}
.gc-body{padding:16px 18px;}
.gc-nom{font-size:.93rem;font-weight:800;margin-bottom:4px;}
.gc-code{display:inline-flex;align-items:center;gap:4px;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;padding:2px 8px;border-radius:5px;background:var(--bg);color:var(--text-muted);border:1px solid var(--border);margin-bottom:8px;}
.gc-desc{font-size:.78rem;color:var(--text-muted);line-height:1.45;margin-bottom:10px;min-height:32px;}
.badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:20px;font-size:.69rem;font-weight:700;}
.gc-stats{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:10px;}
.gc-stat{background:var(--bg);border-radius:7px;padding:7px 10px;text-align:center;}
.gc-stat .sv{font-size:1.05rem;font-weight:800;}
.gc-stat .sl{font-size:.67rem;color:var(--text-muted);}
.gc-footer{padding:10px 16px;border-top:1px solid var(--border);background:var(--bg);display:flex;gap:6px;align-items:center;}

.niveau-dot{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;font-size:.65rem;font-weight:800;background:var(--blue-100);color:var(--blue-700);flex-shrink:0;}

.dept-section{margin-bottom:20px;}
.dept-header{display:flex;align-items:center;gap:8px;margin-bottom:10px;padding:8px 0;}
.dept-header .dh-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.dept-header h3{font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;}
</style>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;">
  <div style="flex:1;font-size:.8rem;color:var(--text-muted);">
    Gérez les groupes de rôles, la matrice de permissions et les droits individuels des utilisateurs.
  </div>
  <?php if (a_permission('PARAMETRES','CREER')): ?>
    <a href="groupe_form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouveau groupe</a>
  <?php endif; ?>
</div>

<!-- KPI -->
<div class="kpi-row">
  <div class="kcard"><div class="v" style="color:var(--blue-600);"><?= $stats['nb_groupes'] ?></div><div class="l">Groupes actifs</div></div>
  <div class="kcard"><div class="v" style="color:#059669;"><?= $stats['nb_modules'] ?></div><div class="l">Modules actifs</div></div>
  <div class="kcard"><div class="v" style="color:#7c3aed;"><?= $stats['nb_perms'] ?></div><div class="l">Permissions totales</div></div>
  <div class="kcard"><div class="v" style="color:#f59e0b;"><?= $stats['nb_spec'] ?></div><div class="l">Overrides utilisateurs</div></div>
</div>

<!-- Groupes par département -->
<?php
$par_dept = [];
foreach ($groupes as $g) {
    $par_dept[$g['departement']][] = $g;
}
?>
<?php foreach ($dept_labels as $dept_code => [$dept_lbl, $dept_color, $dept_ico]):
  if (empty($par_dept[$dept_code])) continue;
?>
<div class="dept-section">
  <div class="dept-header">
    <div class="dh-icon" style="background:<?= $dept_color ?>22;color:<?= $dept_color ?>;">
      <i class="fa-solid <?= $dept_ico ?>"></i>
    </div>
    <h3 style="color:<?= $dept_color ?>;"><?= $dept_lbl ?></h3>
    <span style="font-size:.72rem;color:var(--text-muted);">— <?= count($par_dept[$dept_code]) ?> groupe<?= count($par_dept[$dept_code])>1?'s':'' ?></span>
  </div>

  <div class="groupes-grid">
  <?php foreach ($par_dept[$dept_code] as $g):
    $actif = (bool)$g['actif'];
  ?>
  <div class="gc" style="opacity:<?= $actif?1:.65 ?>;">
    <div class="gc-top" style="background:<?= $actif?$dept_color:'#9ca3af' ?>;"></div>
    <div class="gc-body">
      <div style="display:flex;align-items:flex-start;gap:8px;">
        <div style="flex:1;">
          <div class="gc-nom"><?= e($g['libelle']) ?></div>
          <div class="gc-code"><i class="fa-solid fa-tag"></i> <?= e($g['code']) ?></div>
        </div>
        <div class="niveau-dot" title="Niveau hiérarchique <?= $g['niveau'] ?>"><?= $g['niveau'] ?></div>
      </div>
      <div class="gc-desc"><?= $g['description'] ? e(mb_strimwidth($g['description'],0,80,'…')) : '<em>Aucune description</em>' ?></div>
      <div style="display:flex;gap:5px;flex-wrap:wrap;">
        <?php if ($actif): ?>
          <span class="badge" style="background:#dcfce7;color:#166534;"><i class="fa-solid fa-circle-check"></i> Actif</span>
        <?php else: ?>
          <span class="badge" style="background:#f3f4f6;color:#6b7280;"><i class="fa-solid fa-pause"></i> Inactif</span>
        <?php endif; ?>
        <?php if ($g['code'] === 'SUPER_ADMIN'): ?>
          <span class="badge" style="background:#fef3c7;color:#92400e;"><i class="fa-solid fa-shield-halved"></i> Protégé</span>
        <?php endif; ?>
      </div>
      <div class="gc-stats">
        <div class="gc-stat">
          <div class="sv" style="color:var(--blue-600);"><?= (int)$g['nb_utilisateurs'] ?></div>
          <div class="sl">Utilisateur<?= $g['nb_utilisateurs']>1?'s':'' ?></div>
        </div>
        <div class="gc-stat">
          <div class="sv" style="color:#7c3aed;"><?= (int)$g['nb_permissions'] ?></div>
          <div class="sl">Permission<?= $g['nb_permissions']>1?'s':'' ?></div>
        </div>
      </div>
    </div>
    <div class="gc-footer">
      <a href="matrice.php?groupe_id=<?= $g['id'] ?>" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-table-cells"></i> Permissions
      </a>
      <?php if ($g['code'] !== 'SUPER_ADMIN' && a_permission('PARAMETRES','MODIFIER')): ?>
        <a href="groupe_form.php?id=<?= $g['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier">
          <i class="fa-solid fa-pen"></i>
        </a>
        <form method="POST" action="actions.php" style="display:inline;"
              onsubmit="return confirm('<?= $actif?'Désactiver':'Activer' ?> ce groupe ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action"   value="<?= $actif?'desactiver':'activer' ?>_groupe">
          <input type="hidden" name="groupe_id" value="<?= $g['id'] ?>">
          <button type="submit" class="btn btn-secondary btn-sm btn-icon"
                  title="<?= $actif?'Désactiver':'Activer' ?>"
                  style="<?= $actif?'':'background:#dcfce7;border-color:#86efac;color:#166534;' ?>">
            <i class="fa-solid <?= $actif?'fa-ban':'fa-circle-play' ?>"></i>
          </button>
        </form>
      <?php endif; ?>
      <?php if ($g['code'] !== 'SUPER_ADMIN' && a_permission('PARAMETRES','SUPPRIMER') && (int)$g['nb_utilisateurs'] === 0): ?>
        <form method="POST" action="actions.php" style="display:inline;margin-left:auto;"
              onsubmit="return confirm('Supprimer définitivement ce groupe ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action"    value="supprimer_groupe">
          <input type="hidden" name="groupe_id" value="<?= $g['id'] ?>">
          <button type="submit" class="btn btn-sm btn-icon"
                  style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;"
                  title="Supprimer">
            <i class="fa-solid fa-trash"></i>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
