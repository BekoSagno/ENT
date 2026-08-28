<?php
// modules/identite/swot.php — Analyse SWOT et recommandations
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
$swot          = identite_swot_lister();
$recommandations = identite_recommandations_lire();

$swot_cols = [
    'forces'      => ['lbl' => 'Forces',       'key' => 'f', 'mini' => 'f'],
    'faiblesses'  => ['lbl' => 'Faiblesses',   'key' => 'w', 'mini' => 'w'],
    'opportunites'=> ['lbl' => 'Opportunités', 'key' => 'o', 'mini' => 'o'],
    'menaces'     => ['lbl' => 'Menaces',      'key' => 't', 'mini' => 't'],
];

$page_titre      = 'Analyse SWOT & recommandations';
$page_breadcrumb = 'Évaluation · DGRS · SWOT';
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
    <a href="swot.php" class="btn btn-secondary btn-sm">Annuler</a>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <i class="fa-solid fa-table-columns" style="color:var(--blue-500);"></i>
    <h2>Analyse SWOT</h2>
  </div>
  <div class="card-body">
    <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:20px;">
      Analyse institutionnelle — vous pouvez saisir plusieurs lignes de texte dans chaque cellule.
    </p>

    <?php if ($mode_edit): ?>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="sauver_swot">
      <div class="id-swot-wrap">
        <table class="id-swot-table">
          <thead>
            <tr>
              <th class="id-swot-col-num">N°</th>
              <?php foreach ($swot_cols as $col): ?>
              <th class="id-swot-col-<?= $col['key'] ?>"><?= e($col['lbl']) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($swot as $row): $n = (int)$row['ligne']; ?>
            <tr>
              <td><div class="id-swot-num"><?= $n ?></div></td>
              <?php foreach ($swot_cols as $k => $col): ?>
              <td>
                <textarea name="swot[<?= $n ?>][<?= $k ?>]" class="form-control" rows="4"
                          placeholder="<?= e(IDENTITE_A_COMPLETER) ?>"><?= e($row[$k] ?? '') ?></textarea>
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="submit" class="btn btn-secondary btn-sm" style="margin-top:16px;">
        <i class="fa-solid fa-save"></i> Enregistrer le SWOT
      </button>
    </form>
    <?php else: ?>

    <div class="id-swot-wrap">
      <table class="id-swot-table">
        <thead>
          <tr>
            <th class="id-swot-col-num">N°</th>
            <?php foreach ($swot_cols as $col): ?>
            <th class="id-swot-col-<?= $col['key'] ?>"><?= e($col['lbl']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($swot as $row): ?>
          <tr>
            <td><div class="id-swot-num"><?= (int)$row['ligne'] ?></div></td>
            <?php foreach ($swot_cols as $k => $col): ?>
            <td><?= identite_ui_swot_cell($row[$k] ?? '', $col['key']) ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="id-swot-rows-mobile">
      <?php foreach ($swot as $row): ?>
      <div class="id-swot-row-card">
        <div class="id-swot-row-card-h">Ligne <?= (int)$row['ligne'] ?></div>
        <div class="id-swot-row-card-grid">
          <?php foreach ($swot_cols as $k => $col): ?>
          <div class="id-swot-mini id-swot-mini-<?= $col['mini'] ?>">
            <label><?= e($col['lbl']) ?></label>
            <?= identite_ui_swot_cell($row[$k] ?? '', $col['key']) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fa-solid fa-list-check" style="color:var(--blue-500);"></i>
    <h2>Recommandations prioritaires de l'institution</h2>
  </div>
  <div class="card-body">
    <?php if ($mode_edit): ?>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="sauver_recommandations">
      <textarea name="contenu" class="form-control" rows="10"
                placeholder="Listez les recommandations prioritaires (plusieurs lignes possibles)…"><?= e($recommandations ?? '') ?></textarea>
      <button type="submit" class="btn btn-primary btn-sm" style="margin-top:12px;">
        <i class="fa-solid fa-save"></i> Enregistrer les recommandations
      </button>
    </form>
    <?php else: ?>
    <div class="id-reponse <?= identite_est_complete($recommandations ?? '') ? '' : 'vide' ?>">
      <?= identite_ui_afficher_texte($recommandations ?? '') ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
