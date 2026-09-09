<?php
// modules/projet_article/creer.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet_article.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(ARTICLE_MODULE, 'CREER');
article_assurer_schema();

$page_titre = 'Nouvel article scientifique';
$page_breadcrumb = 'Projets · Article scientifique · Nouveau';
$module_actif = 'PROJETS';
$projet_sous_actif = 'ARTICLE';

include __DIR__ . '/../../views/layout/header.php';
article_ui_styles();
?>

<div class="pa-wrap" style="max-width:560px;">
  <div class="pa-toolbar">
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  </div>

  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-newspaper" style="color:var(--blue-500);"></i>
      <h2>Créer un article</h2>
    </div>
    <div class="card-body">
      <form method="post" action="actions.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="creer">
        <div class="pa-field">
          <label for="titre">Titre de l’article</label>
          <input type="text" name="titre" id="titre" class="form-control" required maxlength="255"
                 placeholder="Titre de l’article scientifique">
        </div>
        <button type="submit" class="btn btn-primary">Créer</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
