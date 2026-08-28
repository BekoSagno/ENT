<?php
// modules/subventions/caisse_form.php — Créer / modifier une caisse
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/subvention.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'CREER');

$cid    = (int)($_GET['id'] ?? 0);
$caisse = $cid ? caisse_charger($cid) : null;
$mode   = $caisse ? 'modifier' : 'creer';
$cats   = categories_lister();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $data = [
        'code_caisse'        => clean($_POST['code_caisse']        ?? ''),
        'libelle'            => clean($_POST['libelle']            ?? ''),
        'description'        => clean($_POST['description']        ?? ''),
        'source_financement' => clean($_POST['source_financement'] ?? ''),
        'montant_initial'    => clean($_POST['montant_initial']    ?? '0'),
        'trimestre'          => clean($_POST['trimestre']          ?? '1'),
        'annee'              => clean($_POST['annee']              ?? date('Y')),
        'date_debut'         => clean($_POST['date_debut']         ?? ''),
        'date_fin'           => clean($_POST['date_fin']           ?? ''),
        'statut'             => clean($_POST['statut']             ?? 'active'),
        'lignes'             => $_POST['lignes'] ?? [],
    ];

    if (empty($data['libelle']) || empty($data['montant_initial']) || empty($data['code_caisse'])) {
        set_flash('error', 'Code, libellé et montant sont obligatoires.');
    } else {
        $res = $mode === 'creer' ? caisse_creer($data) : caisse_modifier($cid, $data);
        if ($res['succes']) {
            set_flash('success', $mode === 'creer' ? 'Caisse créée.' : 'Caisse modifiée.');
            header('Location: caisse_voir.php?id='.($mode==='creer'?$res['id']:$cid));
            exit;
        }
        set_flash('error', $res['message'] ?? 'Erreur.');
    }
}

// Lignes actuelles
$lignes_existantes = [];
if ($caisse) {
    foreach ($caisse['lignes'] as $l) {
        $lignes_existantes[$l['categorie_id']] = $l['montant_alloue'];
    }
}

$page_titre      = $mode === 'creer' ? 'Nouvelle caisse' : 'Modifier la caisse';
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Subventions</a> · '
                 . ($mode === 'creer' ? 'Nouvelle caisse' : e($caisse['libelle']??''));
$module_actif    = 'SUBVENTIONS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.fsec{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px;}
.fsec-hd{display:flex;align-items:center;gap:10px;padding:13px 20px;background:var(--bg);border-bottom:1px solid var(--border);}
.fsec-ico{width:32px;height:32px;border-radius:8px;background:var(--blue-100);color:var(--blue-600);display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.fsec-hd h3{font-size:.88rem;font-weight:700;margin:0;}
.fsec-hd .sub{font-size:.71rem;color:var(--text-muted);}
.fsec-bd{padding:20px 22px;}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.fg3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
@media(max-width:640px){.fg2,.fg3{grid-template-columns:1fr;}}
.ligne-row{display:grid;grid-template-columns:2fr 1fr;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);}
.ligne-row:last-child{border-bottom:none;}
.form-actions{position:sticky;bottom:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid var(--border);padding:13px 0;display:flex;gap:10px;justify-content:flex-end;}
</style>

<div style="margin-bottom:14px;">
  <a href="<?= $caisse ? 'caisse_voir.php?id='.$cid : 'index.php' ?>" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-arrow-left"></i> Retour
  </a>
</div>

<form method="POST" id="caisseForm">
  <?= csrf_field() ?>

  <!-- Infos générales -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-piggy-bank"></i></div>
      <div><h3>Informations de la caisse</h3><div class="sub">Code, libellé, trimestre, source</div></div>
    </div>
    <div class="fsec-bd">
      <div class="fg3">
        <div class="form-group">
          <label class="form-label">Code caisse <span style="color:var(--danger);">*</span></label>
          <input type="text" name="code_caisse" class="form-control" required
                 value="<?= e($caisse['code_caisse'] ?? '') ?>"
                 placeholder="Ex : T1-2026"
                 <?= $mode==='modifier'?'readonly style="background:var(--bg);"':'' ?>>
        </div>
        <div class="form-group">
          <label class="form-label">Trimestre <span style="color:var(--danger);">*</span></label>
          <select name="trimestre" class="form-control" required>
            <?php for ($t=1;$t<=4;$t++): ?>
              <option value="<?= $t ?>" <?= ($caisse['trimestre']??1)===$t?'selected':'' ?>>Trimestre <?= $t ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Année <span style="color:var(--danger);">*</span></label>
          <input type="number" name="annee" class="form-control" required
                 value="<?= e($caisse['annee'] ?? date('Y')) ?>"
                 min="2020" max="2099" step="1">
        </div>
      </div>
      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Libellé <span style="color:var(--danger);">*</span></label>
        <input type="text" name="libelle" class="form-control" required
               value="<?= e($caisse['libelle'] ?? '') ?>"
               placeholder="Ex : Subvention Trimestre 1 - 2026">
      </div>
      <div class="fg2" style="margin-top:14px;">
        <div class="form-group">
          <label class="form-label">Source de financement (Exp: BND)</label>
          <input type="text" name="source_financement" class="form-control"
                 value="<?= e($caisse['source_financement'] ?? '') ?>"
                 placeholder="Ex : État guinéen, Banque Mondiale…">
        </div>
        <div class="form-group">
          <label class="form-label">Statut</label>
          <select name="statut" class="form-control">
            <option value="active"    <?= ($caisse['statut']??'active')==='active'   ?'selected':'' ?>>Active</option>
            <option value="suspendue" <?= ($caisse['statut']??'')==='suspendue'      ?'selected':'' ?>>Suspendue</option>
            <option value="cloturee"  <?= ($caisse['statut']??'')==='cloturee'       ?'selected':'' ?>>Clôturée</option>
          </select>
        </div>
      </div>
      <div class="fg2" style="margin-top:14px;">
        <div class="form-group">
          <label class="form-label">Date de début</label>
          <input type="date" name="date_debut" class="form-control"
                 value="<?= e($caisse['date_debut'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Date de fin prévue</label>
          <input type="date" name="date_fin" class="form-control"
                 value="<?= e($caisse['date_fin'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Description / Observations</label>
        <textarea name="description" class="form-control" rows="3"
                  placeholder="Notes sur cette caisse…"><?= e($caisse['description'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- Montant -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-coins"></i></div>
      <div><h3>Montant alloué</h3><div class="sub">Budget total de cette tranche</div></div>
    </div>
    <div class="fsec-bd">
      <div class="form-group">
        <label class="form-label">Montant initial (GNF) <span style="color:var(--danger);">*</span></label>
        <input type="number" name="montant_initial" class="form-control" required
               value="<?= e($caisse['montant_initial'] ?? '') ?>"
               placeholder="0" min="0" step="0.01"
               style="max-width:340px;">
        <div style="font-size:.71rem;color:var(--text-muted);margin-top:4px;">
          En Francs Guinéens (GNF). Ce montant ne peut être réduit en dessous des dépenses déjà validées.
        </div>
      </div>
    </div>
  </div>

  <!-- Lignes budgétaires par catégorie -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-table-cells-large"></i></div>
      <div><h3>Lignes budgétaires par catégorie</h3><div class="sub">Optionnel — définir un plafond par catégorie de dépense</div></div>
    </div>
    <div class="fsec-bd">
      <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:12px;">
        Laissez à 0 si vous ne souhaitez pas limiter une catégorie. La somme des lignes ne doit pas dépasser le montant total.
      </div>
      <?php foreach ($cats as $cat): ?>
      <div class="ligne-row">
        <div>
          <div style="font-size:.85rem;font-weight:700;">
            <span style="background:var(--blue-100);color:var(--blue-700);padding:1px 8px;border-radius:5px;font-size:.72rem;margin-right:6px;"><?= e($cat['code_categorie']) ?></span>
            <?= e($cat['libelle']) ?>
          </div>
        </div>
        <div>
          <input type="number" name="lignes[<?= $cat['id'] ?>]" class="form-control ligne-montant"
                 value="<?= e($lignes_existantes[$cat['id']] ?? '0') ?>"
                 min="0" step="0.01" placeholder="0">
        </div>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:12px;padding:10px 14px;background:var(--blue-50);border-radius:8px;font-size:.8rem;font-weight:600;color:var(--blue-700);">
        Total alloué aux lignes : <span id="totalLignes">0</span> GNF
      </div>
    </div>
  </div>

  <div class="form-actions">
    <a href="<?= $caisse ? 'caisse_voir.php?id='.$cid : 'index.php' ?>" class="btn btn-secondary">
      <i class="fa-solid fa-xmark"></i> Annuler
    </a>
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid <?= $mode==='creer'?'fa-plus':'fa-floppy-disk' ?>"></i>
      <?= $mode==='creer' ? 'Créer la caisse' : 'Enregistrer' ?>
    </button>
  </div>
</form>

<?php
$extra_scripts = '<script>
function updateTotal() {
  let tot = 0;
  document.querySelectorAll(".ligne-montant").forEach(i => { tot += parseFloat(i.value)||0; });
  document.getElementById("totalLignes").textContent = new Intl.NumberFormat("fr-FR").format(tot);
}
document.querySelectorAll(".ligne-montant").forEach(i => i.addEventListener("input", updateTotal));
updateTotal();
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
