<?php
// modules/projet_recherche/creer.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet_recherche.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(RECHERCHE_MODULE, 'CREER');
recherche_assurer_schema();

$page_titre = 'Nouveau projet de recherche';
$page_breadcrumb = 'Projets · Projet de recherche · Nouveau';
$module_actif = 'PROJETS';
$projet_sous_actif = 'RECHERCHE';

include __DIR__ . '/../../views/layout/header.php';
recherche_ui_styles();
?>

<div class="pr-wrap" style="max-width:560px;">
  <div class="pr-toolbar">
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  </div>

  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-file-lines" style="color:var(--blue-500);"></i>
      <h2>Créer un nouveau projet</h2>
    </div>
    <div class="card-body">
      <form method="post" action="actions.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="creer">
        <div class="pr-field">
          <label for="titre">Titre du projet</label>
          <input type="text" name="titre" id="titre" class="form-control" required maxlength="255"
                 placeholder="Titre du projet de recherche">
        </div>
        <button type="submit" class="btn btn-primary">Créer</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
