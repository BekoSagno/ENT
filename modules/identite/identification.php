<?php
// modules/identite/identification.php — Identification institutionnelle
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/identite.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission('IDENTITE_CRICT', 'VOIR');

identite_appliquer_preremplissage();

$peut_modifier = identite_peut_modifier();
$mode_edit     = $peut_modifier && !empty($_GET['edit']);
$champs        = identite_identification_lister();

$page_titre      = 'Identification institutionnelle';
$page_breadcrumb = 'Évaluation · DGRS · Identification';
$module_actif    = 'IDENTITE_CRICT';
$evaluation_sous_actif = 'DGRS';

include __DIR__ . '/../../views/layout/header.php';
identite_ui_styles();
?>

<div class="id-topbar">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Portail</a>
  <div class="spacer"></div>
  <?php if ($peut_modifier && !$mode_edit): ?>
    <a href="?edit=1" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>
  <?php elseif ($mode_edit): ?>
    <a href="identification.php" class="btn btn-secondary btn-sm">Annuler</a>
  <?php endif; ?>
</div>

<div class="id-hero" style="margin-bottom:20px;">
  <h2><i class="fa-solid fa-building-columns" style="margin-right:8px;"></i>Identification de l'institution</h2>
  <p>Informations officielles du centre — à communiquer aux partenaires et aux missions d'évaluation.</p>
</div>

<?php if ($mode_edit): ?>
<form method="POST" action="actions.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="sauver_identification">
  <div class="id-id-grid">
    <?php foreach ($champs as $f): ?>
    <div class="id-id-item">
      <label for="id_<?= e($f['cle']) ?>"><?= e($f['libelle']) ?></label>
      <?php if ($f['cle'] === 'site_web'): ?>
        <input type="url" name="<?= e($f['cle']) ?>" id="id_<?= e($f['cle']) ?>" class="form-control"
               value="<?= e($f['valeur'] ?? '') ?>" placeholder="https://…">
      <?php else: ?>
        <textarea name="<?= e($f['cle']) ?>" id="id_<?= e($f['cle']) ?>" class="form-control" rows="3"
                  placeholder="<?= e(IDENTITE_A_COMPLETER) ?>"><?= e($f['valeur'] ?? '') ?></textarea>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
    <a href="identification.php" class="btn btn-secondary">Annuler</a>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Publier</button>
  </div>
</form>

<?php else: ?>
<div class="id-id-grid">
  <?php foreach ($champs as $f):
      $vide = !identite_est_complete($f['valeur'] ?? '');
  ?>
  <div class="id-id-item">
    <label><?= e($f['libelle']) ?></label>
    <div class="id-id-val <?= $vide ? 'vide' : '' ?>">
      <?php if ($vide): ?>
        <?= e(IDENTITE_A_COMPLETER) ?>
      <?php elseif ($f['cle'] === 'site_web' && filter_var($f['valeur'], FILTER_VALIDATE_URL)): ?>
        <a href="<?= e($f['valeur']) ?>" target="_blank" rel="noopener" style="color:var(--blue-600);">
          <?= e($f['valeur']) ?> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:.7rem;"></i>
        </a>
      <?php else: ?>
        <?= nl2br(e($f['valeur'])) ?>
      <?php endif; ?>
    </div>
    <?php if (!empty($f['modifie_le'])): ?>
      <div style="font-size:.68rem;color:var(--text-muted);margin-top:8px;">
        MAJ <?= format_datetime($f['modifie_le']) ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
