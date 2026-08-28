<?php
// modules/projet_recherche/index.php — Liste des projets de recherche
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet_recherche.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(RECHERCHE_MODULE, 'VOIR');
recherche_assurer_schema();

$peut_creer = a_permission(RECHERCHE_MODULE, 'CREER') || recherche_peut_gerer();
$f = [
    'statut' => clean($_GET['statut'] ?? ''),
    'q'      => clean($_GET['q'] ?? ''),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$res = recherche_lister($f, 12, ($page - 1) * 12);
$pager = function_exists('paginer') ? paginer($res['total'], 12, $page) : null;
$statuts = recherche_statut_cfg();

$page_titre = 'Projet de recherche';
$page_breadcrumb = 'Projets · Projet de recherche';
$module_actif = 'PROJETS';
$projet_sous_actif = 'RECHERCHE';

include __DIR__ . '/../../views/layout/header.php';
recherche_ui_styles();
?>

<div class="pr-wrap">
  <div class="pr-toolbar">
    <?php if ($peut_creer): ?>
    <a href="creer.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouveau projet</a>
    <?php endif; ?>
    <div class="spacer"></div>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <input type="text" name="q" class="form-control" style="min-width:200px;"
             value="<?= e($f['q']) ?>" placeholder="Rechercher…">
      <select name="statut" class="form-control">
        <option value="">Tous les statuts</option>
        <?php foreach ($statuts as $k => $s): ?>
        <option value="<?= e($k) ?>" <?= $f['statut'] === $k ? 'selected' : '' ?>><?= e($s[0]) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-search"></i></button>
    </form>
  </div>

  <?php if (!$res['data']): ?>
  <div class="card">
    <div class="card-body" style="text-align:center;padding:40px 20px;color:var(--text-muted);">
      <i class="fa-solid fa-folder-open" style="font-size:2rem;opacity:.4;margin-bottom:12px;"></i>
      <p style="margin:0;">Aucun projet pour le moment.</p>
      <?php if ($peut_creer): ?>
      <a href="creer.php" class="btn btn-primary btn-sm" style="margin-top:14px;">Créer un nouveau projet</a>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="pr-list-grid">
    <?php foreach ($res['data'] as $d):
        [$lbl, $bg, $co] = $statuts[$d['statut']] ?? $statuts['BROUILLON'];
        $pct = (int)$d['pct_complet'];
    ?>
    <a href="editer.php?id=<?= (int)$d['id'] ?>" class="pr-list-card">
      <div class="pr-list-top">
        <span class="pr-list-code"><?= e($d['code']) ?></span>
        <span class="pr-list-badge" style="background:<?= $bg ?>;color:<?= $co ?>;"><?= e($lbl) ?></span>
      </div>
      <h3 class="pr-list-titre"><?= e($d['titre']) ?></h3>
      <div>
        <div class="pr-list-prog-row">
          <span>Avancement</span>
          <strong><?= $pct ?> %</strong>
        </div>
        <div class="pr-prog"><div class="pr-prog-fill" style="width:<?= $pct ?>%;"></div></div>
      </div>
      <div class="pr-list-meta">
        <?= e($d['auteur_nom'] ?? '—') ?>
        <?php if (!empty($d['modifie_le'])): ?>
        · maj <?= format_datetime($d['modifie_le']) ?>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
