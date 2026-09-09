<?php
// modules/projet_article/voir.php — Aperçu complet
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet_article.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(ARTICLE_MODULE, 'VOIR');
article_assurer_schema();

$id = (int)($_GET['id'] ?? 0);
$d = article_charger($id);
if (!$d) {
    set_flash('error', 'Article introuvable.');
    redirect('/modules/projet_article/index.php');
}
if (!article_peut_gerer() && (int)$d['cree_par'] !== (int)user_id()) {
    http_response_code(403);
    exit('Accès refusé.');
}

$peut_mod = article_peut_modifier($d);

$page_titre = 'Aperçu article';
$page_breadcrumb = 'Projets · ' . $d['code'];
$module_actif = 'PROJETS';
$projet_sous_actif = 'ARTICLE';

include __DIR__ . '/../../views/layout/header.php';
article_ui_styles();
?>

<div class="pa-wrap">
  <div class="pa-toolbar">
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Liste</a>
    <?php if ($peut_mod): ?>
    <a href="editer.php?id=<?= $id ?>&etape=1" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>
    <?php endif; ?>
    <div class="spacer"></div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
  </div>

  <?php article_ui_synthese_a4($d); ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
