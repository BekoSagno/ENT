<?php
// modules/parametres/matrice.php — Matrice permissions × modules pour un groupe
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/privilege.php';

session_demarrer();
exiger_permission('PARAMETRES','VOIR');

$gid    = (int)($_GET['groupe_id'] ?? 0);
$groupe = $gid ? priv_groupe_charger($gid) : null;
if (!$groupe) { set_flash('error','Groupe introuvable.'); redirect('/modules/parametres/index.php'); }

$peut_modifier = a_permission('PARAMETRES','MODIFIER');
$est_super     = $groupe['code'] === 'SUPER_ADMIN';

// POST — sauvegarder la matrice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && $peut_modifier && !$est_super) {
    $action = clean($_POST['action'] ?? '');

    if ($action === 'sauvegarder_matrice') {
        $accordees = array_map('intval', $_POST['permissions'] ?? []);
        $res = priv_sauvegarder_matrice_groupe($gid, $accordees);
        set_flash($res['succes']?'success':'error', $res['succes']?'Permissions enregistrées.':$res['message']);
        header('Location: matrice.php?groupe_id='.$gid); exit;
    }

    if ($action === 'copier_depuis') {
        $source = (int)($_POST['source_groupe'] ?? 0);
        $res = priv_copier_permissions($source, $gid);
        set_flash($res['succes']?'success':'error', $res['succes']?'Permissions copiées.':$res['message']);
        header('Location: matrice.php?groupe_id='.$gid); exit;
    }
}

$modules_data  = priv_modules_avec_permissions();
$matrice       = priv_matrice_groupe($gid);
$tous_groupes  = priv_groupes_lister();

// Actions (colonnes) dans l'ordre affiché
$actions_ordre = ['VOIR','CREER','MODIFIER','SUPPRIMER','VALIDER','EXPORTER'];
$actions_labels = [
    'VOIR'      => ['Voir',      'fa-eye',         '#0891b2','#e0f2fe'],
    'CREER'     => ['Créer',     'fa-plus',         '#059669','#dcfce7'],
    'MODIFIER'  => ['Modifier',  'fa-pen',          '#7c3aed','#ede9fe'],
    'SUPPRIMER' => ['Supprimer', 'fa-trash',        '#dc2626','#fee2e2'],
    'VALIDER'   => ['Valider',   'fa-check-double', '#d97706','#fef3c7'],
    'EXPORTER'  => ['Exporter',  'fa-download',     '#64748b','#f1f5f9'],
];

// Permissions "spéciales" : toute action hors grille standard
// (ex. MODIFIER_SENSIBLE, GERER_REMUNERATION). Affichées à part,
// mais SOUMISES dans le même formulaire pour ne pas être révoquées.
$perms_speciales = [];
foreach ($modules_data as $mod) {
    foreach ($mod['permissions'] as $p) {
        if (!in_array($p['action'], $actions_ordre, true)) {
            $perms_speciales[] = $p + [
                'module_libelle' => $mod['libelle'],
                'module_icone'   => $mod['icone'] ?? 'fa-cube',
            ];
        }
    }
}

$page_titre      = 'Permissions — '.e($groupe['libelle']);
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Privilèges</a> · '.e($groupe['libelle']);
$module_actif    = 'PARAMETRES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
/* En-tête groupe */
.gh{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
.gh-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700;}

/* Barre d'outils */
.toolbar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}

/* Matrice */
.matrice-wrap{overflow-x:auto;margin-bottom:24px;}
.matrice{width:100%;border-collapse:collapse;}
.matrice th,.matrice td{padding:0;vertical-align:middle;}

/* En-tête colonnes */
.mat-th-action{
  padding:8px 10px;text-align:center;
  font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
  border-bottom:2px solid var(--border);
  white-space:nowrap;min-width:72px;
}
.mat-th-module{
  padding:10px 14px;text-align:left;
  font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
  color:var(--text-muted);border-bottom:2px solid var(--border);
  min-width:200px;
}

/* Lignes */
.mat-row-module td{
  border-bottom:1px solid var(--border);
}
.mat-row-module:hover td{background:var(--blue-50);}
.mat-module-name{
  padding:12px 14px;font-size:.85rem;font-weight:600;
  display:flex;align-items:center;gap:8px;
}
.mat-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.78rem;flex-shrink:0;background:var(--blue-50);color:var(--blue-600);}

/* Cellule checkbox */
.mat-cell{text-align:center;padding:8px 4px;}

/* Checkbox toggle stylisé */
.perm-toggle{
  display:inline-flex;align-items:center;justify-content:center;
  width:28px;height:28px;border-radius:7px;cursor:pointer;
  border:1.5px solid var(--border);background:var(--surface);
  transition:all .15s;position:relative;
}
.perm-toggle input{position:absolute;opacity:0;width:0;height:0;}
.perm-toggle.checked{background:var(--blue-600);border-color:var(--blue-600);}
.perm-toggle.checked i{color:#fff;}
.perm-toggle:not(.checked) i{color:var(--border);}
.perm-toggle:hover:not(.checked){border-color:var(--blue-300);background:var(--blue-50);}
.perm-toggle:hover:not(.checked) i{color:var(--blue-400);}

/* Super admin lock */
.lock-cell{text-align:center;padding:8px 4px;}
.lock-icon{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;color:#fbbf24;}

/* Boutons sélection rapide */
.quick-btns{display:flex;gap:6px;flex-wrap:wrap;}
.qbtn{padding:5px 10px;border-radius:6px;border:1.5px solid var(--border);background:var(--surface);font-size:.73rem;font-weight:600;color:var(--text-muted);cursor:pointer;transition:all .13s;}
.qbtn:hover{border-color:var(--blue-400);color:var(--blue-600);}
.qbtn.active{background:var(--blue-600);border-color:var(--blue-600);color:#fff;}

/* Footer sticky */
.mat-footer{position:sticky;bottom:0;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid var(--border);padding:13px 0;display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap;}
.mat-counter{font-size:.8rem;color:var(--text-muted);}
.mat-counter strong{color:var(--blue-600);}
</style>

<!-- En-tête groupe -->
<div style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  <?php if ($peut_modifier && !$est_super): ?>
    <a href="groupe_form.php?id=<?= $gid ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Modifier le groupe</a>
  <?php endif; ?>
</div>

<div class="gh">
  <div style="flex:1;min-width:0;">
    <div style="font-size:1.05rem;font-weight:800;"><?= e($groupe['libelle']) ?></div>
    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:5px;">
      <span class="gh-badge" style="background:var(--bg);color:var(--text-muted);border:1px solid var(--border);font-family:var(--mono);"><?= e($groupe['code']) ?></span>
      <?php if ($est_super): ?>
        <span class="gh-badge" style="background:#fef3c7;color:#92400e;"><i class="fa-solid fa-shield-halved"></i> Accès total — non modifiable</span>
      <?php endif; ?>
      <?php if (!$groupe['actif']): ?>
        <span class="gh-badge" style="background:#f3f4f6;color:#6b7280;"><i class="fa-solid fa-pause"></i> Inactif</span>
      <?php endif; ?>
    </div>
  </div>
  <div style="font-size:.8rem;color:var(--text-muted);text-align:right;">
    <div><strong id="cptPerms" style="color:var(--blue-600);font-size:1.1rem;">—</strong> permissions accordées</div>
    <div style="font-size:.72rem;">sur <?= array_sum(array_map(fn($m)=>count($m['permissions']),$modules_data)) ?> au total</div>
  </div>
</div>

<?php if ($est_super): ?>
<div class="alert alert-info" style="margin-bottom:18px;">
  <i class="fa-solid fa-shield-halved" style="flex-shrink:0;"></i>
  Le Super Administrateur possède automatiquement <strong>toutes les permissions</strong>. Sa matrice ne peut pas être modifiée.
</div>
<?php else: ?>

<!-- Barre d'outils -->
<div class="toolbar">
  <div class="quick-btns">
    <button type="button" class="qbtn" onclick="toutCocher()"><i class="fa-solid fa-check-double"></i> Tout cocher</button>
    <button type="button" class="qbtn" onclick="toutDecocher()"><i class="fa-solid fa-xmark"></i> Tout décocher</button>
  </div>
  <div style="font-size:.8rem;color:var(--text-muted);margin:0 4px;">·</div>
  <div class="quick-btns">
    <?php foreach ($actions_labels as $act => [$lbl,$ico,$col,$bg]): ?>
    <button type="button" class="qbtn" onclick="cocherAction('<?= $act ?>')"
            style="border-color:<?= $col ?>;color:<?= $col ?>;">
      <i class="fa-solid <?= $ico ?>"></i> <?= $lbl ?>
    </button>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($tous_groupes)): ?>
  <div style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <span style="font-size:.78rem;color:var(--text-muted);">Copier depuis :</span>
    <form method="POST" style="display:flex;gap:6px;align-items:center;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="copier_depuis">
      <select name="source_groupe" class="form-control" style="max-width:180px;font-size:.8rem;padding:5px 10px;">
        <option value="">— Choisir le groupe source —</option>
        <?php foreach ($tous_groupes as $tg): if ($tg['id'] === $gid) continue; ?>
          <option value="<?= $tg['id'] ?>"><?= e($tg['libelle']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Écraser les permissions actuelles ?')">
        <i class="fa-solid fa-copy"></i> Copier
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Matrice -->
<form method="POST" id="matriceForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="sauvegarder_matrice">

  <div class="matrice-wrap">
  <table class="matrice">
    <thead>
      <tr>
        <th class="mat-th-module">Module</th>
        <?php foreach ($actions_ordre as $act):
          [$lbl,$ico,$col,$bg] = $actions_labels[$act];
        ?>
        <th class="mat-th-action" style="color:<?= $col ?>;">
          <div style="background:<?= $bg ?>;border-radius:6px;padding:4px 6px;display:inline-flex;flex-direction:column;align-items:center;gap:2px;">
            <i class="fa-solid <?= $ico ?>"></i>
            <span><?= $lbl ?></span>
          </div>
        </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($modules_data as $mod):
      // Indexer les permissions de ce module par action
      $perm_par_action = [];
      foreach ($mod['permissions'] as $p) {
          $perm_par_action[$p['action']] = $p;
      }
    ?>
    <tr class="mat-row-module">
      <td>
        <div class="mat-module-name">
          <div class="mat-icon"><i class="fa-solid <?= e($mod['icone']) ?>"></i></div>
          <?= e($mod['libelle']) ?>
        </div>
      </td>
      <?php foreach ($actions_ordre as $act):
        $perm = $perm_par_action[$act] ?? null;
      ?>
      <td class="mat-cell">
        <?php if ($perm):
          $pid = (int)$perm['id'];
          if ($est_super): ?>
            <div class="lock-icon" title="Toujours accordé"><i class="fa-solid fa-check" style="color:#22c55e;"></i></div>
          <?php else:
            $is_checked = ($matrice[$pid] ?? 0) === 1;
          ?>
            <label class="perm-toggle <?= $is_checked?'checked':'' ?>"
                   title="<?= e($perm['libelle']) ?>"
                   onclick="togglePerm(this)">
              <input type="checkbox" name="permissions[]" value="<?= $pid ?>"
                     <?= $is_checked?'checked':'' ?> <?= $est_super?'disabled':'' ?>>
              <i class="fa-solid fa-check" style="font-size:.7rem;"></i>
            </label>
          <?php endif; ?>
        <?php else: ?>
          <span style="color:var(--border);">—</span>
        <?php endif; ?>
      </td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <?php if (!$est_super && !empty($perms_speciales)): ?>
  <div class="card" style="margin-bottom:20px;border-left:4px solid #f59e0b;">
    <div class="card-header"><h2 style="font-size:.95rem;"><i class="fa-solid fa-shield-halved" style="color:#d97706;"></i>&nbsp; Permissions spéciales</h2></div>
    <div class="card-body">
      <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:14px;">
        Permissions sensibles, hors grille standard. À accorder avec prudence.
      </p>
      <?php foreach ($perms_speciales as $sp):
        $pid = (int)$sp['id'];
        $is_checked = ($matrice[$pid] ?? 0) === 1;
      ?>
      <div style="display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border);">
        <label class="perm-toggle <?= $is_checked?'checked':'' ?>" onclick="togglePerm(this)" title="<?= e($sp['libelle']) ?>">
          <input type="checkbox" name="permissions[]" value="<?= $pid ?>" <?= $is_checked?'checked':'' ?>>
          <i class="fa-solid fa-check" style="font-size:.7rem;"></i>
        </label>
        <div style="min-width:0;">
          <div style="font-weight:600;font-size:.86rem;"><?= e($sp['libelle']) ?></div>
          <div style="font-size:.74rem;color:var(--text-muted);">
            <i class="fa-solid <?= e($sp['module_icone']) ?>"></i> <?= e($sp['module_libelle']) ?>
            · <span style="font-family:var(--mono);"><?= e($sp['action']) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$est_super && $peut_modifier): ?>
  <div class="mat-footer">
    <div class="mat-counter">
      <strong id="cptFooter">—</strong> permissions sélectionnées
    </div>
    <div style="display:flex;gap:9px;">
      <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Annuler</a>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk"></i> Enregistrer les permissions
      </button>
    </div>
  </div>
  <?php endif; ?>
</form>

<?php
$extra_scripts = '<script>
// Toggle visuel
function togglePerm(label) {
  const cb = label.querySelector("input");
  cb.checked = !cb.checked;
  label.classList.toggle("checked", cb.checked);
  updateCounter();
}

// Compteur
function updateCounter() {
  const n = document.querySelectorAll(".perm-toggle input:checked").length;
  ["cptPerms","cptFooter"].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = n;
  });
}

// Sélection rapide
function toutCocher() {
  document.querySelectorAll(".perm-toggle").forEach(l => {
    l.querySelector("input").checked = true;
    l.classList.add("checked");
  });
  updateCounter();
}
function toutDecocher() {
  document.querySelectorAll(".perm-toggle").forEach(l => {
    l.querySelector("input").checked = false;
    l.classList.remove("checked");
  });
  updateCounter();
}
function cocherAction(action) {
  // Cherche toutes les checkboxes dont la valeur correspond à une permission de cette action
  // On sappuie sur les titres des labels pour cibler
  document.querySelectorAll(".perm-toggle").forEach(l => {
    const title = l.getAttribute("title") || "";
    const act = title.split("–")[0].trim().toUpperCase();
    // Extraire laction du libellé: "VOIR – Gestion des..." → "VOIR"
    if (title.toUpperCase().includes(action.toUpperCase())) {
      l.querySelector("input").checked = true;
      l.classList.add("checked");
    }
  });
  updateCounter();
}

// Confirmation si modifications non sauvegardées
let dirty = false;
document.querySelectorAll(".perm-toggle").forEach(l => {
  l.addEventListener("click", () => { dirty = true; });
});
document.getElementById("matriceForm")?.addEventListener("submit", () => { dirty = false; });
window.addEventListener("beforeunload", e => {
  if (dirty) { e.preventDefault(); e.returnValue = ""; }
});

updateCounter();
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>