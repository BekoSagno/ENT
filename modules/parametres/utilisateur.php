<?php
// modules/parametres/utilisateur.php — Permissions spécifiques d'un utilisateur
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/privilege.php';

session_demarrer();
exiger_permission('PARAMETRES','VOIR');

$uid  = (int)($_GET['user_id'] ?? 0);
if (!$uid) { set_flash('error','Utilisateur introuvable.'); redirect('/modules/parametres/index.php'); }

$db = get_db();
$su = $db->prepare("
    SELECT u.*, g.libelle AS groupe_libelle, g.code AS groupe_code
    FROM utilisateurs u
    JOIN groupes_utilisateurs g ON g.id = u.groupe_id
    WHERE u.id=?
");
$su->execute([$uid]);
$utilisateur = $su->fetch();
if (!$utilisateur) { set_flash('error','Utilisateur introuvable.'); redirect('/modules/parametres/index.php'); }

$peut_modifier = a_permission('PARAMETRES','MODIFIER');

// POST — sauvegarder les overrides
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && $peut_modifier) {
    $action = clean($_POST['action'] ?? '');

    if ($action === 'sauvegarder_overrides') {
        // Construire tableau d'overrides : chaque perm peut être accordée (1), refusée (0) ou sans override (supprimée)
        $overrides = [];
        $perm_actions  = $_POST['perm_action']  ?? [];   // [perm_id => 'accorder'|'refuser'|'supprimer']
        $perm_raisons  = $_POST['perm_raison']  ?? [];

        foreach ($perm_actions as $pid => $act) {
            $pid = (int)$pid;
            if ($act === 'supprimer') continue; // Pas d'override pour cet ID
            $overrides[$pid] = [
                'accorde' => $act === 'accorder' ? 1 : 0,
                'raison'  => trim($perm_raisons[$pid] ?? ''),
            ];
        }

        $res = priv_sauvegarder_perms_specifiques($uid, $overrides, user_id());
        set_flash($res['succes']?'success':'error', $res['succes']?'Permissions spécifiques enregistrées.':$res['message']);
        header('Location: utilisateur.php?user_id='.$uid); exit;
    }

    if ($action === 'supprimer_override') {
        $oid = (int)($_POST['override_id'] ?? 0);
        priv_perm_spec_supprimer($oid);
        set_flash('success','Override supprimé.');
        header('Location: utilisateur.php?user_id='.$uid); exit;
    }
}

$modules_data  = priv_modules_avec_permissions();
$matrice_groupe = priv_matrice_groupe((int)$utilisateur['groupe_id']);
$specs          = priv_perms_specifiques($uid);

// Indexer les overrides : [perm_id => override]
$overrides_idx = [];
foreach ($specs as $sp) $overrides_idx[(int)$sp['permission_id']] = $sp;

$actions_ordre  = ['VOIR','CREER','MODIFIER','SUPPRIMER','VALIDER','EXPORTER'];
$actions_labels = [
    'VOIR'      => ['Voir',      'fa-eye',          '#0891b2'],
    'CREER'     => ['Créer',     'fa-plus',          '#059669'],
    'MODIFIER'  => ['Modifier',  'fa-pen',           '#7c3aed'],
    'SUPPRIMER' => ['Supprimer', 'fa-trash',         '#dc2626'],
    'VALIDER'   => ['Valider',   'fa-check-double',  '#d97706'],
    'EXPORTER'  => ['Exporter',  'fa-download',      '#64748b'],
];

$initiales = strtoupper(
    mb_substr($utilisateur['prenoms']??'',0,1).
    mb_substr($utilisateur['nom']??'',0,1)
);

$page_titre      = 'Droits — '.e($utilisateur['prenoms'].' '.$utilisateur['nom']);
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Privilèges</a> · Droits utilisateur';
$module_actif    = 'PARAMETRES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.uh{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;}
.uh-av{width:44px;height:44px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:800;flex-shrink:0;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700;}

/* Légende */
.legend{display:flex;gap:12px;flex-wrap:wrap;font-size:.76rem;margin-bottom:14px;}
.leg-item{display:flex;align-items:center;gap:5px;color:var(--text-muted);}

/* Tableau */
.perm-table{width:100%;border-collapse:collapse;font-size:.84rem;}
.perm-table thead th{background:var(--bg);color:var(--text-muted);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:9px 12px;border-bottom:2px solid var(--border);white-space:nowrap;vertical-align:middle;}
.perm-table tbody tr{border-bottom:1px solid var(--border);transition:background .1s;}
.perm-table tbody tr:hover{background:var(--blue-50);}
.perm-table td{padding:10px 12px;vertical-align:middle;}

/* Cellule permission */
.perm-cell{display:flex;flex-direction:column;align-items:center;gap:3px;}
.orig-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.orig-dot.on{background:#22c55e;}
.orig-dot.off{background:#e5e7eb;border:1px solid #d1d5db;}

/* Sélecteur d'override */
.ov-select{
  font-size:.72rem;padding:3px 7px;border-radius:6px;
  border:1.5px solid var(--border);background:var(--surface);
  color:var(--text-muted);cursor:pointer;
  transition:all .13s;
}
.ov-select.accorder{background:#dcfce7;border-color:#86efac;color:#166534;}
.ov-select.refuser  {background:#fee2e2;border-color:#fecaca;color:#991b1b;}

/* Résultat final */
.result-cell{text-align:center;}
.result-ok{color:#22c55e;font-size:.9rem;}
.result-no{color:#dc2626;font-size:.9rem;}
.result-over-ok{color:#059669;font-size:.9rem;font-weight:800;}
.result-over-no{color:#dc2626;font-size:.9rem;font-weight:800;}

.form-actions{position:sticky;bottom:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid var(--border);padding:13px 0;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;}
</style>

<div style="margin-bottom:14px;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
</div>

<!-- En-tête utilisateur -->
<div class="uh">
  <div class="uh-av"><?= e($initiales) ?></div>
  <div style="flex:1;min-width:0;">
    <div style="font-size:1rem;font-weight:800;"><?= e($utilisateur['prenoms'].' '.$utilisateur['nom']) ?></div>
    <div style="font-size:.78rem;color:var(--text-muted);"><?= e($utilisateur['matricule']??'') ?> · <?= e($utilisateur['email']??'') ?></div>
    <div style="margin-top:5px;display:flex;gap:5px;flex-wrap:wrap;">
      <span class="badge" style="background:var(--blue-50);color:var(--blue-700);">
        <i class="fa-solid fa-users"></i> <?= e($utilisateur['groupe_libelle']) ?>
      </span>
      <?php if (!empty($overrides_idx)): ?>
        <span class="badge" style="background:#fef3c7;color:#92400e;">
          <i class="fa-solid fa-star"></i> <?= count($overrides_idx) ?> override<?= count($overrides_idx)>1?'s':'' ?> actif<?= count($overrides_idx)>1?'s':'' ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
  <div style="text-align:right;">
    <a href="matrice.php?groupe_id=<?= $utilisateur['groupe_id'] ?>" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-table-cells"></i> Voir la matrice du groupe
    </a>
  </div>
</div>

<!-- Légende -->
<div class="legend">
  <div class="leg-item"><span class="orig-dot on"></span> Accordé par le groupe</div>
  <div class="leg-item"><span class="orig-dot off"></span> Refusé par le groupe</div>
  <div class="leg-item"><span style="background:#dcfce7;color:#166534;padding:1px 7px;border-radius:4px;font-weight:700;font-size:.72rem;">+ Accordé</span> Override : permission ajoutée</div>
  <div class="leg-item"><span style="background:#fee2e2;color:#991b1b;padding:1px 7px;border-radius:4px;font-weight:700;font-size:.72rem;">− Refusé</span> Override : permission retirée</div>
</div>

<div style="background:var(--blue-50);border:1px solid var(--blue-200);border-radius:9px;padding:11px 16px;margin-bottom:18px;font-size:.82rem;color:var(--blue-800);">
  <i class="fa-solid fa-info-circle" style="margin-right:5px;"></i>
  Les overrides <strong>modifient individuellement</strong> les droits de cet utilisateur, en plus ou en moins de ce que donne son groupe. Si vous ne souhaitez pas d'override pour une permission, laissez sur <em>Aucun</em>.
</div>

<form method="POST" id="overrideForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="sauvegarder_overrides">

  <div style="overflow-x:auto;margin-bottom:24px;">
  <table class="perm-table">
    <thead>
      <tr>
        <th>Module</th>
        <th>Permission</th>
        <th style="text-align:center;">Groupe</th>
        <th style="text-align:center;">Override</th>
        <th>Motif override</th>
        <th style="text-align:center;">Résultat final</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($modules_data as $mod):
      foreach ($mod['permissions'] as $p):
        $pid     = (int)$p['id'];
        $grp_ok  = ($matrice_groupe[$pid] ?? 0) === 1;
        $override = $overrides_idx[$pid] ?? null;
        $ov_val  = $override ? (int)$override['accorde'] : -1; // -1 = sans override
        // Résultat final
        if ($override !== null) {
            $final = $ov_val === 1;
            $is_override = true;
        } else {
            $final = $grp_ok;
            $is_override = false;
        }
    ?>
    <tr>
      <td style="font-size:.8rem;white-space:nowrap;">
        <div style="display:flex;align-items:center;gap:6px;">
          <i class="fa-solid <?= e($mod['icone']) ?>" style="color:var(--blue-400);width:14px;text-align:center;"></i>
          <?= e($mod['libelle']) ?>
        </div>
      </td>
      <td>
        <div style="font-size:.82rem;font-weight:600;"><?= e($p['action']) ?></div>
        <div style="font-size:.7rem;color:var(--text-muted);"><?= e(mb_strimwidth($p['libelle'],0,40,'…')) ?></div>
      </td>
      <td style="text-align:center;">
        <?php if ($grp_ok): ?>
          <i class="fa-solid fa-check-circle" style="color:#22c55e;" title="Accordé par le groupe"></i>
        <?php else: ?>
          <i class="fa-solid fa-times-circle" style="color:#d1d5db;" title="Non accordé par le groupe"></i>
        <?php endif; ?>
      </td>
      <td style="text-align:center;">
        <?php if ($peut_modifier): ?>
        <select name="perm_action[<?= $pid ?>]" class="ov-select <?= $ov_val===1?'accorder':($ov_val===0?'refuser':'') ?>"
                onchange="this.className='ov-select '+(this.value==='accorder'?'accorder':this.value==='refuser'?'refuser':'')">
          <option value="supprimer" <?= $ov_val===-1?'selected':'' ?>>Aucun</option>
          <option value="accorder"  <?= $ov_val===1?'selected':'' ?>>+ Accorder</option>
          <option value="refuser"   <?= $ov_val===0?'selected':'' ?>>− Refuser</option>
        </select>
        <?php else: ?>
          <?php if ($ov_val===1): ?><span style="color:#059669;font-weight:700;font-size:.78rem;">+ Accordé</span>
          <?php elseif ($ov_val===0): ?><span style="color:#dc2626;font-weight:700;font-size:.78rem;">− Refusé</span>
          <?php else: ?><span style="color:var(--text-muted);font-size:.78rem;">—</span>
          <?php endif; ?>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($peut_modifier): ?>
        <input type="text" name="perm_raison[<?= $pid ?>]"
               class="form-control" style="font-size:.75rem;padding:4px 8px;"
               value="<?= e($override['raison'] ?? '') ?>"
               placeholder="Motif (optionnel)">
        <?php else: ?>
          <span style="font-size:.78rem;color:var(--text-muted);"><?= e($override['raison'] ?? '—') ?></span>
        <?php endif; ?>
      </td>
      <td style="text-align:center;">
        <?php if ($is_override): ?>
          <?php if ($final): ?>
            <i class="fa-solid fa-shield-check" style="color:#059669;" title="Accordé via override"></i>
          <?php else: ?>
            <i class="fa-solid fa-shield-xmark" style="color:#dc2626;" title="Refusé via override"></i>
          <?php endif; ?>
        <?php else: ?>
          <?php if ($final): ?>
            <i class="fa-regular fa-check-circle" style="color:#22c55e;" title="Accordé (groupe)"></i>
          <?php else: ?>
            <i class="fa-regular fa-times-circle" style="color:#d1d5db;" title="Refusé (groupe)"></i>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; endforeach; ?>
    </tbody>
  </table>
  </div>

  <?php if ($peut_modifier): ?>
  <div class="form-actions">
    <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Annuler</a>
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid fa-floppy-disk"></i> Enregistrer les overrides
    </button>
  </div>
  <?php endif; ?>
</form>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
