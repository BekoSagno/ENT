<?php
// modules/identite/referentiel.php — Portail Auto-évaluation (CIAQ)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/identite.php';
require_once __DIR__ . '/../../models/auto_anaq.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission('IDENTITE_CRICT', 'VOIR');

auto_anaq_assurer_donnees();

// Sélection éventuelle via URL (?canevas=ID)
if (!empty($_GET['canevas'])) {
    auto_anaq_canevas_courant((int)$_GET['canevas']);
}

$ref_anaq        = identite_referentiel('AUTO_EVALUATION_ANAQ');
$peut_modifier   = identite_peut_modifier();
$mode_edit       = $peut_modifier && !empty($_GET['edit']);
$canevas         = auto_anaq_canevas_courant();
$canevas_id      = $canevas ? (int)$canevas['id'] : 0;
$canevas_liste   = auto_anaq_canevas_lister(false);
$q               = clean($_GET['q'] ?? '');
$filtre          = clean($_GET['f'] ?? 'tout');
$recherche_on    = identite_ui_recherche_active($q);
$resultats       = $recherche_on ? auto_anaq_filtrer_recherche(auto_anaq_recherche($q, 50, $mode_edit, $canevas_id), $filtre, $q) : [];
$champs          = auto_anaq_champs_lister($canevas_id);
$global          = auto_anaq_completude_globale($canevas_id);
$derniere_maj    = auto_anaq_derniere_maj();

$page_titre            = 'Évaluation — ' . ($ref_anaq['libelle'] ?? 'Auto-évaluation (CIAQ)');
$page_breadcrumb       = 'Évaluation · ' . ($ref_anaq['libelle'] ?? 'Auto-évaluation (CIAQ)');
$module_actif          = 'IDENTITE_CRICT';
$evaluation_sous_actif = 'AUTO_EVALUATION_ANAQ';

include __DIR__ . '/../../views/layout/header.php';
identite_ui_styles();
auto_anaq_ui_styles();
?>

<div class="id-hero">
  <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
    <div style="flex:1;min-width:200px;">
      <h2><i class="fa-solid fa-clipboard-check" style="margin-right:8px;opacity:.9;"></i>Auto-évaluation ANAQ</h2>
      <p>Auto-évaluation interne — Cellule Interne d'Assurance Qualité — Auto-évaluation interne préparatoire pour anticiper l'évaluation ANAQ.</p>
      <?php if ($canevas): ?>
      <p style="margin-top:10px;font-size:.82rem;opacity:.9;">
        <i class="fa-solid fa-folder-open"></i>
        Référentiel chargé :
        <strong><?= e($canevas['titre']) ?></strong><?= $canevas['version'] ? ' · v' . e($canevas['version']) : '' ?>
        <?php if (!empty($canevas['fichier_guide'])): ?>
        · <a href="auto_canevas_download.php?id=<?= (int)$canevas['id'] ?>" style="color:#fff;text-decoration:underline;" target="_blank">Guide</a>
        <?php endif; ?>
      </p>
      <?php endif; ?>
      <div class="aa-prog-hero" role="progressbar"
           aria-valuenow="<?= (int)$global['pct'] ?>" aria-valuemin="0" aria-valuemax="100"
           aria-label="Avancement global de l'auto-évaluation">
        <div class="aa-prog-hero-meta">
          <span>Avancement global</span>
          <strong><?= (int)$global['pct'] ?> %</strong>
        </div>
        <div class="aa-prog-bar">
          <div class="aa-prog-fill" style="width:<?= (int)$global['pct'] ?>%;"></div>
        </div>
        <div class="aa-prog-hero-hint">
          <?= (int)$global['faits'] ?> / <?= (int)$global['total'] ?> points complétés
          <?php if ((int)$global['total'] > 0 && (int)$global['faits'] < (int)$global['total']): ?>
          · <?= (int)$global['total'] - (int)$global['faits'] ?> restant<?= ((int)$global['total'] - (int)$global['faits']) > 1 ? 's' : '' ?>
          <?php elseif ((int)$global['total'] > 0): ?>
          · grille complète
          <?php endif; ?>
        </div>
      </div>
      <?php if ($derniere_maj): ?>
      <p style="margin-top:8px;font-size:.78rem;opacity:.75;">
        <i class="fa-regular fa-clock"></i> Dernière mise à jour : <?= format_datetime($derniere_maj) ?>
      </p>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="auto_canevas.php" class="btn btn-secondary" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;">
        <i class="fa-solid fa-folder-open"></i> Canevas
      </a>
      <?php if ($peut_modifier && !$mode_edit): ?>
      <a href="referentiel.php?edit=1" class="btn btn-primary" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.35);">
        <i class="fa-solid fa-pen"></i> Modifier
      </a>
      <?php elseif ($mode_edit): ?>
      <a href="referentiel.php" class="btn btn-secondary" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;">
        Annuler
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (count($canevas_liste) > 1): ?>
<div class="aa-zone" style="margin-bottom:16px;">
  <div class="aa-zone-lbl"><i class="fa-solid fa-layer-group"></i> Choisir un référentiel</div>
  <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0 2px;">
    <?php foreach ($canevas_liste as $cv):
        $sel = $canevas_id === (int)$cv['id'];
        $href = 'referentiel.php?canevas=' . (int)$cv['id'] . ($mode_edit ? '&edit=1' : '');
    ?>
    <a href="<?= e($href) ?>" class="btn btn-sm <?= $sel ? 'btn-primary' : 'btn-secondary' ?>">
      <?= e($cv['titre']) ?><?= $cv['version'] ? ' (v' . e($cv['version']) . ')' : '' ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php
$hidden_search = $mode_edit ? ['edit' => '1'] : [];
if ($filtre !== 'tout' && $q !== '') {
    $hidden_search['f'] = $filtre;
}
identite_ui_recherche_form(
    $q,
    'Rechercher une question, un document attendu, un mot-clé…',
    'referentiel.php' . ($mode_edit ? '?edit=1' : ''),
    $hidden_search
);

if ($recherche_on):
    $base_filtre = 'referentiel.php?q=' . rawurlencode($q) . ($mode_edit ? '&edit=1' : '');
    $filtres_opts = [
        'tout'     => 'Tout',
        'question' => 'Questions',
        'preuves'  => 'Documents attendus',
        'reponse'  => 'Réponses',
    ];
?>
<div class="aa-filtres" id="aa-filtres">
  <?php foreach ($filtres_opts as $fk => $flbl): ?>
  <a href="<?= e($base_filtre . '&f=' . $fk) ?>" class="aa-filtre<?= $filtre === $fk ? ' on' : '' ?>"><?= e($flbl) ?></a>
  <?php endforeach; ?>
</div>
<?php
endif;

identite_ui_recherche_panneau(
    $q,
    $resultats,
    'identite_ui_recherche_item_anaq',
    'Essayez un autre mot-clé (ex. organigramme, qualité, publication…)'
);
?>

<div class="id-grid" id="aa-grid"<?= $recherche_on ? ' style="display:none;"' : '' ?>>
  <?php foreach ($champs as $ch):
      $comp = auto_anaq_completude_champ((int)$ch['id']);
  ?>
  <a href="auto_champ.php?id=<?= (int)$ch['id'] ?><?= auto_anaq_query_edit($mode_edit) ?>" class="id-card id-card-active aa-card"
     data-search="<?= e(mb_strtolower($ch['libelle_court'] . ' ' . $ch['libelle'])) ?>">
    <div class="id-card-icon"><i class="fa-solid <?= e($ch['icone']) ?>"></i></div>
    <h3><?= e($ch['libelle_court']) ?></h3>
    <?php
    $desc = trim((string)($ch['description'] ?? ''));
    $court = trim((string)($ch['libelle_court'] ?? ''));
    if ($desc !== '' && mb_strtolower($desc) !== mb_strtolower($court) && mb_strtolower($desc) !== mb_strtolower($court . '.')):
    ?>
    <p><?= e($desc) ?></p>
    <?php endif; ?>
    <div class="aa-prog">
      <div class="aa-prog-bar"><div class="aa-prog-fill" style="width:<?= (int)$comp['pct'] ?>%;"></div></div>
      <span class="aa-prog-lbl"><?= (int)$comp['faits'] ?> / <?= (int)$comp['total'] ?> points complétés</span>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php';
