<?php
// modules/identite/index.php — Portail Identité du CRICT
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
$q             = clean($_GET['q'] ?? '');
$recherche_on  = identite_ui_recherche_active($q);
$resultats     = $recherche_on ? identite_recherche_etendue($q, 50) : [];
$champs        = identite_champs_lister();
$moyennes      = identite_champs_moyennes();
$derniere_maj  = identite_derniere_maj();
$swot          = identite_swot_lister();
$recommandations = identite_recommandations_lire();
$swot_search   = identite_swot_texte_recherche($swot, $recommandations);

$page_titre            = 'DGRS — Référentiel d\'évaluation';
$page_breadcrumb       = 'Évaluation · DGRS';
$module_actif          = 'IDENTITE_CRICT';
$evaluation_sous_actif = 'DGRS';

include __DIR__ . '/../../views/layout/header.php';
identite_ui_styles();
?>

<div class="id-hero">
  <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
    <div style="flex:1;min-width:200px;">
      <h2><i class="fa-solid fa-building-columns" style="margin-right:8px;opacity:.9;"></i>Référentiel DGRS</h2>
      <p>Référence institutionnelle officielle (modèle DGRS) — informations certifiées pour répondre aux partenaires et aux missions d'évaluation.</p>
      <?php if ($derniere_maj): ?>
        <p style="margin-top:10px;font-size:.78rem;opacity:.75;">
          <i class="fa-regular fa-clock"></i> Dernière mise à jour : <?= format_datetime($derniere_maj) ?>
        </p>
      <?php endif; ?>
    </div>
    <?php if ($peut_modifier): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="identification.php?edit=1" class="btn btn-primary" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.35);">
        <i class="fa-solid fa-pen"></i> Modifier
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
identite_ui_recherche_form(
    $q,
    'Rechercher (cadre légal, organigramme, preuves, réponses…)',
    'index.php',
    []
);
identite_ui_recherche_panneau(
    $q,
    $resultats,
    'identite_ui_recherche_item_dgrs',
    'Essayez un autre mot-clé (ex. organigramme, qualité, décret…)'
);
?>

<div class="id-grid" id="id-grid"<?= $recherche_on ? ' style="display:none;"' : '' ?>>
  <a href="identification.php" class="id-card id-card-active" data-search="identification dénomination domaines axes adresse contact site web">
    <div class="id-card-icon"><i class="fa-solid fa-building-columns"></i></div>
    <h3>Identification</h3>
    <p>Dénomination, domaines, axes de recherche, coordonnées officielles.</p>
  </a>

  <?php foreach ($champs as $ch):
      if (!$ch['actif']) continue;
      $moy = $moyennes[(int)$ch['id']] ?? null;
      $score_aff = $moy !== null ? (int)round($moy) : null;
  ?>
  <a href="champ.php?id=<?= (int)$ch['id'] ?>" class="id-card id-card-active"
     data-search="<?= e(mb_strtolower($ch['libelle'] . ' ' . ($ch['description'] ?? ''))) ?>"
     data-champ-id="<?= (int)$ch['id'] ?>">
    <div class="id-card-icon"><i class="fa-solid <?= e($ch['icone']) ?>"></i></div>
    <h3><?= e($ch['libelle']) ?></h3>
    <p><?= e($ch['description'] ?? '') ?></p>
    <?php if ($score_aff !== null): ?>
    <div class="id-card-maturite" title="Maturité moyenne du champ : <?= $moy ?>/5">
      <span class="id-score" style="color:<?= identite_score_couleur($score_aff) ?>">
        <?= identite_ui_score_etoiles($score_aff) ?>
        <span class="id-card-maturite-lbl"><?= $moy ?>/5</span>
      </span>
    </div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="id-swot-preview" id="id-swot-preview" data-search="<?= e($swot_search) ?>"<?= $recherche_on ? ' style="display:none;"' : '' ?>>
  <div class="id-swot-preview-head">
    <h3><i class="fa-solid fa-table-columns" style="color:var(--blue-500);"></i> Analyse SWOT</h3>
    <a href="swot.php" class="id-swot-voir-tout">Voir tout <i class="fa-solid fa-arrow-right"></i></a>
  </div>
  <?= identite_ui_swot_apercu($swot) ?>
  <p class="id-swot-preview-reco">
    <strong>Recommandations :</strong>
    <?php if (identite_est_complete($recommandations ?? '')): ?>
      <span class="id-swot-preview-reco-txt"><?= nl2br(e($recommandations)) ?></span>
    <?php else: ?>
      <span class="id-swot-vide-inline"><?= e(IDENTITE_A_COMPLETER) ?></span>
    <?php endif; ?>
  </p>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php';
